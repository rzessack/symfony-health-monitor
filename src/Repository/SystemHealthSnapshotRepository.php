<?php

declare(strict_types=1);

namespace Rzessack\HealthMonitor\Repository;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Rzessack\HealthMonitor\Entity\SystemHealthSnapshot;

use function in_array;

/**
 * @extends ServiceEntityRepository<SystemHealthSnapshot>
 */
class SystemHealthSnapshotRepository extends ServiceEntityRepository
{
    private readonly DateTimeZone $tz;

    public function __construct(
        ManagerRegistry $registry,
        string $timezone = 'UTC',
    ) {
        parent::__construct($registry, SystemHealthSnapshot::class);
        $this->tz = new DateTimeZone($timezone);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findAggregated(): array
    {
        $now = new DateTimeImmutable('now', $this->tz);

        $buckets = [
            [0, 1, 5],
            [1, 2, 15],
            [2, 3, 30],
            [3, 4, 60],
            [4, 5, 120],
            [5, 7, 240],
        ];

        $result = [];

        foreach ($buckets as [$startDays, $endDays, $intervalMin]) {
            $rows = $this->fetchBucket(
                $now,
                $startDays,
                $endDays,
                $intervalMin,
            );
            $result = [...$result, ...$rows];
        }

        usort(
            $result,
            static fn (array $a, array $b): int => $a['recorded_at'] <=> $b['recorded_at'],
        );

        return $result;
    }

    public function deleteOlderThan(DateTimeImmutable $before): int
    {
        return (int) $this->createQueryBuilder('s')
            ->delete()
            ->where('s.recordedAt < :before')
            ->setParameter('before', $before)
            ->getQuery()
            ->execute();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchBucket(
        DateTimeImmutable $now,
        int $startDays,
        int $endDays,
        int $intervalMin,
    ): array {
        $from = $now->modify("-{$endDays} days")->setTime(0, 0);
        $to = 0 === $startDays
            ? $now
            : $now->modify("-{$startDays} days")->setTime(23, 59, 59);

        if ($intervalMin <= 5) {
            return $this->fetchRaw($from, $to);
        }

        return $this->fetchAggregated($from, $to, $intervalMin);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchRaw(
        DateTimeImmutable $from,
        DateTimeImmutable $to,
    ): array {
        $conn = $this->getEntityManager()->getConnection();

        $sql = 'SELECT recorded_at,
                       cpu_usage_pct AS cpu_pct,
                       ram_usage_pct AS ram_pct,
                       ram_used_mb,
                       load_avg1 AS load1,
                       load_avg5 AS load5,
                       load_avg15 AS load15,
                       disk_usage_pct AS disk_pct,
                       disk_used_gb,
                       db_response_ms AS db_ms,
                       redis_available AS redis_ok
                FROM system_health_snapshots
                WHERE recorded_at >= :from AND recorded_at <= :to
                ORDER BY recorded_at ASC';

        $rows = $conn->fetchAllAssociative($sql, [
            'from' => $from->format('Y-m-d H:i:s'),
            'to' => $to->format('Y-m-d H:i:s'),
        ]);

        return array_map([$this, 'normalizeRow'], $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchAggregated(
        DateTimeImmutable $from,
        DateTimeImmutable $to,
        int $intervalMin,
    ): array {
        $conn = $this->getEntityManager()->getConnection();
        $interval = $intervalMin * 60;

        $sql = 'SELECT
                    TO_TIMESTAMP(
                        FLOOR(EXTRACT(EPOCH FROM recorded_at) / :interval) * :interval
                    ) AS recorded_at,
                    ROUND(AVG(cpu_usage_pct)::numeric, 1) AS cpu_pct,
                    ROUND(AVG(ram_usage_pct)::numeric, 1) AS ram_pct,
                    ROUND(AVG(ram_used_mb)::numeric, 1) AS ram_used_mb,
                    ROUND(AVG(load_avg1)::numeric, 2) AS load1,
                    ROUND(AVG(load_avg5)::numeric, 2) AS load5,
                    ROUND(AVG(load_avg15)::numeric, 2) AS load15,
                    ROUND(AVG(disk_usage_pct)::numeric, 1) AS disk_pct,
                    ROUND(AVG(disk_used_gb)::numeric, 1) AS disk_used_gb,
                    ROUND(AVG(db_response_ms)::numeric, 2) AS db_ms,
                    BOOL_AND(COALESCE(redis_available, true)) AS redis_ok
                FROM system_health_snapshots
                WHERE recorded_at >= :from AND recorded_at <= :to
                GROUP BY FLOOR(EXTRACT(EPOCH FROM recorded_at) / :interval)
                ORDER BY 1 ASC';

        $rows = $conn->fetchAllAssociative($sql, [
            'interval' => $interval,
            'from' => $from->format('Y-m-d H:i:s'),
            'to' => $to->format('Y-m-d H:i:s'),
        ]);

        return array_map([$this, 'normalizeRow'], $rows);
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function normalizeRow(array $row): array
    {
        $dbMs = null !== $row['db_ms'] ? (float) $row['db_ms'] : null;
        $redisOk = null !== $row['redis_ok']
            ? in_array($row['redis_ok'], [true, 't', '1', 1], true)
            : null;

        return [
            'recorded_at' => $row['recorded_at'],
            'cpu_pct' => (float) $row['cpu_pct'],
            'ram_pct' => (float) $row['ram_pct'],
            'ram_used_mb' => (float) $row['ram_used_mb'],
            'load1' => (float) $row['load1'],
            'load5' => (float) $row['load5'],
            'load15' => (float) $row['load15'],
            'disk_pct' => (float) $row['disk_pct'],
            'disk_used_gb' => (float) $row['disk_used_gb'],
            'db_ms' => $dbMs,
            'redis_ok' => $redisOk,
        ];
    }
}
