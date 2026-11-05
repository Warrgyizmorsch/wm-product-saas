<?php

namespace App\Domains\Accounting\Services\Integration\Tally;

use App\Domains\Accounting\Models\IntegrationConnection;
use Illuminate\Support\Carbon;

/**
 * Builds the XML sent to Tally's HTTP interface.
 *
 * Import envelopes follow help.tallysolutions.com/sample-xml. The collection
 * pull (inline TDL) and the Day Book pull are standard practice but are not
 * spelled out word for word on those pages, so confirm them on a real install
 * (see docs/accounting-integrations/TALLY_VERIFICATION.md).
 */
class TallyXmlBuilder
{
    public const COLLECTION_GROUPS = 'ERPGroups';
    public const COLLECTION_LEDGERS = 'ERPLedgers';

    /** @param array<int, string> $fetch */
    public function pullCollection(IntegrationConnection $connection, string $collection, string $objectType, array $fetch): string
    {
        $fetchList = $this->e(implode(',', $fetch));

        return '<ENVELOPE><HEADER><VERSION>1</VERSION><TALLYREQUEST>Export</TALLYREQUEST><TYPE>Collection</TYPE>'
            .'<ID>'.$this->e($collection).'</ID></HEADER><BODY><DESC><STATICVARIABLES>'
            .'<SVEXPORTFORMAT>$$SysName:XML</SVEXPORTFORMAT>'
            .$this->company($connection)
            .'</STATICVARIABLES><TDL><TDLMESSAGE>'
            .'<COLLECTION NAME="'.$this->e($collection).'" ISMODIFY="No">'
            .'<TYPE>'.$this->e($objectType).'</TYPE><FETCH>'.$fetchList.'</FETCH>'
            .'</COLLECTION></TDLMESSAGE></TDL></DESC></BODY></ENVELOPE>';
    }

    public function pullGroups(IntegrationConnection $connection): string
    {
        return $this->pullCollection($connection, self::COLLECTION_GROUPS, 'Group', ['Name', 'GUID', 'Parent']);
    }

    public function pullLedgers(IntegrationConnection $connection): string
    {
        return $this->pullCollection($connection, self::COLLECTION_LEDGERS, 'Ledger', ['Name', 'GUID', 'Parent']);
    }

    public function pullVouchers(IntegrationConnection $connection, string $from, string $to): string
    {
        return '<ENVELOPE><HEADER><VERSION>1</VERSION><TALLYREQUEST>Export</TALLYREQUEST><TYPE>Data</TYPE>'
            .'<ID>Day Book</ID></HEADER><BODY><DESC><STATICVARIABLES>'
            .'<SVEXPORTFORMAT>$$SysName:XML</SVEXPORTFORMAT>'
            .$this->company($connection)
            .'<SVFROMDATE TYPE="Date">'.Carbon::parse($from)->format('j-M-Y').'</SVFROMDATE>'
            .'<SVTODATE TYPE="Date">'.Carbon::parse($to)->format('j-M-Y').'</SVTODATE>'
            .'</STATICVARIABLES></DESC></BODY></ENVELOPE>';
    }

    /** @param array<int, array{name: string, parent: string}> $ledgers */
    public function importLedgers(IntegrationConnection $connection, array $ledgers): string
    {
        $body = '';
        foreach ($ledgers as $ledger) {
            $body .= '<TALLYMESSAGE xmlns:UDF="TallyUDF"><LEDGER NAME="'.$this->e($ledger['name']).'" ACTION="Create">'
                .'<NAME>'.$this->e($ledger['name']).'</NAME>'
                .'<PARENT>'.$this->e($ledger['parent']).'</PARENT>'
                .'</LEDGER></TALLYMESSAGE>';
        }

        return '<ENVELOPE><HEADER><TALLYREQUEST>Import Data</TALLYREQUEST></HEADER><BODY><IMPORTDATA>'
            .'<REQUESTDESC><REPORTNAME>All Masters</REPORTNAME>'.$this->importCompany($connection).'</REQUESTDESC>'
            .'<REQUESTDATA>'.$body.'</REQUESTDATA></IMPORTDATA></BODY></ENVELOPE>';
    }

    /**
     * @param array<int, array{
     *     remote_id: string, tally_type: string, date: string, number: string, narration: ?string,
     *     lines: array<int, array{ledger_name: string, debit: float, credit: float}>
     * }> $vouchers
     */
    public function importVouchers(IntegrationConnection $connection, array $vouchers): string
    {
        $tag = (string) $connection->setting('voucher_entries_tag', 'LEDGERENTRIES.LIST');
        $body = '';

        foreach ($vouchers as $voucher) {
            $entries = '';
            foreach ($voucher['lines'] as $line) {
                // Tally's sample pairs a debit with ISDEEMEDPOSITIVE=Yes and a
                // negative amount, and a credit with No and a positive amount.
                $isDebit = (float) $line['debit'] > 0;
                $amount = $isDebit ? -abs((float) $line['debit']) : abs((float) $line['credit']);
                $entries .= '<'.$tag.'>'
                    .'<LEDGERNAME>'.$this->e($line['ledger_name']).'</LEDGERNAME>'
                    .'<ISDEEMEDPOSITIVE>'.($isDebit ? 'Yes' : 'No').'</ISDEEMEDPOSITIVE>'
                    .'<AMOUNT>'.number_format($amount, 2, '.', '').'</AMOUNT>'
                    .'</'.$tag.'>';
            }

            $body .= '<TALLYMESSAGE xmlns:UDF="TallyUDF">'
                .'<VOUCHER REMOTEID="'.$this->e($voucher['remote_id']).'" VCHTYPE="'.$this->e($voucher['tally_type']).'" ACTION="Create">'
                .'<DATE>'.Carbon::parse($voucher['date'])->format('Ymd').'</DATE>'
                .'<VOUCHERTYPENAME>'.$this->e($voucher['tally_type']).'</VOUCHERTYPENAME>'
                .'<VOUCHERNUMBER>'.$this->e($voucher['number']).'</VOUCHERNUMBER>'
                .'<NARRATION>'.$this->e((string) $voucher['narration']).'</NARRATION>'
                .'<PERSISTEDVIEW>Accounting Voucher View</PERSISTEDVIEW>'
                .'<ISINVOICE>No</ISINVOICE>'
                .$entries
                .'</VOUCHER></TALLYMESSAGE>';
        }

        return '<ENVELOPE><HEADER><VERSION>1</VERSION><TALLYREQUEST>Import</TALLYREQUEST><TYPE>Data</TYPE><ID>Vouchers</ID></HEADER>'
            .'<BODY><DESC>'.$this->importStaticVariables($connection).'</DESC><DATA>'.$body.'</DATA></BODY></ENVELOPE>';
    }

    private function company(IntegrationConnection $connection): string
    {
        return $connection->external_company
            ? '<SVCURRENTCOMPANY>'.$this->e($connection->external_company).'</SVCURRENTCOMPANY>'
            : '';
    }

    private function importStaticVariables(IntegrationConnection $connection): string
    {
        return $connection->external_company
            ? '<STATICVARIABLES>'.$this->company($connection).'</STATICVARIABLES>'
            : '';
    }

    private function importCompany(IntegrationConnection $connection): string
    {
        return $this->importStaticVariables($connection);
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
