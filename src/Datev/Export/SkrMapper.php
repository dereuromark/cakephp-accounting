<?php

declare(strict_types=1);

namespace Accounting\Datev\Export;

use InvalidArgumentException;

/**
 * Map domain categories (`revenue`, `expense`, `trade`, `vat_received`, ...)
 * to DATEV SKR03 or SKR04 account numbers.
 *
 * The plugin ships a small seed map covering the most common small-business
 * cases. Applications with more exotic accounts (construction reverse charge,
 * intracommunity supplies, special investment deductions, etc.) should pass
 * their own full mapping via the `$overrides` argument — the seed is
 * explicitly a starting point, not a complete account chart.
 *
 * Rate keys in the map are integer strings (`'19'`, `'7'`, `'0'`) matching
 * the VAT rate argument converted via `(int)`.
 */
class SkrMapper
{
    /**
     * @var array<string, array<string, array<int|string, string>>>
     */
    protected const SEED = [
        'SKR03' => [
            'revenue' => [
                '19' => '8400',
                '7' => '8300',
                '0' => '8120',
            ],
            'expense' => [
                '19' => '4980',
                '7' => '4985',
                '0' => '4990',
            ],
            'vat_received' => [
                '19' => '1776',
                '7' => '1771',
            ],
            'trade_receivables' => [
                '0' => '1400',
            ],
        ],
        'SKR04' => [
            'revenue' => [
                '19' => '4400',
                '7' => '4300',
                '0' => '4120',
            ],
            'expense' => [
                '19' => '6300',
                '7' => '6305',
                '0' => '6310',
            ],
            'vat_received' => [
                '19' => '3806',
                '7' => '3801',
            ],
            'trade_receivables' => [
                '0' => '1200',
            ],
        ],
    ];

    /**
     * @var array<string, array<int|string, string>>
     */
    protected array $map;

    /**
     * @param string $chart Either `SKR03` or `SKR04`
     * @param array<string, array<string, string>> $overrides Per-category rate-to-account overrides
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(string $chart, array $overrides = [])
    {
        if (!isset(self::SEED[$chart])) {
            throw new InvalidArgumentException(sprintf(
                'Unknown chart "%s". Supported: SKR03, SKR04.',
                $chart,
            ));
        }
        $map = self::SEED[$chart];
        foreach ($overrides as $category => $rates) {
            $map[$category] = ($map[$category] ?? []) + $rates;
            foreach ($rates as $rate => $account) {
                $map[$category][$rate] = $account;
            }
        }
        $this->map = $map;
    }

    public function accountFor(string $category, float $vatRate): string
    {
        if (!isset($this->map[$category])) {
            throw new InvalidArgumentException(sprintf(
                'Unknown category "%s".',
                $category,
            ));
        }
        $key = (string)(int)$vatRate;
        if (!isset($this->map[$category][$key])) {
            throw new InvalidArgumentException(sprintf(
                'No mapping for category "%s" at VAT rate %s%%.',
                $category,
                $key,
            ));
        }

        return $this->map[$category][$key];
    }
}
