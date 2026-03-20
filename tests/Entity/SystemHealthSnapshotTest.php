<?php

declare(strict_types=1);

namespace Rzessack\HealthMonitor\Tests\Entity;

use PHPUnit\Framework\TestCase;
use Rzessack\HealthMonitor\Entity\SystemHealthSnapshot;

class SystemHealthSnapshotTest extends TestCase
{
    public function testConstructorSetsAllFields(): void
    {
        $snapshot = new SystemHealthSnapshot(
            cpuUsagePct: 45.2,
            ramUsagePct: 72.5,
            ramUsedMb: 5800.0,
            ramTotalMb: 8000.0,
            loadAvg1: 1.5,
            loadAvg5: 1.2,
            loadAvg15: 0.9,
            diskUsagePct: 65.3,
            diskUsedGb: 130.6,
            diskTotalGb: 200.0,
            dbResponseMs: 1.23,
            redisAvailable: true,
        );

        self::assertNull($snapshot->getId());
        self::assertSame(45.2, $snapshot->getCpuUsagePct());
        self::assertSame(72.5, $snapshot->getRamUsagePct());
        self::assertSame(5800.0, $snapshot->getRamUsedMb());
        self::assertSame(8000.0, $snapshot->getRamTotalMb());
        self::assertSame(1.5, $snapshot->getLoadAvg1());
        self::assertSame(1.2, $snapshot->getLoadAvg5());
        self::assertSame(0.9, $snapshot->getLoadAvg15());
        self::assertSame(65.3, $snapshot->getDiskUsagePct());
        self::assertSame(130.6, $snapshot->getDiskUsedGb());
        self::assertSame(200.0, $snapshot->getDiskTotalGb());
        self::assertSame(1.23, $snapshot->getDbResponseMs());
        self::assertTrue($snapshot->isRedisAvailable());
        self::assertNotNull($snapshot->getRecordedAt());
    }

    public function testDefaultValues(): void
    {
        $snapshot = new SystemHealthSnapshot(
            cpuUsagePct: 10.0,
            ramUsagePct: 20.0,
            ramUsedMb: 1000.0,
            ramTotalMb: 5000.0,
            loadAvg1: 0.5,
            loadAvg5: 0.3,
            loadAvg15: 0.2,
        );

        self::assertSame(0.0, $snapshot->getDiskUsagePct());
        self::assertSame(0.0, $snapshot->getDiskUsedGb());
        self::assertSame(0.0, $snapshot->getDiskTotalGb());
        self::assertNull($snapshot->getDbResponseMs());
        self::assertNull($snapshot->isRedisAvailable());
    }

    public function testTimezoneIsApplied(): void
    {
        $snapshot = new SystemHealthSnapshot(
            cpuUsagePct: 10.0,
            ramUsagePct: 20.0,
            ramUsedMb: 1000.0,
            ramTotalMb: 5000.0,
            loadAvg1: 0.5,
            loadAvg5: 0.3,
            loadAvg15: 0.2,
            timezone: 'Europe/Berlin',
        );

        self::assertSame('Europe/Berlin', $snapshot->getRecordedAt()->getTimezone()->getName());
    }
}
