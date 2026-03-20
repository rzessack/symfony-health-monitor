<?php

declare(strict_types=1);

namespace Rzessack\HealthMonitor\Service;

use function count;

class ProcReader
{
    /** @return array{total: int, idle: int, iowait: int}|null */
    public function getCpuSnapshot(): ?array
    {
        $content = $this->readProc('stat');
        if (null === $content) {
            return null;
        }

        return $this->parseCpuStat($content);
    }

    /** @return array{total_mb: float, used_mb: float, usage_pct: float}|null */
    public function getMemoryInfo(): ?array
    {
        $content = $this->readProc('meminfo');
        if (null === $content) {
            return null;
        }

        return $this->parseMeminfo($content);
    }

    /** @return array{load_1: float, load_5: float, load_15: float}|null */
    public function getLoadAverage(): ?array
    {
        $content = $this->readProc('loadavg');
        if (null === $content) {
            return null;
        }

        $parts = explode(' ', trim($content));
        if (count($parts) < 3) {
            return null;
        }

        return [
            'load_1' => round((float) $parts[0], 2),
            'load_5' => round((float) $parts[1], 2),
            'load_15' => round((float) $parts[2], 2),
        ];
    }

    public function getCpuCoreCount(): int
    {
        $content = $this->readProc('cpuinfo');
        if (null === $content) {
            return 1;
        }

        $count = preg_match_all('/^processor\s*:/m', $content);

        return max(1, $count);
    }

    private function readProc(string $file): ?string
    {
        $path = $this->procPath($file);
        if (null === $path) {
            return null;
        }

        $content = @file_get_contents($path);

        return false !== $content ? $content : null;
    }

    private function procPath(string $file): ?string
    {
        if (is_readable("/host/proc/{$file}")) {
            return "/host/proc/{$file}";
        }

        if (is_readable("/proc/{$file}")) {
            return "/proc/{$file}";
        }

        return null;
    }

    /** @return array{total: int, idle: int, iowait: int}|null */
    private function parseCpuStat(string $content): ?array
    {
        if (!preg_match('/^cpu\s+(.+)$/m', $content, $m)) {
            return null;
        }

        $vals = array_map('intval', preg_split('/\s+/', trim($m[1])));
        if (count($vals) < 5) {
            return null;
        }

        return [
            'total' => array_sum($vals),
            'idle' => $vals[3],
            'iowait' => $vals[4] ?? 0,
        ];
    }

    /** @return array{total_mb: float, used_mb: float, usage_pct: float}|null */
    private function parseMeminfo(string $content): ?array
    {
        $values = [];

        foreach (['MemTotal', 'MemAvailable'] as $key) {
            if (!preg_match("/{$key}:\s+(\d+)/", $content, $m)) {
                return null;
            }
            $values[$key] = (int) $m[1];
        }

        $totalMb = round($values['MemTotal'] / 1024, 1);
        $usedMb = round(($values['MemTotal'] - $values['MemAvailable']) / 1024, 1);
        $pct = $values['MemTotal'] > 0
            ? round(($values['MemTotal'] - $values['MemAvailable']) / $values['MemTotal'] * 100, 1)
            : 0.0;

        return [
            'total_mb' => $totalMb,
            'used_mb' => $usedMb,
            'usage_pct' => $pct,
        ];
    }
}
