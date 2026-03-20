<?php

declare(strict_types=1);

namespace Rzessack\HealthMonitor\Entity;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\Mapping as ORM;
use Rzessack\HealthMonitor\Repository\SystemHealthSnapshotRepository;

#[ORM\Entity(repositoryClass: SystemHealthSnapshotRepository::class)]
#[ORM\Table(name: 'system_health_snapshots')]
#[ORM\Index(columns: ['recorded_at'], name: 'idx_health_recorded_at')]
class SystemHealthSnapshot
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private float $cpuUsagePct;

    #[ORM\Column]
    private float $ramUsagePct;

    #[ORM\Column]
    private float $ramUsedMb;

    #[ORM\Column]
    private float $ramTotalMb;

    #[ORM\Column]
    private float $loadAvg1;

    #[ORM\Column]
    private float $loadAvg5;

    #[ORM\Column]
    private float $loadAvg15;

    #[ORM\Column]
    private float $diskUsagePct;

    #[ORM\Column]
    private float $diskUsedGb;

    #[ORM\Column]
    private float $diskTotalGb;

    #[ORM\Column(nullable: true)]
    private ?float $dbResponseMs = null;

    #[ORM\Column(nullable: true)]
    private ?bool $redisAvailable = null;

    #[ORM\Column]
    private DateTimeImmutable $recordedAt;

    public function __construct(
        float $cpuUsagePct,
        float $ramUsagePct,
        float $ramUsedMb,
        float $ramTotalMb,
        float $loadAvg1,
        float $loadAvg5,
        float $loadAvg15,
        float $diskUsagePct = 0.0,
        float $diskUsedGb = 0.0,
        float $diskTotalGb = 0.0,
        ?float $dbResponseMs = null,
        ?bool $redisAvailable = null,
    ) {
        $this->cpuUsagePct = $cpuUsagePct;
        $this->ramUsagePct = $ramUsagePct;
        $this->ramUsedMb = $ramUsedMb;
        $this->ramTotalMb = $ramTotalMb;
        $this->loadAvg1 = $loadAvg1;
        $this->loadAvg5 = $loadAvg5;
        $this->loadAvg15 = $loadAvg15;
        $this->diskUsagePct = $diskUsagePct;
        $this->diskUsedGb = $diskUsedGb;
        $this->diskTotalGb = $diskTotalGb;
        $this->dbResponseMs = $dbResponseMs;
        $this->redisAvailable = $redisAvailable;
        $this->recordedAt = new DateTimeImmutable(
            'now',
            new DateTimeZone('Europe/Berlin'),
        );
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCpuUsagePct(): float
    {
        return $this->cpuUsagePct;
    }

    public function getRamUsagePct(): float
    {
        return $this->ramUsagePct;
    }

    public function getRamUsedMb(): float
    {
        return $this->ramUsedMb;
    }

    public function getRamTotalMb(): float
    {
        return $this->ramTotalMb;
    }

    public function getLoadAvg1(): float
    {
        return $this->loadAvg1;
    }

    public function getLoadAvg5(): float
    {
        return $this->loadAvg5;
    }

    public function getLoadAvg15(): float
    {
        return $this->loadAvg15;
    }

    public function getDiskUsagePct(): float
    {
        return $this->diskUsagePct;
    }

    public function getDiskUsedGb(): float
    {
        return $this->diskUsedGb;
    }

    public function getDiskTotalGb(): float
    {
        return $this->diskTotalGb;
    }

    public function getDbResponseMs(): ?float
    {
        return $this->dbResponseMs;
    }

    public function isRedisAvailable(): ?bool
    {
        return $this->redisAvailable;
    }

    public function getRecordedAt(): DateTimeImmutable
    {
        return $this->recordedAt;
    }
}
