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
     * Fetch & update Deal Health directly from HuggingFace AI Engine
     */
    public function syncDealHealth(CrmDeal $deal): array
    {
        $userId = auth()->id() ?? $deal->owner_id ?? 1;
        $contactEmail = $deal->contact?->email ?: ($deal->account?->email ?: $deal->lead?->email);
        $authStatus = $this->checkAuthStatus($userId);

        try {
            // First attempt to evaluate via user emails
            $response = Http::withoutVerifying()->timeout(15)->get($this->baseUrl . "/agent/evaluate-emails/{$userId}", [
                'limit'        => 10,
                'sender_email' => $contactEmail,
            ]);

            $data = $response->successful() ? $response->json() : null;
            $rawJsonStr = json_encode($data ?? []);
            $hasPermissionError = str_contains($rawJsonStr, 'insufficientPermissions') || str_contains($rawJsonStr, 'insufficient authentication scopes');

            if (!$data || empty($data['reports']) || !is_array($data['reports'])) {
                // Fallback to deal endpoint
                $fallbackRes = Http::withoutVerifying()->timeout(15)->get($this->baseUrl . "/deals/{$deal->id}");
                if ($fallbackRes->successful()) {
                    $data = $fallbackRes->json();
                }
            }

            if ($data) {
                if (str_contains($rawJsonStr, 'invalid_grant') || str_contains($rawJsonStr, 'Token has been expired')) {
                    return [
                        'success'        => false,
                        'auth_connected' => false,
                        'login_url'      => $authStatus['login_url'],
                        'message'        => 'Google Workspace token has expired or been revoked. Please click the green "Gmail Connected" badge to re-authenticate.',
                    ];
                }

                // Handle API reports array
                $reportData = null;
                if (isset($data['reports']) && is_array($data['reports']) && count($data['reports']) > 0) {
                    $reportData = $data['reports'][0];
                    if ($contactEmail) {
                        foreach ($data['reports'] as $r) {
                            if (str_contains(strtolower($r['sender'] ?? ''), strtolower($contactEmail))) {
                                $reportData = $r;
                                break;
                            }
                        }
                    }
                } elseif (is_array($data) && isset($data[0])) {
                    $reportData = $data[0];
                } else {
                    $reportData = $data;
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

                    if (is_numeric($rawScore)) {
                        $healthScore = ($rawScore <= 5) ? ($rawScore * 20) . '%' : $rawScore . '%';
                    } else {
                        $healthScore = $rawScore ? (string)$rawScore : ($riskLevel === 'High' ? '20%' : '80%');
                    }

                    $sentimentScore = match($rawStatus) {
                        'RISK' => 'Negative / Risk Alert',
                        'NEUTRAL' => 'Neutral',
                        default => 'Positive',
                    };

                    $reasoning = $eval['reasoning'] ?? '';
                    $nextStep = $eval['next_step'] ?? '';
                    $nextBestAction = trim($reasoning . ($nextStep ? " [Suggested Action: {$nextStep}]" : ''));
                } else {
                    $riskLevel = $reportData['risk_level'] ?? 'Low';
                    $sentimentScore = $reportData['sentiment_score'] ?? 'Neutral';
                    $nextBestAction = $reportData['next_best_action'] ?? 'No action required.';
                    $rawHealthScore = $reportData['health_score'] ?? $reportData['sentiment_score'] ?? null;

                    if (is_numeric($rawHealthScore)) {
                        $healthScore = ($rawHealthScore <= 5) ? ($rawHealthScore * 20) . '%' : $rawHealthScore . '%';
                    } else {
                        $healthScore = $rawHealthScore ? (string)$rawHealthScore : ($riskLevel === 'High' ? '20%' : '80%');
                    }
                }

                $deal->update([
                    'risk_level'       => ucfirst(strtolower((string) $riskLevel)),
                    'health_score'     => $healthScore ? (string) $healthScore : null,
                    'sentiment_score'  => (string) $sentimentScore,
                    'next_best_action' => $nextBestAction,
                    'health_synced_at' => Carbon::now(),
                ]);

                $diagMessage = "Evaluated emails for contact: {$contactEmail}";
                if ($hasPermissionError) {
                    $diagMessage = "Gmail Email Read permission is missing for Google Account ({$contactEmail}). Please click Gmail Connected badge & grant Read permission.";
                }

                return [
                    'success'               => true,
                    'auth_connected'        => $authStatus['is_connected'],
                    'connected_email'       => $authStatus['connected_email'],
                    'contact_email_checked' => $contactEmail,
                    'risk_level'            => $deal->risk_level,
                    'health_score'          => $deal->health_score ?: 'N/A',
                    'sentiment_score'       => $deal->sentiment_score,
                    'next_best_action'      => $deal->next_best_action,
                    'health_synced_at'      => $deal->health_synced_at->diffForHumans(),
                    'message'               => $diagMessage,
                ];
            } else {
                return [
                    'success'        => false,
                    'auth_connected' => $authStatus['is_connected'],
                    'login_url'      => $authStatus['login_url'],
                    'message'        => 'API Status ' . $response->status() . ': Could not fetch live health data from AI Engine.',
                ];
            }
        } catch (\Exception $e) {
            Log::error('DealHealthSync Error: ' . $e->getMessage());
            return [
                'success'        => false,
                'auth_connected' => $authStatus['is_connected'],
                'login_url'      => $authStatus['login_url'],
                'message'        => 'API Exception: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Generate AI Email Draft Response for a Deal
     */
    public function generateDraftReply(CrmDeal $deal): array
    {
        try {
            $response = Http::withoutVerifying()->timeout(20)->post($this->baseUrl . '/agent/draft-email', [
                'deal_id' => (int) $deal->id,
                'channel' => 'Email',
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'subject' => $data['subject'] ?? "Follow up regarding Deal #{$deal->deal_number}",
                    'body'    => $data['body'] ?? $data['draft'] ?? $data['message'] ?? '',
                ];
            }
        } catch (\Exception $e) {
            Log::error('DealHealthDraft Error: ' . $e->getMessage());
        }

        $contactName = $deal->contact ? $deal->contact->full_name : 'Valued Client';

        return [
            'success' => true,
            'subject' => "Follow up regarding Deal #{$deal->deal_number} - {$deal->title}",
            'body'    => "Hi {$contactName},\n\nThank you for taking the time to discuss your requirements with us regarding {$deal->title}.\n\nWe would love to know if you have any questions about our proposed terms or timeline. Please let us know a convenient time for a quick sync call.\n\nBest regards,\n" . (auth()->user()->name ?? 'Sales Team'),
        ];
    }
}
