<?php

declare(strict_types=1);

namespace Accounting\Test\TestCase;

use Accounting\Plugin;
use PHPUnit\Framework\TestCase;

/**
 * Sanity-check that the main plugin class loads.
 */
class PluginTest extends TestCase
{
    public function testPluginCanBeInstantiated(): void
    {
        $plugin = new Plugin();
        $this->assertInstanceOf(Plugin::class, $plugin);
    }
}
