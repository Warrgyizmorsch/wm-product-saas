<?php

namespace App\Domains\CRM\Services;

use App\Domains\CRM\Models\Lead;
use App\Domains\CRM\Models\Customer;
use App\Domains\CRM\Models\LeadDocument;
use App\Domains\CRM\Models\LeadHistory;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class LeadService
{
    /**
     * Process Call Date & Time.
     */
    public function parseCallDateTime(array $validated): Carbon
    {
        if (isset($validated['call_date'])) {
            try {
                return Carbon::parse($validated['call_date']);
            } catch (\Exception $e) {
                return Carbon::now();
            }
        }

        try {
            $hour = intval($validated['call_date_hour'] ?? 0);
            if (($validated['call_date_ampm'] ?? '') === 'PM' && $hour < 12) {
                $hour += 12;
            } elseif (($validated['call_date_ampm'] ?? '') === 'AM' && $hour === 12) {
                $hour = 0;
            }

            $timeString = sprintf('%02d:%02d:00', $hour, intval($validated['call_date_minute'] ?? 0));
            return Carbon::parse(($validated['call_date_date'] ?? Carbon::now()->toDateString()) . ' ' . $timeString);
        } catch (\Exception $e) {
            return Carbon::now();
        }
    }

    /**
     * Process items array into product_items and product_ids.
     */
    public function processItems(array $rawItems, array $rawProductIds = []): array
    {
        $cleanItems = [];
        $cleanProductIds = [];

        foreach ($rawItems as $item) {
            $pId = !empty($item['product_id']) && $item['product_id'] !== '__ADD_NEW__' ? intval($item['product_id']) : null;
            $qty = !empty($item['quantity']) ? floatval($item['quantity']) : 1.0;
            if ($pId) {
                $cleanItems[] = [
                    'product_id' => $pId,
                    'quantity' => $qty > 0 ? $qty : 1.0,
                ];
                if (!in_array($pId, $cleanProductIds)) {
                    $cleanProductIds[] = $pId;
                }
            }
        }

        if (empty($cleanItems)) {
            foreach ($rawProductIds as $pId) {
                $intId = intval($pId);
                if ($intId) {
                    $cleanItems[] = ['product_id' => $intId, 'quantity' => 1.0];
                    $cleanProductIds[] = $intId;
                }
            }
        }

        return [
            'product_items' => $cleanItems,
            'product_ids' => $cleanProductIds,
        ];
    }

    /**
     * Store new lead and log events.
     */
    public function storeLead(array $validated, array $rawItems, array $rawProductIds): Lead
    {
        $callDateTime = $this->parseCallDateTime($validated);
        $processed = $this->processItems($rawItems, $rawProductIds);

        $leadData = [
            'call_date' => $callDateTime,
            'lead_owner_id' => $validated['lead_owner_id'] ?? null,
            'company_name' => $validated['company_name'],
            'contact_person' => $validated['contact_person'] ?? null,
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'requirement' => $validated['requirement'] ?? null,
            'expected_amount' => !empty($validated['expected_amount']) ? floatval($validated['expected_amount']) : 0.00,
            'expected_sale_date' => !empty($validated['expected_sale_date']) ? Carbon::parse($validated['expected_sale_date']) : null,
            'source' => $validated['source'] ?? 'Select an Option',
            'priority' => $validated['priority'] ?? 'Select an Option',
            'segment' => $validated['segment'] ?? 'Select an Option',
            'industry_type' => $validated['industry_type'] ?? null,
            'country' => $validated['country'] ?? null,
            'state' => $validated['state'] ?? null,
            'city' => $validated['city'] ?? null,
            'address' => $validated['address'] ?? null,
            'product_ids' => $processed['product_ids'],
            'product_items' => $processed['product_items'],
        ];

        $lead = Lead::create($leadData);

        LeadHistory::logEvent(
            $lead,
            'created',
            null,
            $lead->company_name,
            'Lead created with initial stage: ' . ($lead->status ?: 'New')
        );

        if ($lead->lead_owner_id) {
            $ownerName = User::find($lead->lead_owner_id)?->name ?: 'Unknown';
            LeadHistory::logEvent(
                $lead,
                'assigned',
                null,
                $ownerName,
                'Lead automatically assigned to lead owner: ' . $ownerName
            );
        }

        return $lead;
    }

    /**
     * Update existing lead and log events.
     */
    public function updateLead(Lead $lead, array $validated, array $rawItems, array $rawProductIds): bool
    {
        $callDateTime = $this->parseCallDateTime($validated);
        $processed = $this->processItems($rawItems, $rawProductIds);

        $leadData = [
            'call_date' => $callDateTime,
            'lead_owner_id' => $validated['lead_owner_id'] ?? null,
            'company_name' => $validated['company_name'],
            'contact_person' => $validated['contact_person'] ?? null,
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'requirement' => $validated['requirement'] ?? null,
            'expected_amount' => !empty($validated['expected_amount']) ? floatval($validated['expected_amount']) : 0.00,
            'expected_sale_date' => !empty($validated['expected_sale_date']) ? Carbon::parse($validated['expected_sale_date']) : null,
            'source' => $validated['source'] ?? 'Select an Option',
            'priority' => $validated['priority'] ?? 'Select an Option',
            'segment' => $validated['segment'] ?? 'Select an Option',
            'industry_type' => $validated['industry_type'] ?? null,
            'country' => $validated['country'] ?? null,
            'state' => $validated['state'] ?? null,
            'city' => $validated['city'] ?? null,
            'address' => $validated['address'] ?? null,
            'product_ids' => $processed['product_ids'],
            'product_items' => $processed['product_items'],
        ];

        $oldOwnerId = $lead->lead_owner_id;
        $updated = $lead->update($leadData);

        if ($oldOwnerId != $lead->lead_owner_id) {
            $oldOwnerName = $oldOwnerId ? (User::find($oldOwnerId)?->name ?: 'Unknown') : 'None';
            $newOwnerName = $lead->lead_owner_id ? (User::find($lead->lead_owner_id)?->name ?: 'Unknown') : 'None';
            LeadHistory::logEvent(
                $lead,
                'assigned',
                $oldOwnerName,
                $newOwnerName,
                "Lead owner updated from {$oldOwnerName} to {$newOwnerName}"
            );
        }

        return $updated;
    }

    /**
     * Update lead status and handle customer conversion logic.
     */
    public function updateLeadStatus(Lead $lead, string $newStatus): array
    {
        if ($lead->is_customer && $newStatus !== 'Converted') {
            return [
                'success' => false,
                'message' => 'This lead has already been converted to a customer and its status cannot be changed.'
            ];
        }

        $updateData = ['status' => $newStatus];
        $message = 'Lead status successfully updated!';

        if ($newStatus === 'Converted' && !$lead->is_customer) {
            $hasAcceptedQuotation = $lead->getQuotations()->where('status', 'Accepted')->isNotEmpty();
            if (!$hasAcceptedQuotation) {
                return [
                    'success' => false,
                    'message' => 'This lead cannot be converted to a customer because there is no accepted quotation.'
                ];
            }

            $customer = $lead->getCustomer();
            if ($customer) {
                $customer->update(['status' => 'active']);
                $message = 'Lead successfully converted and Customer record activated!';
            } else {
                Customer::create([
                    'tenant_id' => $lead->tenant_id,
                    'name' => $lead->company_name ?: ($lead->contact_person ?: 'Converted Lead'),
                    'email' => $lead->email,
                    'phone' => $lead->phone,
                    'status' => 'active',
                ]);
                $message = 'Lead successfully converted and Customer record created!';
            }

            $updateData['is_customer'] = true;
        }

        $oldStatus = $lead->status ?: 'New';
        $lead->update($updateData);

        if ($oldStatus !== $lead->status) {
            LeadHistory::logEvent(
                $lead,
                'status_changed',
                $oldStatus,
                $lead->status,
                "Lead stage status updated from '{$oldStatus}' to '{$lead->status}'"
            );
        }

        return ['success' => true, 'message' => $message];
    }

    /**
     * Delete lead and its associated document files.
     */
    public function deleteLead(Lead $lead): bool
    {
        $lead->leadDocuments->each(function ($document) {
            Storage::disk('public')->delete($document->file_path);
            $document->delete();
        });

        return $lead->delete();
    }

    /**
     * Handle multi-file document uploads for a lead.
     */
    public function uploadDocuments(Lead $lead, array $files): void
    {
        $uploadedIds = $lead->documents ?? [];

        foreach ($files as $file) {
            $path = $file->store('lead_documents/' . $lead->id, 'public');

            $document = LeadDocument::create([
                'tenant_id' => $lead->tenant_id,
                'lead_id' => $lead->id,
                'file_name' => $file->getClientOriginalName(),
                'file_type' => $file->getClientOriginalExtension(),
                'file_path' => $path,
                'size' => $file->getSize(),
            ]);

            $uploadedIds[] = $document->id;
        }

        $lead->documents = array_values(array_filter($uploadedIds));
        $lead->save();
    }

    /**
     * Delete a single document.
     */
    public function deleteDocument(LeadDocument $document): void
    {
        $lead = $document->lead;
        $document->delete();
        Storage::disk('public')->delete($document->file_path);

        if ($lead) {
            $lead->documents = array_values(array_filter(array_diff($lead->documents ?? [], [$document->id])));
            $lead->save();
        }
    }
}
