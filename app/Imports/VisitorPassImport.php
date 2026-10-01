<?php

namespace App\Imports;

use App\Domains\Visitor\Models\Visitor;
use App\Domains\Visitor\Models\VisitorPass;
use App\Domains\Visitor\Services\VisitorService;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;

class VisitorPassImport implements ToCollection, WithHeadingRow, SkipsEmptyRows
{
    public int $totalCount = 0;
    public int $successCount = 0;
    public int $failedCount = 0;
    public array $failedRows = [];

    public function __construct(
        private readonly VisitorService $visitorService
    ) {}

    public function collection(Collection $rows)
    {
        [$tenantId, $companyId, $branchId] = $this->visitorService->resolveTenantContext();

        foreach ($rows as $index => $row) {
            $this->totalCount++;
            $rowArray = $row->toArray();

            // Extract fields with alias normalization
            $fullName = trim((string)($rowArray['visitor_name'] ?? ($rowArray['name'] ?? ($rowArray['full_name'] ?? ''))));
            $phone = trim((string)($rowArray['phone_number'] ?? ($rowArray['phone'] ?? ($rowArray['mobile'] ?? ''))));
            $email = trim((string)($rowArray['email_address'] ?? ($rowArray['email'] ?? '')));
            $company = trim((string)($rowArray['company_org'] ?? ($rowArray['company'] ?? ($rowArray['company_name'] ?? ''))));
            $designation = trim((string)($rowArray['designation'] ?? ($rowArray['role'] ?? '')));
            $idProofType = trim((string)($rowArray['id_proof_type'] ?? ($rowArray['id_type'] ?? '')));
            $idProofNumber = trim((string)($rowArray['id_proof_number'] ?? ($rowArray['id_number'] ?? '')));
            $hostEmail = trim((string)($rowArray['host_employee_email'] ?? ($rowArray['host_email'] ?? ($rowArray['host'] ?? ''))));
            $purpose = trim((string)($rowArray['purpose_of_visit'] ?? ($rowArray['purpose'] ?? 'Meeting')));
            $gateNumber = trim((string)($rowArray['gate_number'] ?? ($rowArray['gate'] ?? 'Main Gate 1')));
            $entryType = trim((string)($rowArray['entry_type'] ?? 'Walk-in'));
            $feeAmount = isset($rowArray['pass_fee']) && is_numeric($rowArray['pass_fee']) ? (float)$rowArray['pass_fee'] : (isset($rowArray['fee_amount']) && is_numeric($rowArray['fee_amount']) ? (float)$rowArray['fee_amount'] : 0.00);
            $expectedArrivalRaw = trim((string)($rowArray['expected_arrival_yyyy_mm_dd_hh_mm'] ?? ($rowArray['expected_arrival'] ?? ($rowArray['arrival_time'] ?? ''))));
            $notes = trim((string)($rowArray['security_notes'] ?? ($rowArray['notes'] ?? ($rowArray['remarks'] ?? ''))));

            // Required field checks
            if (empty($fullName)) {
                $this->failedCount++;
                $this->failedRows[] = [
                    'row'    => $index + 2,
                    'reason' => 'Visitor Name is required.',
                    'data'   => $rowArray,
                ];
                continue;
            }

            if (empty($phone)) {
                $this->failedCount++;
                $this->failedRows[] = [
                    'row'    => $index + 2,
                    'reason' => 'Phone Number is required.',
                    'data'   => $rowArray,
                ];
                continue;
            }

            try {
                // Find or create Visitor profile
                $visitor = Visitor::firstOrNew([
                    'tenant_id' => $tenantId,
                    'phone'     => $phone,
                ]);

                $visitor->company_id = $companyId;
                $visitor->branch_id = $branchId;
                $visitor->full_name = $fullName;
                if (!empty($email)) $visitor->email = $email;
                if (!empty($company)) $visitor->company_name = $company;
                if (!empty($designation)) $visitor->designation = $designation;
                if (!empty($idProofType)) $visitor->id_proof_type = $idProofType;
                if (!empty($idProofNumber)) $visitor->id_proof_number = $idProofNumber;
                $visitor->save();

                // Resolve Host user ID if email is provided
                $hostUserId = null;
                if (!empty($hostEmail)) {
                    $host = User::where('tenant_id', $tenantId)
                        ->where(function($q) use ($hostEmail) {
                            $q->where('email', $hostEmail)->orWhere('name', 'like', "%{$hostEmail}%");
                        })
                        ->first();
                    if ($host) {
                        $hostUserId = $host->id;
                    }
                }

                // Parse Expected Arrival Date
                $expectedArrival = now();
                if (!empty($expectedArrivalRaw)) {
                    try {
                        $expectedArrival = Carbon::parse($expectedArrivalRaw);
                    } catch (\Exception) {
                        $expectedArrival = now();
                    }
                }

                // Generate Pass
                $passNumber = $this->visitorService->generatePassNumber($tenantId);
                $qrToken = 'QR-' . Str::uuid()->toString();

                VisitorPass::create([
                    'tenant_id'           => $tenantId,
                    'company_id'          => $companyId,
                    'branch_id'           => $branchId,
                    'pass_number'         => $passNumber,
                    'visitor_id'          => $visitor->id,
                    'host_user_id'        => $hostUserId,
                    'purpose'             => !empty($purpose) ? $purpose : 'Meeting',
                    'entry_type'          => !empty($entryType) ? $entryType : 'Walk-in',
                    'status'              => 'Expected',
                    'expected_arrival_at' => $expectedArrival,
                    'qr_token'            => $qrToken,
                    'gate_number'         => !empty($gateNumber) ? $gateNumber : 'Gate 1',
                    'fee_amount'          => $feeAmount,
                    'notes'               => !empty($notes) ? $notes : null,
                ]);

                $this->successCount++;
            } catch (\Exception $e) {
                $this->failedCount++;
                $this->failedRows[] = [
                    'row'    => $index + 2,
                    'reason' => $e->getMessage(),
                    'data'   => $rowArray,
                ];
            }
        }
    }
}
