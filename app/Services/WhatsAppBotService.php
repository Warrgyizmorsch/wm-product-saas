<?php

namespace App\Services;

use App\Domains\CRM\Models\Lead;
use App\Models\WhatsAppChatSession;
use App\Models\WhatsAppConfiguration;
use Illuminate\Support\Facades\Log;

class WhatsAppBotService
{
    public function __construct(
        protected WhatsAppService $waService
    ) {}

    /**
     * Validate email format strictly
     */
    public static function isValidEmail(string $email): bool
    {
        $email = trim($email);
        if (empty($email)) {
            return false;
        }
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Process incoming message for interactive B2B/B2C lead qualification flow
     */
    public function handleIncomingMessage(WhatsAppConfiguration $config, string $senderNumber, string $senderName, string $messageBody, ?string $messageId = null): void
    {
        $trimMsg = trim(strtolower($messageBody));

        // Check if user requested a reset
        if (in_array($trimMsg, ['reset', 'restart', 'menu', 'hi', 'hello', 'start'], true)) {
            WhatsAppChatSession::where('phone', $senderNumber)->where('is_completed', false)->update(['is_completed' => true]);
        }

        // Fetch or create current active chat session
        $session = WhatsAppChatSession::where('phone', $senderNumber)
            ->where('is_completed', false)
            ->latest()
            ->first();

        if (!$session) {
            $session = WhatsAppChatSession::create([
                'tenant_id'      => $config->tenant_id,
                'session_key'    => $config->session_key,
                'phone'          => $senderNumber,
                'sender_name'    => $senderName,
                'current_step'   => 0,
                'category'       => null,
                'collected_data' => [],
                'is_completed'   => false,
            ]);
        }

        $collected = $session->collected_data ?: [];

        // STEP 0: Send Initial Category Options (B2B vs B2C)
        if ($session->current_step === 0) {
            $greeting = "Namaste {$senderName}! 🙏 Welcome to Warrgyizmorsch ERP.\n\n"
                      . "Aapki enquiry kis category me hai? Kripya number (1 ya 2) reply karein:\n\n"
                      . "1️⃣ Business / Wholesale Enquiry (B2B)\n"
                      . "2️⃣ Personal / Retail Purchase (B2C)";

            $session->update(['current_step' => 1]);
            $this->waService->sendMessage($senderNumber, $greeting, $messageId);
            return;
        }

        // STEP 1: Category Selection
        if ($session->current_step === 1) {
            if ($trimMsg === '1' || str_contains($trimMsg, 'b2b') || str_contains($trimMsg, 'business')) {
                $session->update([
                    'category'     => 'b2b',
                    'current_step' => 2,
                ]);
                $this->waService->sendMessage($senderNumber, "Great! Business Enquiry selected. 🏢\n\nAapka Full Name (Contact Person) kya hai?", $messageId);
                return;
            } elseif ($trimMsg === '2' || str_contains($trimMsg, 'b2c') || str_contains($trimMsg, 'personal') || str_contains($trimMsg, 'retail')) {
                $session->update([
                    'category'     => 'b2c',
                    'current_step' => 2,
                ]);
                $this->waService->sendMessage($senderNumber, "Great! Retail Purchase selected. 👤\n\nAapka Full Name kya hai?", $messageId);
                return;
            } else {
                $this->waService->sendMessage($senderNumber, "⚠️ Kripya valid option number reply karein:\n\n1️⃣ Business / Wholesale (B2B)\n2️⃣ Personal / Retail (B2C)", $messageId);
                return;
            }
        }

        // STEP 2: Name Received
        if ($session->current_step === 2) {
            $collected['name'] = trim($messageBody);
            $session->update([
                'collected_data' => $collected,
                'current_step'   => 3,
            ]);

            if ($session->category === 'b2b') {
                $this->waService->sendMessage($senderNumber, "Aapki Company / Business ka Naam aur GST Number (optional) kya hai?", $messageId);
            } else {
                $this->waService->sendMessage($senderNumber, "Aapko konsa Product ya Service khareedna hai?", $messageId);
            }
            return;
        }

        // STEP 3: Company Name (B2B) OR Product (B2C)
        if ($session->current_step === 3) {
            if ($session->category === 'b2b') {
                $collected['company_name'] = trim($messageBody);
                $session->update([
                    'collected_data' => $collected,
                    'current_step'   => 4,
                ]);
                $this->waService->sendMessage($senderNumber, "Aapko kis Product/Service ki Wholesale / Bulk Quantity me requirement hai?", $messageId);
            } else {
                $collected['product'] = trim($messageBody);
                $session->update([
                    'collected_data' => $collected,
                    'current_step'   => 4,
                ]);
                $this->waService->sendMessage($senderNumber, "Aap kis City / Location se hain?", $messageId);
            }
            return;
        }

        // STEP 4: Product (B2B) OR City (B2C)
        if ($session->current_step === 4) {
            if ($session->category === 'b2b') {
                $collected['product'] = trim($messageBody);
                $session->update([
                    'collected_data' => $collected,
                    'current_step'   => 5,
                ]);
                $this->waService->sendMessage($senderNumber, "Aapki Company kis City / Location me sthit hai?", $messageId);
            } else {
                $collected['city'] = trim($messageBody);
                $session->update([
                    'collected_data' => $collected,
                    'current_step'   => 5,
                ]);
                $this->waService->sendMessage($senderNumber, "Quotation & Product Catalogue bhejne ke liye aapka valid Email ID kya hai? (e.g. name@example.com)", $messageId);
            }
            return;
        }

        // STEP 5: City (B2B) OR Email & Lead Creation (B2C)
        if ($session->current_step === 5) {
            if ($session->category === 'b2b') {
                $collected['city'] = trim($messageBody);
                $session->update([
                    'collected_data' => $collected,
                    'current_step'   => 6,
                ]);
                $this->waService->sendMessage($senderNumber, "Official Commercial Quotation aur Tax Invoice bhejne ke liye aapka Email ID kya hai? (e.g. info@company.com)", $messageId);
                return;
            } else {
                // B2C Email Validation
                if (!self::isValidEmail($messageBody)) {
                    $this->waService->sendMessage($senderNumber, "⚠️ Email ID galat lag rahi hai!\nKripya ek valid Email ID enter karein (e.g. name@example.com):", $messageId);
                    return;
                }

                $collected['email'] = trim($messageBody);
                $session->update([
                    'collected_data' => $collected,
                    'is_completed'   => true,
                ]);

                $this->createCrmLead($config, $senderNumber, $session, $collected, $messageId);
                return;
            }
        }

        // STEP 6: B2B Email Validation & Lead Creation
        if ($session->current_step === 6 && $session->category === 'b2b') {
            if (!self::isValidEmail($messageBody)) {
                $this->waService->sendMessage($senderNumber, "⚠️ Official Email ID galat lag rahi hai!\nKripya ek valid Email ID enter karein (e.g. sales@company.com):", $messageId);
                return;
            }

            $collected['email'] = trim($messageBody);
            $session->update([
                'collected_data' => $collected,
                'is_completed'   => true,
            ]);

            $this->createCrmLead($config, $senderNumber, $session, $collected, $messageId);
            return;
        }
    }

    /**
     * Create Lead in CRM database and send final confirmation on WhatsApp
     */
    protected function createCrmLead(WhatsAppConfiguration $config, string $senderNumber, WhatsAppChatSession $session, array $collected, ?string $messageId = null): void
    {
        try {
            $isB2b = ($session->category === 'b2b');

            $lead = Lead::create([
                'tenant_id'      => $config->tenant_id,
                'company_id'     => $config->company_id,
                'branch_id'      => $config->branch_id,
                'contact_person' => $collected['name'] ?? $session->sender_name,
                'company_name'   => $isB2b ? ($collected['company_name'] ?? null) : null,
                'email'          => $collected['email'] ?? null,
                'phone'          => $senderNumber,
                'company_phone'  => $senderNumber,
                'lead_type'      => $isB2b ? 'B2B' : 'B2C',
                'requirement'    => $collected['product'] ?? 'WhatsApp Interactive Enquiry',
                'city'           => $collected['city'] ?? null,
                'source'         => 'WhatsApp Bot',
                'status'         => 'New',
            ]);

            $leadNumber = $lead->lead_number ?? ('LD-' . $lead->id);

            if ($isB2b) {
                $confirmMsg = "🎉 Dhanyawad! Aapki B2B Commercial Enquiry (**#{$leadNumber}**) register ho gayi hai.\n\n"
                            . "🏢 Company: " . ($collected['company_name'] ?? 'N/A') . "\n"
                            . "📋 Requirement: " . ($collected['product'] ?? 'N/A') . "\n"
                            . "📧 Email: " . ($collected['email'] ?? 'N/A') . "\n\n"
                            . "Humari Corporate Sales Team aapse jald hi Wholesale Quotation ke saath contact karegi!";
            } else {
                $confirmMsg = "🎉 Dhanyawad! Aapki Retail Enquiry (**#{$leadNumber}**) register ho gayi hai.\n\n"
                            . "👤 Name: " . ($collected['name'] ?? 'N/A') . "\n"
                            . "🛍️ Requirement: " . ($collected['product'] ?? 'N/A') . "\n"
                            . "📍 City: " . ($collected['city'] ?? 'N/A') . "\n\n"
                            . "Humari Customer Executive aapse jald hi WhatsApp & Email par details share karegi!";
            }

            $this->waService->sendMessage($senderNumber, $confirmMsg, $messageId);
        } catch (\Throwable $e) {
            Log::error('Failed to auto-create Lead from WhatsApp Bot:', ['error' => $e->getMessage()]);
            $this->waService->sendMessage($senderNumber, "Thank you! Aapki information receive ho gayi hai. Humari team aapse contact karegi.", $messageId);
        }
    }
}
