<?php

declare(strict_types=1);

namespace Accounting\Test\TestCase\Datev;

use Accounting\Datev\Export\SkrMapper;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class SkrMapperTest extends TestCase
{
    public function testSkr03MapsRevenueFullTaxToDefaultAccount(): void
    {
        $mapper = new SkrMapper('SKR03');
        $this->assertSame('8400', $mapper->accountFor('revenue', 19.0));
    }

    public function testSkr03MapsRevenueReducedTaxToDedicatedAccount(): void
    {
        $mapper = new SkrMapper('SKR03');
        $this->assertSame('8300', $mapper->accountFor('revenue', 7.0));
    }

    public function testSkr03MapsZeroRatedRevenueToTaxFreeAccount(): void
    {
        $mapper = new SkrMapper('SKR03');
        $this->assertSame('8120', $mapper->accountFor('revenue', 0.0));
    }

    public function testSkr04MapsRevenueFullTaxToDifferentAccount(): void
    {
        $mapper = new SkrMapper('SKR04');
        $this->assertSame('4400', $mapper->accountFor('revenue', 19.0));
    }

    public function testSkr04MapsRevenueReducedTaxToDifferentAccount(): void
    {
        $mapper = new SkrMapper('SKR04');
        $this->assertSame('4300', $mapper->accountFor('revenue', 7.0));
    }

    public function testUnknownCategoryThrows(): void
    {
        $mapper = new SkrMapper('SKR03');
        $this->expectException(InvalidArgumentException::class);
        $mapper->accountFor('not_a_category', 19.0);
    }

    public function testCustomOverrideReplacesDefault(): void
    {
        $mapper = new SkrMapper('SKR03', [
            'revenue' => ['19' => '8401'],
        ]);
        $this->assertSame('8401', $mapper->accountFor('revenue', 19.0));
    }

    public function testUnknownChartThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new SkrMapper('SKR99');
    }
}
