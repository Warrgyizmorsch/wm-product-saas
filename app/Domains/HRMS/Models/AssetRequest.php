<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetRequest extends BaseModel
{
    protected $fillable = [
        'company_id',
        'employee_id',
        'asset_category_id',
        'asset_item_id',
        'quantity',
        'requested_asset_id',
        'reason',
        'request_date',
        'status',
        'allocated_asset_id',
        'admin_notes',
    ];

    protected $casts = [
        'request_date' => 'date',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'asset_category_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(AssetItem::class, 'asset_item_id');
    }

    public function allocatedAsset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'allocated_asset_id');
    }

    public function requestedAsset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'requested_asset_id');
    }

    public function allocatedAssets(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Asset::class, 'asset_request_id');
    }

    /**
     * Get consolidated/formatted admin notes string.
     * Combines multiple allocations on the same date into a single line (e.g. "Allocated: AST0002 and AST0003 on 23 Jul, 2026"),
     * while preserving entries allocated on different dates or times.
     */
    public function getFormattedAdminNotesAttribute(): ?string
    {
        if (empty($this->admin_notes)) {
            return null;
        }

        $notes = explode(' | ', $this->admin_notes);
        $groupedByDate = [];
        $otherNotes = [];

        foreach ($notes as $note) {
            $trimmedNote = trim($note);
            if (empty($trimmedNote)) {
                continue;
            }

            // Pattern matching: Allocated: AST0002 on 23 Jul, 2026 OR Directly allocated assets: AST0002, AST0003 on 23 Jul, 2026
            if (preg_match('/^(?:Directly allocated assets?:|Allocated:?|Allocated asset)\s+(.+?)\s+on\s+(.+)$/i', $trimmedNote, $matches)) {
                $rawCodesText = trim($matches[1]);
                $dateStr = trim($matches[2]);

                // Remove parentheses info if any (e.g. (MacBook))
                $cleanedText = preg_replace('/\s*\([^)]*\)/', '', $rawCodesText);
                $cleanedText = str_replace([' and ', '&'], ',', $cleanedText);
                $codesList = array_filter(array_map('trim', explode(',', $cleanedText)));

                if (!isset($groupedByDate[$dateStr])) {
                    $groupedByDate[$dateStr] = [];
                }
                foreach ($codesList as $code) {
                    if ($code && !in_array($code, $groupedByDate[$dateStr])) {
                        $groupedByDate[$dateStr][] = $code;
                    }
                }
            } else {
                $otherNotes[] = $trimmedNote;
            }
        }

        $resultParts = [];
        foreach ($groupedByDate as $dateStr => $codes) {
            $count = count($codes);
            if ($count === 1) {
                $formattedCodes = $codes[0];
            } elseif ($count === 2) {
                $formattedCodes = $codes[0] . ' and ' . $codes[1];
            } else {
                $last = array_pop($codes);
                $formattedCodes = implode(', ', $codes) . ' and ' . $last;
            }
            $resultParts[] = "Allocated: {$formattedCodes} on {$dateStr}";
        }

        foreach ($otherNotes as $other) {
            $resultParts[] = $other;
        }

        return !empty($resultParts) ? implode(' | ', $resultParts) : $this->admin_notes;
    }
}
