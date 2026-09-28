<?php

namespace App\Domains\Inventory\Services;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Uom;
use InvalidArgumentException;

class MaterialMeasurementService
{
    public const TYPE_COUNT = 'count';
    public const TYPE_WEIGHT = 'weight';
    public const TYPE_LINEAR = 'linear';
    public const TYPE_SHEET = 'sheet';

    public const VALID_TYPES = [
        self::TYPE_COUNT,
        self::TYPE_WEIGHT,
        self::TYPE_LINEAR,
        self::TYPE_SHEET,
    ];

    /**
     * Derive canonical product UOM quantity from measurement type and physical inputs.
     * Enforces strict type-safety and explicit V1 conversion rules.
     */
    public function calculateCanonicalQuantity(Product $product, string $measurementType, array $measurements): float
    {
        $uom = $product->uom ?? ($product->uom_id ? Uom::find($product->uom_id) : null);
        $uomCode = strtolower(trim($uom->code ?? $uom->name ?? 'pcs'));

        switch ($measurementType) {
            case self::TYPE_COUNT:
                $pieces = (float) ($measurements['pieces'] ?? $measurements['quantity'] ?? 1);
                if ($pieces <= 0) {
                    throw new InvalidArgumentException("Count measurement pieces must be greater than zero.");
                }
                return round($pieces, 4);

            case self::TYPE_WEIGHT:
                $weight = (float) ($measurements['weight'] ?? 0);
                $unit = strtolower(trim($measurements['weight_unit'] ?? 'kg'));

                if ($weight <= 0) {
                    throw new InvalidArgumentException("Weight measurement must be greater than zero.");
                }

                if (!in_array($unit, ['kg', 'g'], true)) {
                    throw new InvalidArgumentException("Unsupported weight unit [{$unit}]. V1 supports only kg and g.");
                }

                $isProductKg = in_array($uomCode, ['kg', 'kilogram', 'kilograms'], true);
                $isProductG = in_array($uomCode, ['g', 'gram', 'grams'], true);

                if (!$isProductKg && !$isProductG) {
                    throw new InvalidArgumentException(
                        "Product [{$product->name}] has UOM [{$uomCode}], which is not a supported weight unit (kg, g)."
                    );
                }

                if ($isProductKg) {
                    $canonical = ($unit === 'kg') ? $weight : ($weight / 1000.0);
                } else {
                    $canonical = ($unit === 'g') ? $weight : ($weight * 1000.0);
                }

                return round($canonical, 4);

            case self::TYPE_LINEAR:
                $lengthMm = (float) ($measurements['length'] ?? 0);
                $pieces = max(1, (int) ($measurements['pieces'] ?? 1));

                if ($lengthMm <= 0) {
                    throw new InvalidArgumentException("Linear measurement length must be greater than zero mm.");
                }

                $totalMm = $lengthMm * $pieces;

                if (in_array($uomCode, ['mtr', 'meter', 'meters', 'm'], true)) {
                    return round($totalMm / 1000.0, 4);
                }

                if (in_array($uomCode, ['mm', 'millimeter', 'millimeters'], true)) {
                    return round($totalMm, 4);
                }

                if (in_array($uomCode, ['pcs', 'pieces', 'pc'], true)) {
                    $standardLength = (float) ($product->length ?? 0);
                    if ($standardLength <= 0) {
                        throw new InvalidArgumentException(
                            "Product [{$product->name}] is measured in Pieces but has no standard length defined."
                        );
                    }
                    return round($totalMm / $standardLength, 4);
                }

                throw new InvalidArgumentException(
                    "Product [{$product->name}] has UOM [{$uomCode}], which does not support linear conversion (expected Mtr, mm, or Pieces)."
                );

            case self::TYPE_SHEET:
                $lengthMm = (float) ($measurements['length'] ?? 0);
                $widthMm = (float) ($measurements['width'] ?? 0);
                $pieces = max(1, (int) ($measurements['pieces'] ?? 1));

                if ($lengthMm <= 0 || $widthMm <= 0) {
                    throw new InvalidArgumentException("Sheet measurement length and width must be greater than zero mm.");
                }

                if (in_array($uomCode, ['sqm', 'm2', 'sq.m', 'square meter', 'square meters'], true)) {
                    $areaM2 = ($lengthMm / 1000.0) * ($widthMm / 1000.0) * $pieces;
                    return round($areaM2, 4);
                }

                if (in_array($uomCode, ['pcs', 'pieces', 'pc'], true)) {
                    $stdLength = (float) ($product->length ?? 0);
                    $stdWidth = (float) ($product->width ?? 0);
                    if ($stdLength <= 0 || $stdWidth <= 0) {
                        throw new InvalidArgumentException(
                            "Product [{$product->name}] is measured in Pieces but has no standard length and width defined."
                        );
                    }
                    $standardArea = $stdLength * $stdWidth;
                    $actualArea = $lengthMm * $widthMm * $pieces;
                    return round($actualArea / $standardArea, 4);
                }

                throw new InvalidArgumentException(
                    "Product [{$product->name}] has UOM [{$uomCode}], which does not support sheet conversion (expected SqM or Pieces with standard dimensions)."
                );

            default:
                throw new InvalidArgumentException("Unsupported measurement type [{$measurementType}].");
        }
    }

    /**
     * Automatically infer the appropriate measurement type from product UOM and dimension attributes.
     */
    public function inferMeasurementType(Product $product): string
    {
        $uom = $product->uom ?? ($product->uom_id ? Uom::find($product->uom_id) : null);
        $uomCode = strtolower(trim($uom->code ?? $uom->name ?? 'pcs'));

        if (in_array($uomCode, ['mtr', 'meter', 'meters', 'm', 'mm', 'cm', 'inch', 'ft'], true)) {
            return self::TYPE_LINEAR;
        }

        if (in_array($uomCode, ['sqm', 'm2', 'sq.m', 'sqft', 'sq.ft', 'square meter', 'square meters'], true)) {
            return self::TYPE_SHEET;
        }

        if (in_array($uomCode, ['kg', 'kilogram', 'kilograms', 'g', 'gram', 'grams', 'ton', 'lbs', 'mg'], true)) {
            return self::TYPE_WEIGHT;
        }

        // Discrete items (pieces / units): check dimensional attributes
        $length = (float) ($product->length ?? 0);
        $width = (float) ($product->width ?? 0);

        if ($length > 0 && $width > 0) {
            return self::TYPE_SHEET;
        }

        if ($length > 0 && $width <= 0) {
            return self::TYPE_LINEAR;
        }

        $weight = (float) ($product->weight ?? 0);
        if ($weight > 0 && !empty($product->weight_unit)) {
            return self::TYPE_WEIGHT;
        }

        return self::TYPE_COUNT;
    }
}
