<?php

declare(strict_types=1);

namespace Rzessack\HealthMonitor\Service;

use Doctrine\ORM\EntityManagerInterface;
use Throwable;

use function sprintf;

class SystemHealthService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ProcReader $procReader,
        private readonly string $redisUrl,
    ) {
    }

    /** @return array{status: string, message: string} */
    public function checkDatabase(): array
    {
        try {
            $this->em->getConnection()->executeQuery('SELECT 1');

            return ['status' => 'ok', 'message' => 'Database connected'];
        } catch (Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /** @return array{total_gb: float, used_gb: float, free_gb: float, usage_pct: float}|null */
    public function getDiskInfo(): ?array
    {
        $free = disk_free_space('/');
        $total = disk_total_space('/');

        if (false === $free || false === $total || $total <= 0) {
            return null;
        }

        $used = $total - $free;

        return [
            'total_gb' => round($total / 1073741824, 1),
            'used_gb' => round($used / 1073741824, 1),
            'free_gb' => round($free / 1073741824, 1),
            'usage_pct' => round($used / $total * 100, 1),
        ];
    }

    /** @return array{status: string, message: string} */
    public function checkRedis(): array
    {
        if ('' === $this->redisUrl) {
            return ['status' => 'error', 'message' => 'REDIS_URL not configured'];
        }

        try {
            /** @var array{host?: string, port?: int}|false $parsed */
            $parsed = parse_url($this->redisUrl);
            $host = $parsed['host'] ?? 'localhost';
            $port = $parsed['port'] ?? 6379;
            $conn = @fsockopen($host, (int) $port, $errno, $errstr, 2);

            if (false === $conn) {
                return ['status' => 'error', 'message' => 'Redis unreachable: '.$errstr];
            }

            fclose($conn);

            return ['status' => 'ok', 'message' => sprintf('Redis connected (%s:%d)', $host, $port)];
        } catch (Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /** @return array{total: int, idle: int, iowait: int}|null */
    public function getCpuSnapshot(): ?array
    {
        return $this->procReader->getCpuSnapshot();
    }

    /** @return array{total_mb: float, used_mb: float, usage_pct: float}|null */
    public function getMemoryInfo(): ?array
    {
        return $this->procReader->getMemoryInfo();
    }

    /** @return array{load_1: float, load_5: float, load_15: float}|null */
    public function getLoadAverage(): ?array
    {
        return $this->procReader->getLoadAverage();
    }

    public function getCpuCoreCount(): int
    {
        return $this->procReader->getCpuCoreCount();
    }
}
