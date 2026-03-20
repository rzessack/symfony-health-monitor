# Symfony Health Monitor Bundle

System health monitoring bundle for Symfony -- collects CPU, RAM, disk, load average, database response time, and Redis availability as historical snapshots with time-bucketed aggregation.

## Requirements

- PHP >= 8.2
- Symfony 7.x or 8.x
- Doctrine ORM 3.x
- PostgreSQL (aggregation queries use PostgreSQL-specific syntax)

## Installation

```bash
composer require rzessack/symfony-health-monitor
```

## Configuration

### 1. Register the bundle

Add the bundle to `config/bundles.php`:

```php
return [
    // ...
    Rzessack\HealthMonitor\HealthMonitorBundle::class => ['all' => true],
];
```

### 2. Bundle configuration (optional)

Create `config/packages/health_monitor.yaml`:

```yaml
health_monitor:
    timezone: 'Europe/Berlin'  # default: UTC
    retention_days: 14         # default: 7
```

### 3. Set the Redis URL

Add the `REDIS_URL` environment variable to your `.env` file:

```dotenv
REDIS_URL=redis://localhost:6379
```

If you do not use Redis, leave it unset or set it to an empty string — the bundle handles both gracefully.

### 4. Create the database table

```bash
php bin/console doctrine:schema:update --force
```

Or generate a migration:

```bash
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

### 5. Configure Doctrine entity mapping

Ensure the bundle entities are picked up by Doctrine. Add to your `config/packages/doctrine.yaml`:

```yaml
doctrine:
    orm:
        mappings:
            HealthMonitor:
                is_bundle: false
                type: attribute
                dir: '%kernel.project_dir%/vendor/rzessack/symfony-health-monitor/src/Entity'
                prefix: 'Rzessack\HealthMonitor\Entity'
                alias: HealthMonitor
```

### 6. Schedule snapshot collection

Add a cron job that runs every 5 minutes:

```
*/5 * * * * php /path/to/your/project/bin/console health-monitor:collect
```

Or use Symfony Scheduler for in-process scheduling.

## Usage

### Console command

Collect a single snapshot manually:

```bash
php bin/console health-monitor:collect
```

The command automatically cleans up snapshots older than the configured retention period.

### Integration in an admin controller

Inject the services to build a dashboard:

```php
use Rzessack\HealthMonitor\Repository\SystemHealthSnapshotRepository;
use Rzessack\HealthMonitor\Service\SystemHealthService;

class AdminDashboardController extends AbstractController
{
    public function __construct(
        private readonly SystemHealthService $healthService,
        private readonly SystemHealthSnapshotRepository $snapshotRepo,
    ) {
    }

    #[Route('/admin/health', name: 'admin_health')]
    public function health(): Response
    {
        return $this->render('admin/health.html.twig', [
            'snapshots' => $this->snapshotRepo->findAggregated(),
            'db_status' => $this->healthService->checkDatabase(),
            'redis_status' => $this->healthService->checkRedis(),
            'disk_info' => $this->healthService->getDiskInfo(),
            'memory_info' => $this->healthService->getMemoryInfo(),
            'load_average' => $this->healthService->getLoadAverage(),
        ]);
    }
}
```

The `findAggregated()` method returns time-bucketed data suitable for Chart.js or similar charting libraries:

- Last 24 hours: raw 5-minute intervals
- 1-2 days ago: 15-minute averages
- 2-3 days ago: 30-minute averages
- 3-4 days ago: 1-hour averages
- 4-5 days ago: 2-hour averages
- 5-7 days ago: 4-hour averages

### Docker support

The bundle automatically detects Docker environments by looking for `/host/proc/*` paths. Mount the host's `/proc` filesystem into your container:

```yaml
# docker-compose.yaml
services:
    app:
        volumes:
            - /proc:/host/proc:ro
```

## Testing

```bash
composer install
vendor/bin/phpunit
```

## License

MIT
