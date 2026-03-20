<?php

declare(strict_types=1);

namespace Rzessack\HealthMonitor\Command;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Rzessack\HealthMonitor\Entity\SystemHealthSnapshot;
use Rzessack\HealthMonitor\Repository\SystemHealthSnapshotRepository;
use Rzessack\HealthMonitor\Service\SystemHealthService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

use function sprintf;

#[AsCommand(
    name: 'health-monitor:collect',
    description: 'Collect a system health snapshot (CPU, RAM, disk, load)',
)]
class CollectHealthSnapshotCommand extends Command
{
    public function __construct(
        private readonly SystemHealthService $healthService,
        private readonly SystemHealthSnapshotRepository $snapshotRepo,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $cpuPct = $this->measureCpu();
        $memory = $this->healthService->getMemoryInfo();
        $load = $this->healthService->getLoadAverage();
        $disk = $this->healthService->getDiskInfo();

        if (null === $memory || null === $load) {
            $output->writeln('<error>Could not read system metrics.</error>');

            return Command::FAILURE;
        }

        $dbMs = $this->measureDatabase();
        $redisOk = $this->testRedis();

        $snapshot = new SystemHealthSnapshot(
            cpuUsagePct: $cpuPct,
            ramUsagePct: $memory['usage_pct'],
            ramUsedMb: $memory['used_mb'],
            ramTotalMb: $memory['total_mb'],
            loadAvg1: $load['load_1'],
            loadAvg5: $load['load_5'],
            loadAvg15: $load['load_15'],
            diskUsagePct: $disk['usage_pct'] ?? 0.0,
            diskUsedGb: $disk['used_gb'] ?? 0.0,
            diskTotalGb: $disk['total_gb'] ?? 0.0,
            dbResponseMs: $dbMs,
            redisAvailable: $redisOk,
        );

        $this->em->persist($snapshot);
        $this->em->flush();

        $deleted = $this->cleanup();

        $output->writeln(sprintf(
            'Snapshot: CPU %.1f%%, RAM %.1f%%, Disk %.1f%%, Load %.2f. Cleaned %d old.',
            $cpuPct,
            $memory['usage_pct'],
            $disk['usage_pct'] ?? 0.0,
            $load['load_1'],
            $deleted,
        ));

        return Command::SUCCESS;
    }

    private function measureCpu(): float
    {
        $cpu1 = $this->healthService->getCpuSnapshot();
        usleep(1_000_000);
        $cpu2 = $this->healthService->getCpuSnapshot();

        if (null === $cpu1 || null === $cpu2) {
            return 0.0;
        }

        $totalDiff = $cpu2['total'] - $cpu1['total'];
        $idleDiff = ($cpu2['idle'] + $cpu2['iowait']) - ($cpu1['idle'] + $cpu1['iowait']);

        return $totalDiff > 0
            ? round((1 - $idleDiff / $totalDiff) * 100, 1)
            : 0.0;
    }

    private function measureDatabase(): ?float
    {
        try {
            $start = hrtime(true);
            $this->em->getConnection()->executeQuery('SELECT 1');

            return round((hrtime(true) - $start) / 1_000_000, 2);
        } catch (Throwable $e) {
            $this->logger->warning('DB health check failed: '.$e->getMessage());

            return null;
        }
    }

    private function testRedis(): ?bool
    {
        $result = $this->healthService->checkRedis();

        return 'ok' === $result['status'];
    }

    private function cleanup(): int
    {
        $before = new DateTimeImmutable(
            '-7 days',
            new DateTimeZone('Europe/Berlin'),
        );

        return $this->snapshotRepo->deleteOlderThan($before);
    }
}
