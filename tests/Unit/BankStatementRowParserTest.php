<?php

namespace Tests\Unit;

use App\Domains\Accounting\Support\BankStatementRowParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class BankStatementRowParserTest extends TestCase
{
    public static function dates(): array
    {
        return [
            'iso' => ['2026-09-02', '2026-09-02'],
            'indian day first' => ['02/09/2026', '2026-09-02'],
            'single digits' => ['2/9/2026', '2026-09-02'],
            'two digit year' => ['02-09-26', '2026-09-02'],
            'month name' => ['02-Sep-2026', '2026-09-02'],
            'excel serial number' => [46267, '2026-09-02'],
            'excel serial as text' => ['46267', '2026-09-02'],
            'iso with time' => ['2026-09-02 00:00:00', '2026-09-02'],
        ];
    }

    #[Test]
    #[DataProvider('dates')]
    public function it_reads_dates_day_first_and_excel_serials(mixed $input, string $expected): void
    {
        $this->assertSame($expected, (new BankStatementRowParser())->parseDate($input)?->toDateString());
    }

    #[Test]
    public function it_never_reads_a_date_month_first_or_accepts_impossible_dates(): void
    {
        $parser = new BankStatementRowParser();

        // 09/15/2026 is only valid US-style; Indian statements are day first.
        $this->assertNull($parser->parseDate('09/15/2026'));
        $this->assertNull($parser->parseDate('31/02/2026'));
        $this->assertNull($parser->parseDate('not a date'));
        // Small numbers are not Excel dates (that bug produced 01 Jan 1970).
        $this->assertNull($parser->parseDate(1500));
    }

    #[Test]
    public function it_reads_indian_amount_formats(): void
    {
        $parser = new BankStatementRowParser();

        $this->assertSame(123456.0, $parser->parseAmount('1,23,456.00'));
        $this->assertSame(-500.0, $parser->parseAmount('(500.00)'));
        $this->assertSame(-500.0, $parser->parseAmount('500.00 Dr'));
        $this->assertSame(250.0, $parser->parseAmount('250 CR'));
        $this->assertSame(1000.0, $parser->parseAmount('₹ 1,000'));
        $this->assertNull($parser->parseAmount(''));
    }

    #[Test]
    public function it_combines_withdrawal_and_deposit_columns_into_a_signed_amount(): void
    {
        $parser = new BankStatementRowParser();

        $deposit = $parser->parse(['Date' => '02/09/26', 'Narration' => 'NEFT CR', 'Chq./Ref.No.' => 'UTR123', 'Withdrawal Amt.' => '', 'Deposit Amt.' => '45,000.00']);
        $withdrawal = $parser->parse(['Txn Date' => '05/09/2026', 'Description' => 'CHQ PAID', 'Debit' => '1,500.00', 'Credit' => null]);

        $this->assertTrue($deposit['ok']);
        $this->assertSame(['2026-09-02', 'NEFT CR', 'UTR123', 45000.0], [$deposit['date'], $deposit['description'], $deposit['reference'], $deposit['amount']]);
        $this->assertTrue($withdrawal['ok']);
        $this->assertSame(-1500.0, $withdrawal['amount']);
    }

    #[Test]
    public function it_explains_why_a_row_is_rejected(): void
    {
        $parser = new BankStatementRowParser();

        $this->assertSame('no date', $parser->parse(['description' => 'x', 'amount' => 10])['error']);
        $this->assertStringContainsString('unreadable date', $parser->parse(['date' => '13/13/2026', 'amount' => 10])['error']);
        $this->assertSame('no amount', $parser->parse(['date' => '2026-01-01', 'description' => 'x'])['error']);
        $this->assertSame('zero amount', $parser->parse(['date' => '2026-01-01', 'amount' => '0.00'])['error']);
    }
}
