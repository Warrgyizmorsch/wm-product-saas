<?php

namespace App\Services;

use App\Rules\ValidGstin;
use App\Rules\ValidPan;

class TaxIdentityService
{
    /** Standard Indian GSTIN Regex: 2 digits state code + 5 letters + 4 digits + 1 letter + 1 entity alphanumeric + 'Z' + 1 checksum char */
    public const GSTIN_REGEX = '/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/';

    /** Standard Indian PAN Regex: 5 uppercase letters + 4 digits + 1 uppercase letter */
    public const PAN_REGEX = '/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/';

    /**
     * Complete list of official GST State Codes and State / UT Names.
     */
    public const GST_STATES = [
        '01' => 'Jammu and Kashmir',
        '02' => 'Himachal Pradesh',
        '03' => 'Punjab',
        '04' => 'Chandigarh',
        '05' => 'Uttarakhand',
        '06' => 'Haryana',
        '07' => 'Delhi',
        '08' => 'Rajasthan',
        '09' => 'Uttar Pradesh',
        '10' => 'Bihar',
        '11' => 'Sikkim',
        '12' => 'Arunachal Pradesh',
        '13' => 'Nagaland',
        '14' => 'Manipur',
        '15' => 'Mizoram',
        '16' => 'Tripura',
        '17' => 'Meghalaya',
        '18' => 'Assam',
        '19' => 'West Bengal',
        '20' => 'Jharkhand',
        '21' => 'Odisha',
        '22' => 'Chhattisgarh',
        '23' => 'Madhya Pradesh',
        '24' => 'Gujarat',
        '26' => 'Dadra and Nagar Haveli and Daman and Diu',
        '27' => 'Maharashtra',
        '28' => 'Andhra Pradesh (Old)',
        '29' => 'Karnataka',
        '30' => 'Goa',
        '31' => 'Lakshadweep',
        '32' => 'Kerala',
        '33' => 'Tamil Nadu',
        '34' => 'Puducherry',
        '35' => 'Andaman and Nicobar Islands',
        '36' => 'Telangana',
        '37' => 'Andhra Pradesh',
        '38' => 'Ladakh',
        '96' => 'Foreign Country',
        '97' => 'Other Territory',
    ];

    /**
     * Sanitize and format GSTIN string: uppercase, remove all non-alphanumeric chars.
     */
    public static function normalizeGstin(?string $gstin): ?string
    {
        if ($gstin === null) {
            return null;
        }

        $clean = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $gstin));
        return $clean !== '' ? $clean : null;
    }

    /**
     * Sanitize and format PAN string: uppercase, remove all non-alphanumeric chars.
     */
    public static function normalizePan(?string $pan): ?string
    {
        if ($pan === null) {
            return null;
        }

        $clean = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $pan));
        return $clean !== '' ? $clean : null;
    }

    /**
     * Validate whether given GSTIN matches Indian GSTIN specifications and has a valid state code.
     */
    public static function isValidGstin(?string $gstin): bool
    {
        $clean = self::normalizeGstin($gstin);
        if (!$clean || strlen($clean) !== 15) {
            return false;
        }

        if (!preg_match(self::GSTIN_REGEX, $clean)) {
            return false;
        }

        $stateCode = substr($clean, 0, 2);
        return isset(self::GST_STATES[$stateCode]);
    }

    /**
     * Validate whether given PAN matches Indian PAN specifications.
     */
    public static function isValidPan(?string $pan): bool
    {
        $clean = self::normalizePan($pan);
        if (!$clean || strlen($clean) !== 10) {
            return false;
        }

        return (bool) preg_match(self::PAN_REGEX, $clean);
    }

    /**
     * Extract 10-character PAN from 15-character GSTIN.
     */
    public static function extractPanFromGstin(?string $gstin): ?string
    {
        $clean = self::normalizeGstin($gstin);
        if (!$clean || strlen($clean) < 12) {
            return null;
        }

        return substr($clean, 2, 10);
    }

    /**
     * Extract 2-digit State Code from GSTIN.
     */
    public static function extractStateCode(?string $gstin): ?string
    {
        $clean = self::normalizeGstin($gstin);
        if (!$clean || strlen($clean) < 2) {
            return null;
        }

        $code = substr($clean, 0, 2);
        return isset(self::GST_STATES[$code]) ? $code : null;
    }

    /**
     * Get State Name for a GSTIN or State Code.
     */
    public static function getStateName(?string $gstinOrCode): ?string
    {
        if (!$gstinOrCode) {
            return null;
        }

        $code = strlen($gstinOrCode) >= 2 ? substr($gstinOrCode, 0, 2) : $gstinOrCode;
        return self::GST_STATES[$code] ?? null;
    }

    /**
     * Returns standard Laravel validation rule array for GSTIN.
     */
    public static function gstinRules(bool $required = false): array
    {
        $rules = [$required ? 'required' : 'nullable', 'string'];
        $rules[] = new ValidGstin();
        return $rules;
    }

    /**
     * Returns standard Laravel validation rule array for PAN.
     */
    public static function panRules(bool $required = false): array
    {
        $rules = [$required ? 'required' : 'nullable', 'string'];
        $rules[] = new ValidPan();
        return $rules;
    }
}
