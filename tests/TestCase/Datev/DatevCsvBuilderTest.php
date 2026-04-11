<?php

declare(strict_types=1);

namespace Accounting\Test\TestCase\Datev;

use Accounting\Datev\Export\DatevBooking;
use Accounting\Datev\Export\DatevCsvBuilder;
use Cake\I18n\Date;
use PHPUnit\Framework\TestCase;

class DatevCsvBuilderTest extends TestCase
{
    protected DatevCsvBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new DatevCsvBuilder(
            consultantNumber: 1001,
            clientNumber: 12345,
            fiscalYearStart: new Date('2026-01-01'),
            accountLength: 4,
        );
    }

    public function testFirstLineIsDatevHeaderV7(): void
    {
        $csv = $this->builder->build([]);
        $lines = explode("\n", $csv);
        // Header row is pipe-delimited metadata (DATEV v7.0 format)
        $this->assertStringStartsWith('"EXTF"', $lines[0]);
        $this->assertStringContainsString('700', $lines[0]);
        $this->assertStringContainsString('21', $lines[0]);
        $this->assertStringContainsString('"Buchungsstapel"', $lines[0]);
    }

    public function testSecondLineIsColumnHeaders(): void
    {
        $csv = $this->builder->build([]);
        $lines = explode("\n", $csv);
        $this->assertStringContainsString('Umsatz (ohne Soll/Haben-Kz)', $lines[1]);
        $this->assertStringContainsString('Soll/Haben-Kennzeichen', $lines[1]);
        $this->assertStringContainsString('Konto', $lines[1]);
        $this->assertStringContainsString('Gegenkonto', $lines[1]);
        $this->assertStringContainsString('Belegdatum', $lines[1]);
    }

    public function testBookingRowFormatAmountUsesCommaDecimal(): void
    {
        $booking = new DatevBooking(
            amount: '119.00',
            creditDebit: 'S',
            account: '1400',
            counterAccount: '8400',
            taxKey: '9',
            date: new Date('2026-03-15'),
            documentNumber: 'RE-2026-0001',
            description: 'Invoice RE-2026-0001',
        );

        $csv = $this->builder->build([$booking]);
        $lines = explode("\n", $csv);
        $this->assertCount(4, $lines); // header + column row + booking + trailing newline
        $this->assertStringContainsString('"119,00"', $lines[2]);
    }

    public function testBookingRowUsesDdmmDateFormat(): void
    {
        $booking = new DatevBooking(
            amount: '50.00',
            creditDebit: 'S',
            account: '1400',
            counterAccount: '8400',
            taxKey: '9',
            date: new Date('2026-03-15'),
            documentNumber: 'RE-1',
            description: 'Test',
        );

        $csv = $this->builder->build([$booking]);
        $lines = explode("\n", $csv);
        $this->assertStringContainsString('"1503"', $lines[2]);
    }

    public function testMultipleBookingsProduceMultipleRows(): void
    {
        $bookings = [
            new DatevBooking('100.00', 'S', '1400', '8400', '9', new Date('2026-01-15'), 'RE-1', 'A'),
            new DatevBooking('200.00', 'S', '1400', '8400', '9', new Date('2026-02-15'), 'RE-2', 'B'),
            new DatevBooking('300.00', 'S', '1400', '8400', '9', new Date('2026-03-15'), 'RE-3', 'C'),
        ];
        $csv = $this->builder->build($bookings);
        $lines = array_filter(explode("\n", $csv));
        $this->assertCount(5, $lines); // header + column row + 3 bookings
    }

    public function testFieldsAreSemicolonDelimited(): void
    {
        $booking = new DatevBooking('100.00', 'S', '1400', '8400', '9', new Date('2026-01-15'), 'RE-1', 'A');
        $csv = $this->builder->build([$booking]);
        $lines = explode("\n", $csv);
        $this->assertGreaterThan(5, substr_count($lines[2], ';'));
    }

    public function testHabenIndicatorIsPreservedOnBooking(): void
    {
        $booking = new DatevBooking('100.00', 'H', '8400', '1400', '9', new Date('2026-01-15'), 'RE-1', 'A');
        $csv = $this->builder->build([$booking]);
        $lines = explode("\n", $csv);
        $this->assertStringContainsString('"H"', $lines[2]);
    }

    public function testDescriptionCommaIsEscapedByEnclosingQuotes(): void
    {
        $booking = new DatevBooking(
            amount: '100.00',
            creditDebit: 'S',
            account: '1400',
            counterAccount: '8400',
            taxKey: '9',
            date: new Date('2026-01-15'),
            documentNumber: 'RE-1',
            description: 'Legal, and accounting services',
        );
        $csv = $this->builder->build([$booking]);
        $lines = explode("\n", $csv);
        $this->assertStringContainsString('"Legal, and accounting services"', $lines[2]);
    }

    public function testFiscalYearInHeaderMatchesConfig(): void
    {
        $csv = $this->builder->build([]);
        $lines = explode("\n", $csv);
        $this->assertStringContainsString('2026', $lines[0]);
    }

    public function testConsultantAndClientNumbersInHeader(): void
    {
        $csv = $this->builder->build([]);
        $lines = explode("\n", $csv);
        $this->assertStringContainsString('1001', $lines[0]);
        $this->assertStringContainsString('12345', $lines[0]);
    }
}
