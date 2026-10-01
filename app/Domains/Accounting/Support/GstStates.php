<?php

namespace App\Domains\Accounting\Support;

/**
 * GST state codes (the first two digits of a GSTIN, and the "pos" used in
 * GSTR-1), plus the Unique Quantity Codes the portal accepts in the HSN
 * summary.
 */
final class GstStates
{
    public const STATES = [
        '01' => 'Jammu and Kashmir', '02' => 'Himachal Pradesh', '03' => 'Punjab', '04' => 'Chandigarh',
        '05' => 'Uttarakhand', '06' => 'Haryana', '07' => 'Delhi', '08' => 'Rajasthan', '09' => 'Uttar Pradesh',
        '10' => 'Bihar', '11' => 'Sikkim', '12' => 'Arunachal Pradesh', '13' => 'Nagaland', '14' => 'Manipur',
        '15' => 'Mizoram', '16' => 'Tripura', '17' => 'Meghalaya', '18' => 'Assam', '19' => 'West Bengal',
        '20' => 'Jharkhand', '21' => 'Odisha', '22' => 'Chhattisgarh', '23' => 'Madhya Pradesh', '24' => 'Gujarat',
        '26' => 'Dadra and Nagar Haveli and Daman and Diu', '27' => 'Maharashtra', '28' => 'Andhra Pradesh (Old)',
        '29' => 'Karnataka', '30' => 'Goa', '31' => 'Lakshadweep', '32' => 'Kerala', '33' => 'Tamil Nadu',
        '34' => 'Puducherry', '35' => 'Andaman and Nicobar Islands', '36' => 'Telangana', '37' => 'Andhra Pradesh',
        '38' => 'Ladakh', '96' => 'Foreign Country', '97' => 'Other Territory',
    ];

    /** Other spellings people type in addresses. */
    private const ALIASES = [
        'orissa' => '21', 'pondicherry' => '34', 'j&k' => '01', 'jammu & kashmir' => '01', 'new delhi' => '07',
        'nct of delhi' => '07', 'daman' => '26', 'diu' => '26', 'dadra' => '26', 'andaman' => '35', 'uttaranchal' => '05',
    ];

    public const UQC = [
        'BAG', 'BAL', 'BDL', 'BKL', 'BOU', 'BOX', 'BTL', 'BUN', 'CAN', 'CBM', 'CCM', 'CMS', 'CTN', 'DOZ', 'DRM', 'GGK',
        'GMS', 'GRS', 'GYD', 'KGS', 'KLR', 'KME', 'LTR', 'MLT', 'MTR', 'MTS', 'NOS', 'OTH', 'PAC', 'PCS', 'PRS', 'QTL',
        'ROL', 'SET', 'SQF', 'SQM', 'SQY', 'TBS', 'TGM', 'THD', 'TON', 'TUB', 'UGS', 'UNT', 'YDS',
    ];

    private const UQC_ALIASES = [
        'KG' => 'KGS', 'KGM' => 'KGS', 'KILOGRAM' => 'KGS', 'KILOGRAMS' => 'KGS', 'G' => 'GMS', 'GM' => 'GMS', 'GRAM' => 'GMS',
        'L' => 'LTR', 'LT' => 'LTR', 'LITRE' => 'LTR', 'LITER' => 'LTR', 'ML' => 'MLT', 'M' => 'MTR', 'METER' => 'MTR',
        'METRE' => 'MTR', 'CM' => 'CMS', 'PC' => 'PCS', 'PIECE' => 'PCS', 'PIECES' => 'PCS', 'NO' => 'NOS', 'NUMBER' => 'NOS',
        'NUMBERS' => 'NOS', 'EA' => 'NOS', 'EACH' => 'NOS', 'UNIT' => 'UNT', 'UNITS' => 'UNT', 'DOZEN' => 'DOZ',
        'PKT' => 'PAC', 'PACK' => 'PAC', 'PACKET' => 'PAC', 'TONNE' => 'MTS', 'MT' => 'MTS', 'PAIR' => 'PRS', 'ROLL' => 'ROL',
        'BOTTLE' => 'BTL', 'CARTON' => 'CTN', 'SQFT' => 'SQF', 'SQ FT' => 'SQF', 'SQMT' => 'SQM', 'SQ M' => 'SQM',
    ];

    public static function name(?string $code): ?string
    {
        return $code === null ? null : (self::STATES[$code] ?? null);
    }

    public static function fromGstin(?string $gstin): ?string
    {
        $code = substr(strtoupper(trim((string) $gstin)), 0, 2);

        return isset(self::STATES[$code]) ? $code : null;
    }

    /** Finds a state named in free text such as a billing address ("…, Pune, Maharashtra 411001"). */
    public static function findInText(?string $text): ?string
    {
        $haystack = ' ' . strtolower(preg_replace('/[^A-Za-z&]+/', ' ', (string) $text)) . ' ';

        if (trim($haystack) === '') {
            return null;
        }

        $names = array_map('strtolower', self::STATES) + [];
        // Longest names first, so "Andhra Pradesh (Old)" never wins over the current one.
        uasort($names, fn ($a, $b) => strlen($b) <=> strlen($a));

        foreach ($names as $code => $name) {
            if ($code === '28' || $code === '96' || $code === '97') {
                continue;
            }

            $plain = trim(preg_replace('/[^a-z&]+/', ' ', $name));
            if (str_contains($haystack, " {$plain} ")) {
                return (string) $code;
            }
        }

        foreach (self::ALIASES as $alias => $code) {
            if (str_contains($haystack, " {$alias} ")) {
                return $code;
            }
        }

        return null;
    }

    public static function isValidGstin(?string $gstin): bool
    {
        $gstin = strtoupper(trim((string) $gstin));

        return (bool) preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/', $gstin)
            && isset(self::STATES[substr($gstin, 0, 2)]);
    }

    public static function uqc(?string $unit): string
    {
        $unit = strtoupper(trim((string) $unit));

        if ($unit === '') {
            return 'OTH';
        }

        if (in_array($unit, self::UQC, true)) {
            return $unit;
        }

        return self::UQC_ALIASES[$unit] ?? 'OTH';
    }
}
