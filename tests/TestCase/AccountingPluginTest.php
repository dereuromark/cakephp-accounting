<?php

declare(strict_types=1);

namespace Accounting\Test\TestCase;

use Accounting\AccountingPlugin;
use PHPUnit\Framework\TestCase;

/**
 * Sanity-check that the main plugin class loads.
 */
class AccountingPluginTest extends TestCase
{
    public function testAccountingPluginCanBeInstantiated(): void
    {
        $plugin = new AccountingPlugin();
        $this->assertInstanceOf(AccountingPlugin::class, $plugin);
    }
}
