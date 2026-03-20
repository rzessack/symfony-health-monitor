<?php

declare(strict_types=1);

namespace Rzessack\HealthMonitor\Tests\Service;

use PHPUnit\Framework\TestCase;
use Rzessack\HealthMonitor\Service\ProcReader;

class ProcReaderTest extends TestCase
{
    public function testGetCpuCoreCountReturnsAtLeastOne(): void
    {
        $reader = new ProcReader();
        self::assertGreaterThanOrEqual(1, $reader->getCpuCoreCount());
    }

    public function testGetCpuSnapshotReturnsExpectedKeys(): void
    {
        $reader = new ProcReader();
        $result = $reader->getCpuSnapshot();

        if (null === $result) {
            self::markTestSkipped('/proc/stat not available on this system');
        }

        self::assertArrayHasKey('total', $result);
        self::assertArrayHasKey('idle', $result);
        self::assertArrayHasKey('iowait', $result);
        self::assertGreaterThan(0, $result['total']);
    }

    public function testGetMemoryInfoReturnsExpectedKeys(): void
    {
        $reader = new ProcReader();
        $result = $reader->getMemoryInfo();

        if (null === $result) {
            self::markTestSkipped('/proc/meminfo not available on this system');
        }

        self::assertArrayHasKey('total_mb', $result);
        self::assertArrayHasKey('used_mb', $result);
        self::assertArrayHasKey('usage_pct', $result);
        self::assertGreaterThan(0.0, $result['total_mb']);
    }

    public function testGetLoadAverageReturnsExpectedKeys(): void
    {
        $reader = new ProcReader();
        $result = $reader->getLoadAverage();

        if (null === $result) {
            self::markTestSkipped('/proc/loadavg not available on this system');
        }

        self::assertArrayHasKey('load_1', $result);
        self::assertArrayHasKey('load_5', $result);
        self::assertArrayHasKey('load_15', $result);
    }
}
