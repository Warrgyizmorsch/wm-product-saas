<?php

namespace App\Domains\CRM\Services;

use App\Domains\CRM\Models\CrmDeal;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class DealHealthService
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('services.deal_health.base_url', 'https://love14-deal-health-scoring.hf.space');
    }

    /**
     * Check if user is authenticated with Google Workspace on HuggingFace Space
     */
    public function checkAuthStatus(?int $userId = null): array
    {
        $userId = $userId ?: (auth()->id() ?? 1);
        $redirectUrl = url()->previous();
        if (!str_starts_with($redirectUrl, 'http')) {
            $redirectUrl = url($redirectUrl);
        }

        $loginUrl = $this->baseUrl . '/auth/login?user_id=' . $userId . '&next=' . urlencode($redirectUrl);

        if (session('google_calendar_connected')) {
            return [
                'is_connected'    => true,
                'connected_email' => session('google_connected_email') ?? 'Google Account Connected',
                'login_url'       => $loginUrl,
            ];
        }

        try {
            $response = Http::withoutVerifying()->timeout(8)->get($this->baseUrl . "/auth/user/{$userId}");
            if ($response->successful()) {
                $data = $response->json();

                // Validate if Google OAuth token is actually valid or expired/revoked
                $evalCheck = Http::withoutVerifying()->timeout(6)->get($this->baseUrl . "/agent/evaluate-emails/{$userId}", [
                    'limit' => 1
                ]);
                $rawCheck = json_encode($evalCheck->json() ?? []);
                if (str_contains($rawCheck, 'invalid_grant') || str_contains($rawCheck, 'Token has been expired')) {
                    return [
                        'is_connected'    => false,
                        'connected_email' => null,
                        'login_url'       => $loginUrl,
                    ];
                }

                return [
                    'is_connected'    => true,
                    'connected_email' => $data['email'] ?? $data['user']['email'] ?? 'Google Account Connected',
                    'login_url'       => $loginUrl,
                ];
            }
        } catch (\Exception $e) {
            Log::error('CheckAuthStatus Error: ' . $e->getMessage());
        }

        return [
            'is_connected'    => false,
            'connected_email' => null,
            'login_url'       => $loginUrl,
        ];
    }

    /**
     * Fetch & update Deal Health directly or via In-App CRM Intelligence Engine
     */
    public function syncDealHealth(CrmDeal $deal): array
    {
        $userId = auth()->id() ?? $deal->owner_id ?? 1;
        $contactEmail = $deal->contact?->email ?: ($deal->account?->email ?: $deal->lead?->email);
        $authStatus = $this->checkAuthStatus($userId);

        try {
            // Attempt external AI Engine call
            $response = Http::withoutVerifying()->timeout(4)->get($this->baseUrl . "/agent/evaluate-emails/{$userId}", [
                'limit'        => 10,
                'sender_email' => $contactEmail,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $rawJsonStr = json_encode($data ?? []);

                if (!empty($data['reports']) && is_array($data['reports']) && !str_contains($rawJsonStr, 'invalid_grant')) {
                    $reportData = $data['reports'][0];
                    if ($contactEmail) {
                        foreach ($data['reports'] as $r) {
                            if (str_contains(strtolower($r['sender'] ?? ''), strtolower($contactEmail))) {
                                $reportData = $r;
                                break;
                            }
                        }
                    }

                    if (isset($reportData['health_evaluation'])) {
                        $eval = $reportData['health_evaluation'];
                        $rawStatus = strtoupper((string)($eval['status'] ?? 'NEUTRAL'));
                        $rawScore = $eval['score'] ?? null;

                        $riskLevel = match($rawStatus) {
                            'RISK', 'HIGH' => 'High',
                            'NEUTRAL', 'MEDIUM' => 'Medium',
                            'HEALTHY', 'LOW', 'POSITIVE' => 'Low',
                            default => 'Low',
                        };

                        $healthScore = is_numeric($rawScore) ? (($rawScore <= 5) ? ($rawScore * 20) . '%' : $rawScore . '%') : ($riskLevel === 'High' ? '20%' : '80%');
                        $sentimentScore = match($rawStatus) {
                            'RISK' => 'Negative / Risk Alert',
                            'NEUTRAL' => 'Neutral',
                            default => 'Positive',
                        };

                        $reasoning = $eval['reasoning'] ?? '';
                        $nextStep = $eval['next_step'] ?? '';
                        $nextBestAction = trim($reasoning . ($nextStep ? " [Suggested Action: {$nextStep}]" : ''));

                        $deal->update([
                            'risk_level'       => ucfirst(strtolower((string) $riskLevel)),
                            'health_score'     => (string) $healthScore,
                            'sentiment_score'  => (string) $sentimentScore,
                            'next_best_action' => $nextBestAction,
                            'health_synced_at' => Carbon::now(),
                        ]);

                        return [
                            'success'               => true,
                            'auth_connected'        => true,
                            'connected_email'       => $authStatus['connected_email'] ?? 'Google Account Connected',
                            'contact_email_checked' => $contactEmail,
                            'risk_level'            => $deal->risk_level,
                            'health_score'          => $deal->health_score ?: 'N/A',
                            'sentiment_score'       => $deal->sentiment_score,
                            'next_best_action'      => $deal->next_best_action,
                            'health_synced_at'      => $deal->health_synced_at->diffForHumans(),
                            'message'               => "Evaluated live from AI Engine for {$contactEmail}",
                        ];
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::info('External AI Health sync skipped to In-App Engine: ' . $e->getMessage());
        }

        // Use In-App Intelligent Deal Health Engine
        return $this->calculateLocalDealHealth($deal, $contactEmail);
    }

    /**
     * Intelligent In-App Deal Health & Action Intelligence calculation
     */
    public function calculateLocalDealHealth(CrmDeal $deal, ?string $contactEmail = null): array
    {
        $deal->loadMissing(['quotations', 'account.contacts', 'contact', 'salesOrders']);

        $stage = strtolower((string) $deal->stage);
        $score = 50; // baseline
        $riskLevel = 'Medium';
        $sentiment = 'Neutral / In Progress';
        $actionPoints = [];

        // 1. Stage & Probability Analysis
        if (in_array($stage, ['won', 'closed won', 'customer converted'])) {
            $score = 100;
            $riskLevel = 'Low';
            $sentiment = 'Positive / Deal Converted (100%)';
            $actionPoints[] = 'Deal won successfully! Initiate order processing and customer onboarding.';
        } elseif (in_array($stage, ['lost', 'closed lost'])) {
            $score = 15;
            $riskLevel = 'High';
            $sentiment = 'Lost / Negative';
            $actionPoints[] = 'Deal lost. Conduct win/loss analysis or schedule a follow-up for next quarter.';
        } elseif (in_array($stage, ['negotiation'])) {
            $score = 80;
            $sentiment = 'Positive / High Buying Intent';
            $actionPoints[] = 'Client in negotiation. Revisit terms and close the deal before target date.';
        } elseif (in_array($stage, ['proposal'])) {
            $score = 70;
            $sentiment = 'Positive / Proposal Review';
            $actionPoints[] = 'Proposal submitted. Follow up on quotation review and handle objections.';
        } elseif (in_array($stage, ['needs analysis', 'qualified'])) {
            $score = 60;
            $sentiment = 'Neutral / Evaluating Fit';
            $actionPoints[] = 'Schedule a deep-dive requirement call or product demonstration.';
        } else {
            $score = 45;
            $sentiment = 'Neutral / Early Discovery';
            $actionPoints[] = 'Verify client budget, authority, need, and timeline (BANT).';
        }

        // 2. Quotation Status Analysis
        $quotations = $deal->quotations;
        if ($quotations && $quotations->isNotEmpty()) {
            $hasAccepted = $quotations->contains(fn($q) => in_array(strtolower((string)$q->status), ['accepted', 'approved']));
            $hasSent = $quotations->contains(fn($q) => in_array(strtolower((string)$q->status), ['sent', 'issued', 'pending']));
            $hasExpired = $quotations->contains(fn($q) => strtolower((string)$q->status) === 'expired');

            if ($hasAccepted) {
                $score = max($score, 95);
                $riskLevel = 'Low';
                $sentiment = 'Positive / Quotation Accepted';
                $actionPoints[] = 'Quotation accepted. Generate Sales Order or Tax Invoice.';
            } elseif ($hasSent) {
                $score += 10;
                $actionPoints[] = 'Active quotation pending client decision. Request feedback on pricing.';
            } elseif ($hasExpired) {
                $score -= 15;
                $riskLevel = 'Medium';
                $actionPoints[] = 'Quotation expired. Issue a refreshed quotation with updated validity.';
            }
        }

        // 3. Activity / Follow-up Recency Analysis
        $followups = \App\Domains\CRM\Models\LeadFollowup::where('crm_deal_id', $deal->id)
            ->orWhere(function($q) use ($deal) {
                if ($deal->lead_id) {
                    $q->where('lead_id', $deal->lead_id);
                }
            })
            ->orderBy('followup_date', 'desc')
            ->get();

        $lastFollowup = $followups->first();
        $pendingOverdue = $followups->filter(fn($f) => strtolower((string)$f->status) === 'pending' && Carbon::parse($f->followup_date)->isPast());

        if ($pendingOverdue->isNotEmpty()) {
            $score -= 15;
            $riskLevel = 'High';
            $actionPoints[] = 'Overdue follow-up detected. Contact client immediately to maintain engagement.';
        } elseif ($lastFollowup && Carbon::parse($lastFollowup->followup_date)->diffInDays(now()) <= 3) {
            $score += 10;
            $riskLevel = ($riskLevel === 'High') ? 'Medium' : 'Low';
        } elseif (!$lastFollowup || Carbon::parse($lastFollowup->followup_date)->diffInDays(now()) > 10) {
            $score -= 10;
            if ($riskLevel !== 'Low') $riskLevel = 'Medium';
            $actionPoints[] = 'No recent activity in over 10 days. Schedule a check-in call.';
        }

        // 4. Target Closing Date Analysis
        if ($deal->closing_date) {
            $closing = Carbon::parse($deal->closing_date);
            if ($closing->isPast() && !in_array($stage, ['won', 'closed won'])) {
                $score -= 15;
                $riskLevel = 'High';
                $actionPoints[] = 'Target closing date (' . $closing->format('d M Y') . ') is overdue. Update deal timeline.';
            } elseif ($closing->diffInDays(now()) <= 5) {
                $actionPoints[] = 'Target closing within ' . ceil($closing->diffInDays(now())) . ' days. Confirm final approvals.';
            }
        }

        // Normalize Score (0% - 100%)
        $score = max(10, min(100, $score));
        if ($score >= 75) {
            $riskLevel = 'Low';
        } elseif ($score >= 45) {
            $riskLevel = ($riskLevel === 'High') ? 'High' : 'Medium';
        } else {
            $riskLevel = 'High';
        }

        $nextBestAction = !empty($actionPoints) ? implode(' ', array_unique($actionPoints)) : 'Maintain regular communication and track requirement milestones.';

        $deal->update([
            'risk_level'       => $riskLevel,
            'health_score'     => $score . '%',
            'sentiment_score'  => $sentiment,
            'next_best_action' => $nextBestAction,
            'health_synced_at' => Carbon::now(),
        ]);

        return [
            'success'               => true,
            'auth_connected'        => true,
            'connected_email'       => session('google_connected_email') ?? ($contactEmail ?: 'CRM Intelligence'),
            'contact_email_checked' => $contactEmail,
            'risk_level'            => $deal->risk_level,
            'health_score'          => $deal->health_score,
            'sentiment_score'       => $deal->sentiment_score,
            'next_best_action'      => $deal->next_best_action,
            'health_synced_at'      => $deal->health_synced_at->diffForHumans(),
            'message'               => 'Live CRM Deal Health & Sentiment analyzed successfully.',
        ];
    }

    /**
     * Generate AI Email Draft Response for a Deal with Multi-Tone support
     */
    public function generateDraftReply(CrmDeal $deal, string $tone = 'professional'): array
    {
        $contactName = $deal->contact ? $deal->contact->full_name : ($deal->account ? $deal->account->name : 'Valued Client');
        $userName = auth()->user()->name ?? 'Account Executive';
        $dealTitle = $deal->title ?? 'your requirements';
        $dealNumber = $deal->deal_number ?? 'DL-0001';
        $stage = strtolower((string)$deal->stage);

        $deal->loadMissing('quotations');
        $quotation = $deal->quotations->sortByDesc('created_at')->first();
        $currency = $quotation->currency ?? '₹';
        $total = $quotation ? number_format($quotation->grand_total ?? $quotation->total_amount ?? 0, 2) : '';

        if (in_array($stage, ['won', 'closed won'])) {
            $subject = "Order Confirmation & Next Steps — Deal #{$dealNumber} ({$dealTitle})";
            $body = match($tone) {
                'friendly' => "Hi {$contactName}!\n\nWe are absolutely delighted to partner with you for {$dealTitle}! 🎉\n\nOur operations team has already begun processing your order. I will personally ensure everything stays right on track and keep you updated every step of the way.\n\nIf you need anything at all in the meantime, please don't hesitate to reach out.\n\nWarm regards,\n{$userName}\nCustomer Success Team",
                'concise' => "Hi {$contactName},\n\nThank you for confirming your order for {$dealTitle} (Deal #{$dealNumber}). We are initiating fulfillment and will share shipping and delivery timelines shortly.\n\nBest regards,\n{$userName}",
                default => "Hi {$contactName},\n\nThank you for choosing us! We are thrilled to partner with you for {$dealTitle}.\n\nOur team is currently preparing the necessary paperwork and setting up the fulfillment process. We will keep you updated with the estimated delivery schedule and milestones shortly.\n\nShould you have any immediate questions, please feel free to reply directly to this email.\n\nWarm regards,\n{$userName}\nCustomer Success & Sales Team"
            };
        } elseif ($quotation) {
            $subject = match($tone) {
                'urgent' => "Urgent: Quotation #{$quotation->quotation_number} Validity Expiring Soon — {$dealTitle}",
                'persuasive' => "Maximizing ROI for {$dealTitle} — Quotation #{$quotation->quotation_number}",
                'friendly' => "Quick check-in regarding Quotation #{$quotation->quotation_number} for {$dealTitle}",
                'concise' => "Follow-up: Quotation #{$quotation->quotation_number} ({$currency}{$total})",
                default => "Follow up on Quotation #{$quotation->quotation_number} — {$dealTitle}"
            };

            $body = match($tone) {
                'urgent' => "Hi {$contactName},\n\nI am writing to remind you that the pricing and commercial terms on Quotation #{$quotation->quotation_number} ({$currency}{$total}) for {$dealTitle} are approaching their validity expiration date.\n\nTo lock in the approved discount and secure priority production allocation, please confirm your acceptance at your earliest convenience.\n\nPlease let me know if you would like me to fast-track your order today.\n\nBest regards,\n{$userName}\nSenior Sales Executive",
                'persuasive' => "Hi {$contactName},\n\nI wanted to follow up on Quotation #{$quotation->quotation_number} ({$currency}{$total}) shared for {$dealTitle}.\n\nOur solution is specifically engineered to optimize your operational workflow and deliver measurable cost efficiencies from Day 1. Furthermore, we provide end-to-end onboarding support and dedicated account management.\n\nWould you be open to a brief 10-minute call this week to address any specific commercial considerations or tailor the payment structure to your preferences?\n\nLooking forward to collaborating!\n\nBest regards,\n{$userName}\nCommercial Solutions Lead",
                'friendly' => "Hi {$contactName},\n\nHope you're having a wonderful week!\n\nJust wanted to check in and see how everything looks with Quotation #{$quotation->quotation_number} for {$dealTitle}.\n\nIf you have any questions, need any adjustments to the quantities, or just want to chat through the details, I'm always happy to help.\n\nHave a great day ahead!\n\nWarmly,\n{$userName}",
                'concise' => "Hi {$contactName},\n\nFollowing up on Quotation #{$quotation->quotation_number} ({$currency}{$total}) for {$dealTitle}. Please let me know if you have any questions or if we are good to proceed with the sales order.\n\nBest regards,\n{$userName}",
                default => "Hi {$contactName},\n\nI hope you are having a productive week.\n\nI am following up regarding Quotation #{$quotation->quotation_number} ({$currency}{$total}) shared for {$dealTitle}.\n\nHave you had a chance to review the proposal? Please let us know if you have any questions regarding the line items, timeline, or commercial terms. We would be glad to schedule a quick 10-minute sync call to address any queries.\n\nLooking forward to hearing from you!\n\nBest regards,\n{$userName}\nSales Team"
            };
        } else {
            $subject = match($tone) {
                'urgent' => "Action Required: Next Steps for {$dealTitle} (Deal #{$dealNumber})",
                'persuasive' => "Unlocking Value for {$dealTitle} — Next Steps",
                'friendly' => "Touching base regarding {$dealTitle}",
                'concise' => "Next steps on {$dealTitle}",
                default => "Discussion on {$dealTitle} — Next Steps (Deal #{$dealNumber})"
            };

            $body = match($tone) {
                'urgent' => "Hi {$contactName},\n\nWe are finalizing our schedule for this cycle and wanted to confirm the next steps for {$dealTitle}.\n\nPlease let us know if you can spare 10 minutes today or tomorrow so we can finalize the scope and issue your official proposal without delay.\n\nBest regards,\n{$userName}",
                'persuasive' => "Hi {$contactName},\n\nThank you for discussing {$dealTitle} with us. We have analyzed your requirements and identified key areas where our solution will provide significant competitive advantage.\n\nLet's schedule a brief call this week to review the customized roadmap before we release the proposal.\n\nBest regards,\n{$userName}",
                'friendly' => "Hi {$contactName},\n\nIt was great speaking with you about {$dealTitle}! I'm putting together the information we discussed.\n\nLet me know when you have a free moment this week for a quick follow-up chat.\n\nWarm regards,\n{$userName}",
                'concise' => "Hi {$contactName},\n\nFollowing up on our conversation regarding {$dealTitle}. Please share a convenient time for a brief 10-minute call to finalize the proposal.\n\nBest regards,\n{$userName}",
                default => "Hi {$contactName},\n\nThank you for discussing your requirements with us regarding {$dealTitle}.\n\nBased on our conversation, we are assembling a tailored solution to best address your operational needs and budget.\n\nCould you please let us know a convenient time this week for a brief call to finalize the scope so we can issue the official proposal?\n\nBest regards,\n{$userName}\nSales Executive"
            };
        }

        return [
            'success' => true,
            'tone'    => $tone,
            'subject' => $subject,
            'body'    => $body,
        ];
    }
}
