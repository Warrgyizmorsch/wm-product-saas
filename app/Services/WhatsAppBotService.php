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
    public static function isValidEmail(?string $email): bool
    {
        if (empty($email)) {
            return false;
        }
        return filter_var(trim($email), FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Smart extractor for Name and Email from a combined text input
     */
    public static function extractNameAndEmail(string $input): array
    {
        $input = trim($input);
        $email = null;
        $name = $input;

        // Extract valid email address using regex
        if (preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $input, $matches)) {
            $email = trim($matches[0]);
            // Remove the email from the string to isolate the name/text
            $nameWithoutEmail = str_replace($matches[0], '', $input);
            // Clean up separators like commas, dashes, colons, brackets
            $cleanedName = preg_replace('/[,\-\/:;|()<>]+/', ' ', $nameWithoutEmail);
            $cleanedName = preg_replace('/\s+/', ' ', trim($cleanedName));

            if (!empty($cleanedName)) {
                $name = $cleanedName;
            } else {
                $name = $email;
            }
        }

        return [
            'name'  => $name,
            'email' => $email,
        ];
    }

    /**
     * Smart extractor / normalizer for GSTIN
     */
    public static function extractGstin(string $input): ?string
    {
        $trimmed = trim($input);
        $lower = strtolower($trimmed);

        if (in_array($lower, ['skip', 'no', 'none', 'n/a', 'na', '-', 'nil', 'not applicable', 'dont have', "don't have"], true)) {
            return null;
        }

        // Match standard 15-character Indian GST format (e.g., 27AAAAA0000A1Z5)
        if (preg_match('/[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}/i', $trimmed, $matches)) {
            return strtoupper($matches[0]);
        }

        return !empty($trimmed) ? strtoupper($trimmed) : null;
    }

    /**
     * Process incoming message for interactive B2B/B2C lead qualification flow
     */
    public function handleIncomingMessage(WhatsAppConfiguration $config, string $senderNumber, string $senderName, string $messageBody, ?string $messageId = null): void
    {
        $rawMsg = trim($messageBody);
        $trimMsg = strtolower($rawMsg);

        // Check if user requested a reset / restart
        if (in_array($trimMsg, ['reset', 'restart', 'menu', 'hi', 'hello', 'start', 'help'], true)) {
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

        // STEP 0: Initial Greeting & Enquiry Type Selection
        if ($session->current_step === 0) {
            $displayName = !empty($senderName) ? " {$senderName}" : '';
            $greeting = "Hello{$displayName}! 👋 Welcome to our service.\n\n"
                      . "Please select the type of your enquiry by replying with **1** or **2**:\n\n"
                      . "1️⃣ Business / Wholesale Enquiry (B2B)\n"
                      . "2️⃣ Personal / Retail Purchase (B2C)";

            $session->update(['current_step' => 1]);
            $this->waService->sendMessage($senderNumber, $greeting, $messageId);
            return;
        }

        // STEP 1: Category Selection
        if ($session->current_step === 1) {
            if ($trimMsg === '1' || str_contains($trimMsg, 'b2b') || str_contains($trimMsg, 'business') || str_contains($trimMsg, 'wholesale')) {
                $session->update([
                    'category'     => 'b2b',
                    'current_step' => 2,
                ]);
                $prompt = "Thank you for choosing **Business Enquiry (B2B)**! 🏢\n\n"
                        . "Please provide your **Company / Business Name** and **Official Email Address**:\n\n"
                        . "*(Example: Acme Enterprises, info@acme.com)*";
                $this->waService->sendMessage($senderNumber, $prompt, $messageId);
                return;
            } elseif ($trimMsg === '2' || str_contains($trimMsg, 'b2c') || str_contains($trimMsg, 'personal') || str_contains($trimMsg, 'retail')) {
                $session->update([
                    'category'     => 'b2c',
                    'current_step' => 2,
                ]);
                $prompt = "Thank you for choosing **Personal / Retail Purchase**! 👤\n\n"
                        . "Please provide your **Full Name** and **Email Address**:\n\n"
                        . "*(Example: John Smith, john@example.com)*";
                $this->waService->sendMessage($senderNumber, $prompt, $messageId);
                return;
            } else {
                $this->waService->sendMessage(
                    $senderNumber,
                    "⚠️ Please reply with a valid option number:\n\n1️⃣ Business / Wholesale Enquiry (B2B)\n2️⃣ Personal / Retail Purchase (B2C)",
                    $messageId
                );
                return;
            }
        }

        // ==========================================
        // B2B FLOW
        // ==========================================
        if ($session->category === 'b2b') {
            // STEP 2 (B2B): Company Name & Company Email
            if ($session->current_step === 2) {
                $parsed = self::extractNameAndEmail($rawMsg);
                $collected['company_name'] = $parsed['name'];
                if (!empty($parsed['email'])) {
                    $collected['company_email'] = $parsed['email'];
                }

                $session->update([
                    'collected_data' => $collected,
                    'current_step'   => 3,
                ]);

                $prompt = "Thank you! What is your company's **GST / Tax Identification Number**?\n\n"
                        . "*(Reply with your GSTIN, or type **Skip** if not applicable)*";
                $this->waService->sendMessage($senderNumber, $prompt, $messageId);
                return;
            }

            // STEP 3 (B2B): GSTIN
            if ($session->current_step === 3) {
                $gstin = self::extractGstin($rawMsg);
                $collected['gstin'] = $gstin;

                $session->update([
                    'collected_data' => $collected,
                    'current_step'   => 4,
                ]);

                $prompt = "Great! Please provide the **Contact Person's Name** and direct **Email Address**:\n\n"
                        . "*(Example: Rajesh Sharma, rajesh@acme.com)*";
                $this->waService->sendMessage($senderNumber, $prompt, $messageId);
                return;
            }

            // STEP 4 (B2B): Contact Person & Direct Email
            if ($session->current_step === 4) {
                $parsed = self::extractNameAndEmail($rawMsg);
                $collected['contact_person'] = $parsed['name'];
                if (!empty($parsed['email'])) {
                    $collected['email'] = $parsed['email'];
                }

                $session->update([
                    'collected_data' => $collected,
                    'current_step'   => 5,
                ]);

                $prompt = "Which **City and State** is your business located in?";
                $this->waService->sendMessage($senderNumber, $prompt, $messageId);
                return;
            }

            // STEP 5 (B2B): City / Location
            if ($session->current_step === 5) {
                $collected['city'] = $rawMsg;

                $session->update([
                    'collected_data' => $collected,
                    'current_step'   => 6,
                ]);

                $prompt = "Please describe your **Product Requirement** (including product names, specifications, or estimated order quantities):";
                $this->waService->sendMessage($senderNumber, $prompt, $messageId);
                return;
            }

            // STEP 6 (B2B): Requirement & Finalization
            if ($session->current_step === 6) {
                $collected['requirement'] = $rawMsg;

                // If no email was provided at all in earlier steps, request it
                $primaryEmail = $collected['email'] ?? ($collected['company_email'] ?? null);
                if (empty($primaryEmail)) {
                    $session->update([
                        'collected_data' => $collected,
                        'current_step'   => 7,
                    ]);
                    $prompt = "Almost done! Please provide your **Official Email Address** to receive the quotation and product catalog:";
                    $this->waService->sendMessage($senderNumber, $prompt, $messageId);
                    return;
                }

                $session->update([
                    'collected_data' => $collected,
                    'is_completed'   => true,
                ]);

                $this->createCrmLead($config, $senderNumber, $session, $collected, $messageId);
                return;
            }

            // STEP 7 (B2B - Fallback Email if missed)
            if ($session->current_step === 7) {
                if (!self::isValidEmail($rawMsg)) {
                    $this->waService->sendMessage($senderNumber, "⚠️ Please enter a valid email address (e.g., info@company.com):", $messageId);
                    return;
                }

                $collected['email'] = trim($rawMsg);
                $session->update([
                    'collected_data' => $collected,
                    'is_completed'   => true,
                ]);

                $this->createCrmLead($config, $senderNumber, $session, $collected, $messageId);
                return;
            }
        }

        // ==========================================
        // B2C FLOW
        // ==========================================
        if ($session->category === 'b2c') {
            // STEP 2 (B2C): Full Name & Email
            if ($session->current_step === 2) {
                $parsed = self::extractNameAndEmail($rawMsg);
                $collected['name'] = $parsed['name'];
                if (!empty($parsed['email'])) {
                    $collected['email'] = $parsed['email'];
                }

                $session->update([
                    'collected_data' => $collected,
                    'current_step'   => 3,
                ]);

                $prompt = "Which **City / Location** are you from?";
                $this->waService->sendMessage($senderNumber, $prompt, $messageId);
                return;
            }

            // STEP 3 (B2C): City
            if ($session->current_step === 3) {
                $collected['city'] = $rawMsg;

                $session->update([
                    'collected_data' => $collected,
                    'current_step'   => 4,
                ]);

                $prompt = "Which **Product or Service** are you looking to purchase?";
                $this->waService->sendMessage($senderNumber, $prompt, $messageId);
                return;
            }

            // STEP 4 (B2C): Product / Requirement
            if ($session->current_step === 4) {
                $collected['requirement'] = $rawMsg;

                // Check if email was provided in Step 2
                if (empty($collected['email'])) {
                    $session->update([
                        'collected_data' => $collected,
                        'current_step'   => 5,
                    ]);

                    $prompt = "Please provide your **Email Address** so we can send you the product details and price quote:";
                    $this->waService->sendMessage($senderNumber, $prompt, $messageId);
                    return;
                }

                $session->update([
                    'collected_data' => $collected,
                    'is_completed'   => true,
                ]);

                $this->createCrmLead($config, $senderNumber, $session, $collected, $messageId);
                return;
            }

            // STEP 5 (B2C - Fallback Email if missed)
            if ($session->current_step === 5) {
                if (!self::isValidEmail($rawMsg)) {
                    $this->waService->sendMessage($senderNumber, "⚠️ Please enter a valid email address (e.g., name@example.com):", $messageId);
                    return;
                }

                $collected['email'] = trim($rawMsg);
                $session->update([
                    'collected_data' => $collected,
                    'is_completed'   => true,
                ]);

                $this->createCrmLead($config, $senderNumber, $session, $collected, $messageId);
                return;
            }
        }
    }

    /**
     * Create Lead in CRM database and send final confirmation on WhatsApp
     */
    protected function createCrmLead(WhatsAppConfiguration $config, string $senderNumber, WhatsAppChatSession $session, array $collected, ?string $messageId = null): void
    {
        try {
            $isB2b = ($session->category === 'b2b');

            $contactPerson = $isB2b
                ? ($collected['contact_person'] ?? ($session->sender_name ?: 'Valued Business Client'))
                : ($collected['name'] ?? ($session->sender_name ?: 'Valued Customer'));

            $companyName = $isB2b ? ($collected['company_name'] ?? null) : null;
            $companyEmail = $collected['company_email'] ?? null;
            $contactEmail = $collected['email'] ?? $companyEmail;
            $gstin = $isB2b ? ($collected['gstin'] ?? null) : null;
            $city = $collected['city'] ?? null;
            $requirement = $collected['requirement'] ?? ($collected['product'] ?? 'General WhatsApp Enquiry');

            $lead = Lead::create([
                'tenant_id'      => $config->tenant_id,
                'company_id'     => $config->company_id,
                'branch_id'      => $config->branch_id,
                'contact_person' => $contactPerson,
                'company_name'   => $companyName,
                'company_email'  => $companyEmail,
                'email'          => $contactEmail,
                'gstin'          => $gstin,
                'phone'          => $senderNumber,
                'company_phone'  => $senderNumber,
                'lead_type'      => $isB2b ? 'B2B' : 'B2C',
                'requirement'    => $requirement,
                'city'           => $city,
                'source'         => 'WhatsApp Bot',
                'status'         => 'New',
            ]);

            $leadNumber = $lead->lead_number ?? ('LD-' . $lead->id);

            if ($isB2b) {
                $confirmMsg = "🎉 Thank you! Your B2B Commercial Enquiry (**#{$leadNumber}**) has been successfully registered.\n\n"
                            . "🏢 **Company:** " . ($companyName ?: 'N/A') . "\n"
                            . "👤 **Contact Person:** " . $contactPerson . "\n"
                            . "📧 **Email:** " . ($contactEmail ?: 'N/A') . "\n"
                            . "🧾 **GSTIN:** " . ($gstin ?: 'N/A') . "\n"
                            . "📍 **Location:** " . ($city ?: 'N/A') . "\n"
                            . "📋 **Requirement:** " . $requirement . "\n\n"
                            . "Our Corporate Sales Team will review your requirements and get back to you shortly with a commercial quotation.";
            } else {
                $confirmMsg = "🎉 Thank you! Your Enquiry (**#{$leadNumber}**) has been successfully registered.\n\n"
                            . "👤 **Name:** " . $contactPerson . "\n"
                            . "📧 **Email:** " . ($contactEmail ?: 'N/A') . "\n"
                            . "📍 **Location:** " . ($city ?: 'N/A') . "\n"
                            . "🛍️ **Requirement:** " . $requirement . "\n\n"
                            . "Our Customer Support Executive will share product details and pricing with you shortly!";
            }

            $this->waService->sendMessage($senderNumber, $confirmMsg, $messageId);
        } catch (\Throwable $e) {
            Log::error('Failed to auto-create Lead from WhatsApp Bot:', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            $this->waService->sendMessage($senderNumber, "Thank you! Your information has been received. Our team will contact you shortly.", $messageId);
        }
    }
}
