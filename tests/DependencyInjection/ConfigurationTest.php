<?php

declare(strict_types=1);

namespace Rzessack\HealthMonitor\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Rzessack\HealthMonitor\DependencyInjection\Configuration;
use Symfony\Component\Config\Definition\Processor;

class ConfigurationTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $processor = new Processor();
        $config = $processor->processConfiguration(new Configuration(), []);

        self::assertSame('UTC', $config['timezone']);
        self::assertSame(7, $config['retention_days']);
    }

    public function testCustomValues(): void
    {
        $processor = new Processor();
        $config = $processor->processConfiguration(new Configuration(), [
            [
                'timezone' => 'Europe/Berlin',
                'retention_days' => 14,
            ],
        ]);

        self::assertSame('Europe/Berlin', $config['timezone']);
        self::assertSame(14, $config['retention_days']);
    }
}
