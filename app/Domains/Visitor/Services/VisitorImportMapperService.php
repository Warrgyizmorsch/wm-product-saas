<?php

namespace App\Domains\Visitor\Services;

use App\Domains\Visitor\Models\Visitor;
use App\Domains\Visitor\Models\VisitorPass;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class VisitorImportMapperService
{
    public const ERP_FIELDS = [
        'full_name' => [
            'label' => 'Visitor Full Name *',
            'required' => true,
            'description' => 'Full name of the visitor / guest',
            'aliases' => ['visitor_name', 'full_name', 'name', 'visitor', 'guest_name', 'visitor name', 'full name', 'person_name']
        ],
        'phone' => [
            'label' => 'Phone / Mobile Number *',
            'required' => true,
            'description' => 'Primary mobile or contact number for lookup',
            'aliases' => ['phone', 'mobile', 'phone_number', 'mobile_no', 'phone_no', 'contact_number', 'phone number', 'mobile number', 'cell']
        ],
        'email' => [
            'label' => 'Email Address',
            'required' => false,
            'description' => 'Visitor email for pass QR / notification',
            'aliases' => ['email', 'email_address', 'mail', 'visitor_email', 'email address', 'e-mail']
        ],
        'company_name' => [
            'label' => 'Company / Organization',
            'required' => false,
            'description' => 'Visitor company or vendor organization',
            'aliases' => ['company_name', 'company', 'organization', 'org', 'company / org', 'org_name', 'business_name', 'vendor']
        ],
        'designation' => [
            'label' => 'Designation / Role',
            'required' => false,
            'description' => 'Job title or role (e.g. Consultant, Auditor)',
            'aliases' => ['designation', 'job_title', 'title', 'role', 'position']
        ],
        'id_proof_type' => [
            'label' => 'ID Proof Type',
            'required' => false,
            'description' => 'Aadhaar Card, PAN Card, Driving License, Passport, Voter ID',
            'aliases' => ['id_proof_type', 'id_type', 'id proof', 'id proof type', 'identity_proof', 'id_card']
        ],
        'id_proof_number' => [
            'label' => 'ID Proof Number',
            'required' => false,
            'description' => 'Unique ID proof number',
            'aliases' => ['id_proof_number', 'id_number', 'id proof number', 'id_no', 'identity_number', 'document_number']
        ],
        'host_email' => [
            'label' => 'Host (Employee Email / Name)',
            'required' => false,
            'description' => 'Host employee email or name to assign meeting',
            'aliases' => ['host_employee_email', 'host_email', 'host', 'employee_email', 'host employee', 'host_name', 'staff_email']
        ],
        'purpose' => [
            'label' => 'Purpose of Visit',
            'required' => false,
            'description' => 'Meeting, Interview, Vendor, Delivery, Audit, Personal',
            'aliases' => ['purpose_of_visit', 'purpose', 'visit_purpose', 'reason', 'visit_reason', 'purpose of visit']
        ],
        'gate_number' => [
            'label' => 'Gate / Entry Point',
            'required' => false,
            'description' => 'Main Gate 1, Gate 2, VIP Reception, etc.',
            'aliases' => ['gate_number', 'gate', 'entry_point', 'gate number', 'entry_gate']
        ],
        'entry_type' => [
            'label' => 'Entry Type',
            'required' => false,
            'description' => 'Walk-in, Pre-Invite, Kiosk',
            'aliases' => ['entry_type', 'pass_type', 'entry type', 'type']
        ],
        'fee_amount' => [
            'label' => 'Pass Fee / Charge ($)',
            'required' => false,
            'description' => 'Pass issuance charge if applicable',
            'aliases' => ['pass_fee', 'fee_amount', 'fee', 'charge', 'amount', 'pass charge']
        ],
        'expected_arrival_at' => [
            'label' => 'Expected Arrival Date & Time',
            'required' => false,
            'description' => 'Scheduled arrival timestamp (YYYY-MM-DD HH:MM)',
            'aliases' => ['expected_arrival_at', 'expected_arrival', 'arrival_time', 'expected_date', 'scheduled_at', 'expected arrival']
        ],
        'notes' => [
            'label' => 'Security Notes / Remarks',
            'required' => false,
            'description' => 'Vehicle number, luggage or security comments',
            'aliases' => ['security_notes', 'notes', 'remarks', 'comments', 'instructions', 'vehicle_number']
        ],
    ];

    /**
     * Parse uploaded spreadsheet file, extract headers, sample data rows, and build auto-mapping.
     */
    public function parseFile(UploadedFile $file, int $tenantId): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, ['xlsx', 'xls', 'csv', 'txt'])) {
            throw new \Exception('Invalid file type. Supported formats: .xlsx, .xls, .csv');
        }

        // Store file in temp directory for multi-step processing
        $token = 'visitor_import_' . Str::random(32);
        $tempPath = $file->storeAs('temp_imports', "{$token}.{$extension}", 'local');

        $fullPath = Storage::disk('local')->path($tempPath);

        $spreadsheet = IOFactory::load($fullPath);
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray(null, true, true, true);

        if (empty($rows)) {
            Storage::disk('local')->delete($tempPath);
            throw new \Exception('Uploaded spreadsheet is completely empty.');
        }

        // Find header row (first non-empty row)
        $headerRow = null;
        $headerRowIndex = 1;
        foreach ($rows as $rIndex => $rData) {
            $filtered = array_filter($rData, fn($v) => !is_null($v) && trim((string)$v) !== '');
            if (count($filtered) >= 2) {
                $headerRow = $rData;
                $headerRowIndex = $rIndex;
                break;
            }
        }

        if (!$headerRow) {
            Storage::disk('local')->delete($tempPath);
            throw new \Exception('No valid header row found in spreadsheet.');
        }

        // Map column letters to sanitized header names
        $fileHeaders = [];
        foreach ($headerRow as $colLetter => $headerName) {
            if (!empty(trim((string)$headerName))) {
                $fileHeaders[$colLetter] = trim((string)$headerName);
            }
        }

        // Extract first 5 data rows for real-time visual preview
        $previewRows = [];
        $dataRowCounter = 0;
        foreach ($rows as $rIndex => $rData) {
            if ($rIndex <= $headerRowIndex) continue;
            $filtered = array_filter($rData, fn($v) => !is_null($v) && trim((string)$v) !== '');
            if (empty($filtered)) continue;

            $rowSample = [];
            foreach ($fileHeaders as $colLetter => $headerName) {
                $rowSample[$colLetter] = isset($rData[$colLetter]) ? trim((string)$rData[$colLetter]) : '';
            }
            $previewRows[] = $rowSample;
            $dataRowCounter++;
            if ($dataRowCounter >= 5) break;
        }

        $totalDataRows = max(0, count($rows) - $headerRowIndex);

        // Auto-match file columns with ERP Visitor fields using aliases
        $autoMapping = [];
        foreach (self::ERP_FIELDS as $erpKey => $erpDef) {
            $matchedCol = null;
            $aliases = array_map('strtolower', $erpDef['aliases'] ?? []);

            foreach ($fileHeaders as $colLetter => $headerName) {
                $cleanedHeader = strtolower(trim(preg_replace('/[^a-zA-Z0-9]/', '_', $headerName), '_'));
                $rawLowerHeader = strtolower(trim($headerName));

                if ($cleanedHeader === $erpKey || in_array($cleanedHeader, $aliases, true) || in_array($rawLowerHeader, $aliases, true)) {
                    $matchedCol = $colLetter;
                    break;
                }
            }

            $autoMapping[$erpKey] = $matchedCol;
        }

        return [
            'file_token'       => $token,
            'file_name'        => $file->getClientOriginalName(),
            'file_size'        => $file->getSize(),
            'total_rows'       => $totalDataRows,
            'header_row_index' => $headerRowIndex,
            'file_headers'     => $fileHeaders,
            'preview_rows'     => $previewRows,
            'erp_fields'       => self::ERP_FIELDS,
            'auto_mapping'     => $autoMapping,
        ];
    }

    /**
     * Process mapped import rows (Dry-run test or Live DB insertion).
     */
    public function processImport(
        string $fileToken,
        array $mapping,
        array $options,
        int $tenantId,
        int $companyId,
        ?int $branchId = null,
        bool $isDryRun = false
    ): array {
        // Find stored temp file
        $files = Storage::disk('local')->files('temp_imports');
        $matchedFile = null;
        foreach ($files as $f) {
            if (str_contains($f, $fileToken)) {
                $matchedFile = $f;
                break;
            }
        }

        if (!$matchedFile || !Storage::disk('local')->exists($matchedFile)) {
            throw new \Exception('Import session expired or file not found. Please upload again.');
        }

        $fullPath = Storage::disk('local')->path($matchedFile);
        $spreadsheet = IOFactory::load($fullPath);
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray(null, true, true, true);

        // Find header row index
        $headerRowIndex = (int)($options['header_row_index'] ?? 1);

        $defaultHostId = !empty($options['default_host_id']) ? (int)$options['default_host_id'] : null;
        $defaultPurpose = !empty($options['default_purpose']) ? trim($options['default_purpose']) : 'Meeting';
        $defaultGate = !empty($options['default_gate']) ? trim($options['default_gate']) : 'Main Gate 1';
        $defaultEntryType = !empty($options['default_entry_type']) ? trim($options['default_entry_type']) : 'Walk-in';
        $updateExisting = !empty($options['update_existing']);

        $totalProcessed = 0;
        $importedCount = 0;
        $updatedCount = 0;
        $errors = [];

        $visitorService = app(VisitorService::class);

        DB::beginTransaction();
        try {
            foreach ($rows as $rIndex => $rData) {
                if ($rIndex <= $headerRowIndex) continue;
                $filtered = array_filter($rData, fn($v) => !is_null($v) && trim((string)$v) !== '');
                if (empty($filtered)) continue;

                $totalProcessed++;

                // Map row data using selected mapping
                $rowPayload = [];
                foreach (self::ERP_FIELDS as $erpKey => $erpDef) {
                    $mappedColLetter = $mapping[$erpKey] ?? null;
                    if (!empty($mappedColLetter) && isset($rData[$mappedColLetter])) {
                        $val = trim((string)$rData[$mappedColLetter]);
                        $rowPayload[$erpKey] = $val !== '' ? $val : null;
                    } else {
                        $rowPayload[$erpKey] = null;
                    }
                }

                $fullName = $rowPayload['full_name'] ?? null;
                $phone = $rowPayload['phone'] ?? null;

                if (empty($fullName)) {
                    $errors[] = "Row #{$rIndex}: Visitor Full Name is missing.";
                    continue;
                }

                if (empty($phone)) {
                    $errors[] = "Row #{$rIndex}: Phone Number is missing for visitor '{$fullName}'.";
                    continue;
                }

                if ($isDryRun) {
                    $importedCount++;
                    continue;
                }

                // Resolve Host User ID
                $hostId = $defaultHostId;
                if (!empty($rowPayload['host_email'])) {
                    $hInput = trim($rowPayload['host_email']);
                    $host = User::where('tenant_id', $tenantId)
                        ->where(function ($q) use ($hInput) {
                            $q->where('email', $hInput)->orWhere('name', 'like', "%{$hInput}%");
                        })
                        ->first();
                    if ($host) {
                        $hostId = $host->id;
                    }
                }

                // 1. Find or create Visitor
                $visitor = Visitor::firstOrNew([
                    'tenant_id' => $tenantId,
                    'phone'     => $phone,
                ]);

                $isNewVisitor = !$visitor->exists;

                if ($isNewVisitor || $updateExisting) {
                    $visitor->company_id = $companyId;
                    $visitor->branch_id = $branchId;
                    $visitor->full_name = $fullName;
                    if (!empty($rowPayload['email'])) $visitor->email = $rowPayload['email'];
                    if (!empty($rowPayload['company_name'])) $visitor->company_name = $rowPayload['company_name'];
                    if (!empty($rowPayload['designation'])) $visitor->designation = $rowPayload['designation'];
                    if (!empty($rowPayload['id_proof_type'])) $visitor->id_proof_type = $rowPayload['id_proof_type'];
                    if (!empty($rowPayload['id_proof_number'])) $visitor->id_proof_number = $rowPayload['id_proof_number'];
                    $visitor->save();
                }

                // Parse Expected Arrival Date
                $expectedArrival = now();
                if (!empty($rowPayload['expected_arrival_at'])) {
                    try {
                        $expectedArrival = Carbon::parse($rowPayload['expected_arrival_at']);
                    } catch (\Exception) {
                        $expectedArrival = now();
                    }
                }

                // Parse Fee Amount
                $feeAmount = 0.00;
                if (!empty($rowPayload['fee_amount']) && is_numeric($rowPayload['fee_amount'])) {
                    $feeAmount = (float)$rowPayload['fee_amount'];
                }

                // 2. Create Pass
                $passNumber = $visitorService->generatePassNumber($tenantId);
                $qrToken = 'QR-' . Str::uuid()->toString();

                VisitorPass::create([
                    'tenant_id'           => $tenantId,
                    'company_id'          => $companyId,
                    'branch_id'           => $branchId,
                    'pass_number'         => $passNumber,
                    'visitor_id'          => $visitor->id,
                    'host_user_id'        => $hostId,
                    'purpose'             => $rowPayload['purpose'] ?: $defaultPurpose,
                    'entry_type'          => $rowPayload['entry_type'] ?: $defaultEntryType,
                    'status'              => 'Expected',
                    'expected_arrival_at' => $expectedArrival,
                    'qr_token'            => $qrToken,
                    'gate_number'         => $rowPayload['gate_number'] ?: $defaultGate,
                    'fee_amount'          => $feeAmount,
                    'notes'               => $rowPayload['notes'] ?? null,
                ]);

                if ($isNewVisitor) {
                    $importedCount++;
                } else {
                    $updatedCount++;
                }
            }

            if ($isDryRun) {
                DB::rollBack();
            } else {
                DB::commit();
                // Clean up temp file after successful import
                Storage::disk('local')->delete($matchedFile);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return [
            'success'          => true,
            'is_dry_run'       => $isDryRun,
            'total_processed'  => $totalProcessed,
            'imported_count'   => $importedCount,
            'updated_count'    => $updatedCount,
            'errors_count'     => count($errors),
            'errors'           => array_slice($errors, 0, 30),
            'message'          => $isDryRun 
                ? "Test import passed: {$importedCount} records validated successfully with " . count($errors) . " errors."
                : "Import completed! Successfully issued {$importedCount} new passes and updated {$updatedCount} returning visitors.",
        ];
    }
}
