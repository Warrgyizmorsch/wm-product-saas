<?php

namespace App\Domains\CRM\Services;

use App\Domains\CRM\Models\Lead;
use App\Domains\CRM\Models\LeadStatus;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class LeadImportMapperService
{
    /**
     * Standard ERP CRM Lead fields available for mapping.
     */
    public const ERP_FIELDS = [
        'contact_person' => [
            'label' => 'Contact Person / Lead Name',
            'required' => false,
            'description' => 'Full name of the contact person or prospect',
            'aliases' => ['contact_person', 'contact person', 'contact_name', 'contact name', 'name', 'full_name', 'lead_name', 'lead name', 'person_name', 'client_name', 'prospect_name', 'customer_name']
        ],
        'company_name' => [
            'label' => 'Company / Organization Name',
            'required' => false,
            'description' => 'Business, organization or brand name',
            'aliases' => ['company_name', 'company name', 'company', 'organization', 'organization_name', 'org_name', 'business_name', 'firm_name', 'account_name', 'enterprise']
        ],
        'phone' => [
            'label' => 'Primary Phone / Mobile',
            'required' => false,
            'description' => 'Mobile number or direct contact phone',
            'aliases' => ['phone', 'mobile', 'mobile_no', 'phone_no', 'contact_phone', 'contact_no', 'cell', 'whatsapp_number', 'mobile number', 'phone number', 'telephone']
        ],
        'email' => [
            'label' => 'Primary Email Address',
            'required' => false,
            'description' => 'Contact or lead email address',
            'aliases' => ['email', 'email_address', 'contact_email', 'mail', 'email id', 'e-mail', 'personal_email']
        ],
        'company_phone' => [
            'label' => 'Company / Landline Phone',
            'required' => false,
            'description' => 'Official office phone or landline',
            'aliases' => ['company_phone', 'office_phone', 'landline', 'work_phone', 'company phone', 'office telephone']
        ],
        'company_email' => [
            'label' => 'Company Official Email',
            'required' => false,
            'description' => 'Official business email (e.g. info@company.com)',
            'aliases' => ['company_email', 'official_email', 'work_email', 'corp_email', 'company email', 'business_email']
        ],
        'designation' => [
            'label' => 'Designation / Job Title',
            'required' => false,
            'description' => 'Role or position (e.g. Director, Purchase Manager)',
            'aliases' => ['designation', 'job_title', 'title', 'role', 'position', 'designation/role']
        ],
        'lead_type' => [
            'label' => 'Lead Type (B2B / B2C)',
            'required' => false,
            'description' => 'b2b, b2c, corporate, individual',
            'aliases' => ['lead_type', 'type', 'client_type', 'customer_type', 'nature']
        ],
        'source' => [
            'label' => 'Lead Source',
            'required' => false,
            'description' => 'Website, WhatsApp, IndiaMart, Referral, Cold Call, Facebook, etc.',
            'aliases' => ['source', 'lead_source', 'channel', 'campaign_source', 'origin', 'inquiry_source', 'medium']
        ],
        'status' => [
            'label' => 'Lead Status',
            'required' => false,
            'description' => 'New, Contacted, Qualified, In Progress, Won, Lost, etc.',
            'aliases' => ['status', 'lead_status', 'stage', 'lead_stage']
        ],
        'priority' => [
            'label' => 'Priority',
            'required' => false,
            'description' => 'Low, Medium, High, Urgent',
            'aliases' => ['priority', 'urgency', 'importance', 'lead_priority', 'severity']
        ],
        'expected_amount' => [
            'label' => 'Expected Amount / Deal Budget',
            'required' => false,
            'description' => 'Anticipated deal value or budget',
            'aliases' => ['expected_amount', 'amount', 'budget', 'deal_value', 'value', 'expected_revenue', 'opportunity_amount', 'deal_size', 'est_budget']
        ],
        'expected_sale_date' => [
            'label' => 'Expected Closing Date',
            'required' => false,
            'description' => 'Target closing date (YYYY-MM-DD)',
            'aliases' => ['expected_sale_date', 'close_date', 'expected_closing', 'target_date', 'closing_date']
        ],
        'next_followup_date' => [
            'label' => 'Next Follow-up Date & Time',
            'required' => false,
            'description' => 'Scheduled reminder date/time for followup',
            'aliases' => ['next_followup_date', 'followup_date', 'next_followup', 'next_call_date', 'reminder_date']
        ],
        'requirement' => [
            'label' => 'Requirement / Notes / Inquiry Details',
            'required' => false,
            'description' => 'Customer inquiry description, remarks or product interest',
            'aliases' => ['requirement', 'notes', 'remarks', 'description', 'inquiry', 'details', 'comment', 'message', 'query']
        ],
        'gstin' => [
            'label' => 'GSTIN / Tax ID',
            'required' => false,
            'description' => 'GST Registration Number',
            'aliases' => ['gstin', 'gst_no', 'gst_number', 'gst', 'tax_id', 'vat_no']
        ],
        'industry_type' => [
            'label' => 'Industry / Sector',
            'required' => false,
            'description' => 'Manufacturing, Retail, IT, Construction, etc.',
            'aliases' => ['industry_type', 'industry', 'sector', 'business_type', 'domain']
        ],
        'segment' => [
            'label' => 'Market Segment',
            'required' => false,
            'description' => 'SME, Mid-Market, Enterprise, SMB',
            'aliases' => ['segment', 'market_segment', 'tier', 'customer_segment']
        ],
        'city' => [
            'label' => 'City',
            'required' => false,
            'description' => 'City name',
            'aliases' => ['city', 'town', 'district']
        ],
        'state' => [
            'label' => 'State / Province',
            'required' => false,
            'description' => 'State or province name',
            'aliases' => ['state', 'province', 'region']
        ],
        'country' => [
            'label' => 'Country',
            'required' => false,
            'description' => 'e.g. India, USA, UAE',
            'aliases' => ['country', 'nation']
        ],
        'address' => [
            'label' => 'Full Address / Street',
            'required' => false,
            'description' => 'Street address, building, landmark',
            'aliases' => ['address', 'street', 'location', 'office_address', 'full_address']
        ],
        'lead_owner' => [
            'label' => 'Assigned Salesperson / Owner',
            'required' => false,
            'description' => 'Sales rep name or email to whom lead is assigned',
            'aliases' => ['lead_owner', 'assigned_to', 'salesperson', 'sales_rep', 'owner', 'executive', 'agent']
        ],
        'call_date' => [
            'label' => 'Inquiry / Call Date',
            'required' => false,
            'description' => 'Initial inquiry or call timestamp',
            'aliases' => ['call_date', 'inquiry_date', 'lead_date', 'date', 'created_date', 'entry_date']
        ],
    ];

    /**
     * Parse uploaded file, save to temp storage, and return detected headers + suggested mapping.
     */
    public function parseFile(UploadedFile $file, int $tenantId): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $token = 'import_lead_' . Str::random(24) . '.' . $extension;
        
        $tempPath = $file->storeAs('temp-imports', $token, 'local');
        $fullPath = Storage::disk('local')->path($tempPath);

        // Read rows from spreadsheet
        $spreadsheet = IOFactory::load($fullPath);
        $worksheet = $spreadsheet->getActiveSheet();
        $rawRows = $worksheet->toArray();

        if (empty($rawRows) || count($rawRows) < 1) {
            throw new \Exception('Uploaded spreadsheet is empty.');
        }

        // Find header row (first non-empty row)
        $headerRowIndex = 0;
        $headers = [];
        foreach ($rawRows as $idx => $row) {
            $nonEmpty = array_filter($row, fn($cell) => $cell !== null && trim((string)$cell) !== '');
            if (count($nonEmpty) > 0) {
                $headerRowIndex = $idx;
                $headers = array_map(fn($h) => trim((string)$h), $row);
                break;
            }
        }

        if (empty($headers)) {
            throw new \Exception('Could not find a valid header row in the file.');
        }

        // Filter out empty trailing headers
        $filteredHeaders = [];
        foreach ($headers as $colIdx => $headerTitle) {
            if ($headerTitle !== '') {
                $filteredHeaders[$colIdx] = $headerTitle;
            } else {
                $filteredHeaders[$colIdx] = 'Column ' . ($colIdx + 1);
            }
        }

        // Extract 3-5 sample rows
        $sampleRows = [];
        $totalDataRows = 0;
        for ($i = $headerRowIndex + 1; $i < count($rawRows); $i++) {
            $row = $rawRows[$i];
            $nonEmpty = array_filter($row, fn($cell) => $cell !== null && trim((string)$cell) !== '');
            if (count($nonEmpty) === 0) {
                continue;
            }
            $totalDataRows++;

            if (count($sampleRows) < 5) {
                $sampleRow = [];
                foreach ($filteredHeaders as $colIdx => $headerTitle) {
                    $sampleRow[$headerTitle] = isset($row[$colIdx]) ? (string)$row[$colIdx] : '';
                }
                $sampleRows[] = $sampleRow;
            }
        }

        // Auto-match suggestions
        $suggestedMapping = $this->generateSmartMapping($filteredHeaders);

        return [
            'file_token' => $token,
            'original_name' => $file->getClientOriginalName(),
            'total_rows' => $totalDataRows,
            'headers' => array_values($filteredHeaders),
            'sample_rows' => $sampleRows,
            'fields_schema' => self::ERP_FIELDS,
            'suggested_mapping' => $suggestedMapping,
        ];
    }

    /**
     * Smart fuzzy matcher between file headers and CRM Lead fields.
     */
    protected function generateSmartMapping(array $fileHeaders): array
    {
        $mapping = [];
        $usedHeaders = [];

        foreach (self::ERP_FIELDS as $fieldKey => $fieldMeta) {
            $bestMatch = null;
            $highestScore = 0;

            foreach ($fileHeaders as $colIdx => $header) {
                $normalizedHeader = strtolower(trim(preg_replace('/[^a-zA-Z0-9]/', '', $header)));

                foreach ($fieldMeta['aliases'] as $alias) {
                    $normalizedAlias = strtolower(trim(preg_replace('/[^a-zA-Z0-9]/', '', $alias)));

                    // Exact match
                    if ($normalizedHeader === $normalizedAlias) {
                        $bestMatch = $header;
                        $highestScore = 100;
                        break 2;
                    }

                    // Partial match
                    if (str_contains($normalizedHeader, $normalizedAlias) || str_contains($normalizedAlias, $normalizedHeader)) {
                        $score = 80;
                        if ($score > $highestScore) {
                            $bestMatch = $header;
                            $highestScore = $score;
                        }
                    }

                    // Similarity
                    similar_text($normalizedHeader, $normalizedAlias, $percent);
                    if ($percent > 75 && $percent > $highestScore) {
                        $bestMatch = $header;
                        $highestScore = $percent;
                    }
                }
            }

            if ($bestMatch && !in_array($bestMatch, $usedHeaders, true)) {
                $mapping[$fieldKey] = $bestMatch;
                $usedHeaders[] = $bestMatch;
            } else {
                $mapping[$fieldKey] = '';
            }
        }

        return $mapping;
    }

    /**
     * Process actual import with column mappings and options.
     */
    public function processImport(
        string $fileToken,
        array $mapping,
        array $options,
        int $tenantId,
        ?int $userId = null,
        ?int $companyId = null,
        ?int $branchId = null
    ): array {
        $filePath = 'temp-imports/' . $fileToken;
        if (!Storage::disk('local')->exists($filePath)) {
            throw new \Exception('Uploaded file session expired. Please upload the file again.');
        }

        $fullPath = Storage::disk('local')->path($filePath);
        $spreadsheet = IOFactory::load($fullPath);
        $worksheet = $spreadsheet->getActiveSheet();
        $rawRows = $worksheet->toArray();

        // Options
        $defaultStatus = !empty($options['default_status']) ? trim($options['default_status']) : 'New';
        $defaultSource = !empty($options['default_source']) ? trim($options['default_source']) : 'Direct';
        $defaultOwnerId = !empty($options['default_owner_id']) ? (int)$options['default_owner_id'] : ($userId ?: null);
        $updateExisting = !empty($options['update_existing']);
        $dryRun = !empty($options['dry_run']);
        
        // Resolve tenant, company, and branch context
        $resolvedCompanyId = $companyId ?? (company_id() ?? auth()->user()?->company_id ?? \App\Models\Company::where('tenant_id', $tenantId)->value('id') ?? 1);
        $resolvedBranchId = $branchId ?? (branch_id() ?? auth()->user()?->branch_id ?? \App\Models\Branch::where('company_id', $resolvedCompanyId)->value('id') ?? null);

        // Locate header row & map column names to indexes
        $headerMap = [];
        $headerRowIndex = 0;
        foreach ($rawRows as $idx => $row) {
            $nonEmpty = array_filter($row, fn($c) => $c !== null && trim((string)$c) !== '');
            if (count($nonEmpty) > 0) {
                $headerRowIndex = $idx;
                foreach ($row as $colIdx => $colName) {
                    $trimmed = trim((string)$colName);
                    if ($trimmed !== '') {
                        $headerMap[$trimmed] = $colIdx;
                    }
                }
                break;
            }
        }

        // Cache users for owner resolution scoped to tenant
        $allUsers = User::query()
            ->where(function($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id');
            })
            ->select(['id', 'name', 'email'])
            ->get();

        $userMap = [];
        foreach ($allUsers as $u) {
            $userMap[strtolower(trim($u->name))] = $u->id;
            $userMap[strtolower(trim($u->email))] = $u->id;
            $userMap[(string)$u->id] = $u->id;
        }

        $resolveOwner = function (?string $rawVal) use ($userMap, $defaultOwnerId): ?int {
            if ($rawVal === null || trim($rawVal) === '') {
                return $defaultOwnerId;
            }
            $clean = strtolower(trim($rawVal));
            return $userMap[$clean] ?? $defaultOwnerId;
        };

        // Cache existing leads strictly within current tenant and company
        $existingLeadsQuery = Lead::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at');

        if ($resolvedCompanyId) {
            $existingLeadsQuery->where('company_id', $resolvedCompanyId);
        }

        $existingLeads = $existingLeadsQuery->select(['id', 'phone', 'email', 'company_name'])->get();

        $phoneMap = [];
        $emailMap = [];
        $companyMap = [];
        foreach ($existingLeads as $l) {
            if (!empty($l->phone)) {
                $cleanPhone = preg_replace('/[^0-9]/', '', $l->phone);
                if (strlen($cleanPhone) >= 7) {
                    $phoneMap[$cleanPhone] = $l->id;
                }
            }
            if (!empty($l->email)) {
                $emailMap[strtolower(trim($l->email))] = $l->id;
            }
            if (!empty($l->company_name)) {
                $companyMap[strtolower(trim($l->company_name))] = $l->id;
            }
        }

        $cleanDate = function (?string $val) {
            if ($val === null || trim($val) === '') return null;
            try {
                return Carbon::parse(trim($val));
            } catch (\Exception $e) {
                return null;
            }
        };

        $cleanNumeric = function (?string $val, float $default = 0.0): float {
            if ($val === null || $val === '') return $default;
            $clean = preg_replace('/[^0-9.-]/', '', $val);
            return is_numeric($clean) ? (float)$clean : $default;
        };

        $importedCount = 0;
        $updatedCount = 0;
        $skippedCount = 0;
        $errors = [];

        $getValue = function (array $row, string $fieldKey) use ($mapping, $headerMap) {
            if (empty($mapping[$fieldKey])) {
                return null;
            }
            $targetColName = $mapping[$fieldKey];
            if (!isset($headerMap[$targetColName])) {
                return null;
            }
            $colIdx = $headerMap[$targetColName];
            $val = $row[$colIdx] ?? null;
            return $val !== null ? trim((string)$val) : null;
        };

        if (!$dryRun) {
            DB::beginTransaction();
        }

        try {
            $year = date('Y');
            $currentCount = Lead::withoutGlobalScopes()->where('tenant_id', $tenantId)->whereYear('created_at', $year)->count();

            for ($i = $headerRowIndex + 1; $i < count($rawRows); $i++) {
                $rowNum = $i + 1;
                $row = $rawRows[$i];
                $nonEmpty = array_filter($row, fn($c) => $c !== null && trim((string)$c) !== '');
                if (count($nonEmpty) === 0) {
                    continue;
                }

                $contactPerson = $getValue($row, 'contact_person');
                $companyName = $getValue($row, 'company_name');
                $phone = $getValue($row, 'phone');
                $email = $getValue($row, 'email');

                // Validation: At least one identifier must exist
                if (empty($contactPerson) && empty($companyName) && empty($phone) && empty($email)) {
                    $errors[] = "Row #{$rowNum}: Skipped because no Contact Name, Company Name, Phone, or Email was provided.";
                    $skippedCount++;
                    continue;
                }

                $leadTypeRaw = strtolower(trim((string)$getValue($row, 'lead_type')));
                $leadType = !empty($companyName) ? 'b2b' : 'b2c';
                if (!empty($leadTypeRaw)) {
                    $leadType = str_contains($leadTypeRaw, 'b2b') || str_contains($leadTypeRaw, 'corp') ? 'b2b' : 'b2c';
                }

                // Lead source and status with defaults
                $source = $getValue($row, 'source') ?: $defaultSource;
                $status = $getValue($row, 'status') ?: $defaultStatus;
                $priority = $getValue($row, 'priority') ?: 'Medium';
                $ownerId = $resolveOwner($getValue($row, 'lead_owner'));

                $expectedAmount = $cleanNumeric($getValue($row, 'expected_amount'), 0.0);
                $expectedSaleDate = $cleanDate($getValue($row, 'expected_sale_date'));
                $nextFollowupDate = $cleanDate($getValue($row, 'next_followup_date'));
                $callDate = $cleanDate($getValue($row, 'call_date')) ?: now();

                // Find existing match
                $matchedId = null;
                $cleanPhoneDigits = $phone ? preg_replace('/[^0-9]/', '', $phone) : '';
                if ($cleanPhoneDigits && strlen($cleanPhoneDigits) >= 7 && isset($phoneMap[$cleanPhoneDigits])) {
                    $matchedId = $phoneMap[$cleanPhoneDigits];
                } elseif ($email && isset($emailMap[strtolower(trim($email))])) {
                    $matchedId = $emailMap[strtolower(trim($email))];
                } elseif ($companyName && isset($companyMap[strtolower(trim($companyName))])) {
                    $matchedId = $companyMap[strtolower(trim($companyName))];
                }

                $leadPayload = [
                    'tenant_id' => $tenantId,
                    'company_id' => $resolvedCompanyId,
                    'branch_id' => $resolvedBranchId,
                    'lead_owner_id' => $ownerId,
                    'company_name' => $companyName ?: null,
                    'company_email' => $getValue($row, 'company_email') ?: null,
                    'company_phone' => $getValue($row, 'company_phone') ?: null,
                    'contact_person' => $contactPerson ?: ($companyName ?: 'Prospective Client'),
                    'designation' => $getValue($row, 'designation') ?: null,
                    'email' => $email ?: null,
                    'phone' => $phone ?: null,
                    'lead_type' => $leadType,
                    'source' => $source,
                    'status' => $status,
                    'priority' => ucfirst(strtolower($priority)),
                    'expected_amount' => $expectedAmount,
                    'expected_sale_date' => $expectedSaleDate,
                    'next_followup_date' => $nextFollowupDate,
                    'call_date' => $callDate,
                    'requirement' => $getValue($row, 'requirement') ?: null,
                    'gstin' => $getValue($row, 'gstin') ?: null,
                    'industry_type' => $getValue($row, 'industry_type') ?: null,
                    'segment' => $getValue($row, 'segment') ?: 'Mid-Market',
                    'city' => $getValue($row, 'city') ?: null,
                    'state' => $getValue($row, 'state') ?: null,
                    'country' => $getValue($row, 'country') ?: 'India',
                    'address' => $getValue($row, 'address') ?: null,
                ];

                if (!$dryRun) {
                    if ($matchedId) {
                        if ($updateExisting) {
                            $existingObj = Lead::withoutGlobalScopes()->where('tenant_id', $tenantId)->find($matchedId);
                            if ($existingObj) {
                                $existingObj->update($leadPayload);
                                $updatedCount++;
                            } else {
                                $skippedCount++;
                            }
                        } else {
                            $skippedCount++;
                        }
                    } else {
                        $currentCount++;
                        $leadPayload['lead_number'] = 'LD-' . $year . '-' . str_pad((string)$currentCount, 4, '0', STR_PAD_LEFT);
                        $createdLead = Lead::create($leadPayload);
                        
                        // Register in memory map
                        if ($cleanPhoneDigits && strlen($cleanPhoneDigits) >= 7) {
                            $phoneMap[$cleanPhoneDigits] = $createdLead->id;
                        }
                        if ($email) {
                            $emailMap[strtolower(trim($email))] = $createdLead->id;
                        }
                        if ($companyName) {
                            $companyMap[strtolower(trim($companyName))] = $createdLead->id;
                        }

                        $importedCount++;
                    }
                } else {
                    // Dry run counting
                    if ($matchedId) {
                        if ($updateExisting) {
                            $updatedCount++;
                        } else {
                            $skippedCount++;
                        }
                    } else {
                        $importedCount++;
                    }
                }
            }

            if (!$dryRun) {
                DB::commit();
                Storage::disk('local')->delete($filePath);
            }

            return [
                'success' => true,
                'dry_run' => $dryRun,
                'imported_count' => $importedCount,
                'updated_count' => $updatedCount,
                'skipped_count' => $skippedCount,
                'total_processed' => $importedCount + $updatedCount + $skippedCount,
                'errors' => $errors,
            ];
        } catch (\Exception $e) {
            if (!$dryRun) {
                DB::rollBack();
            }
            throw $e;
        }
    }
}
