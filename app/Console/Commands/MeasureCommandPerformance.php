<?php

namespace App\Console\Commands;

use App\Models\City;
use App\Models\DailyReturn;
use App\Models\Facility;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Local timing for the command center. Five runs, median time and query count.
 */
class MeasureCommandPerformance extends Command
{
    protected $signature = 'tbai:perf {--output= : Write the markdown report to this path} {--label=before : Report heading, before or after}';

    protected $description = 'Median time and query count for the command center pages and API, as admin, the Sindh viewer and the Karachi West officer.';

    private int $queries = 0;

    private bool $counting = false;

    public function handle(Kernel $kernel): int
    {
        if (! app()->environment('local')) {
            $this->error('Refused. tbai:perf runs only when the application environment is local.');

            return self::FAILURE;
        }

        $users = [
            'admin' => User::query()->where('email', 'admin@tbai.test')->first(),
            'Sindh viewer' => User::query()->where('email', 'sindh@tbai.test')->first(),
            'Karachi West officer' => User::query()->where('email', 'karachi-west@tbai.test')->first(),
        ];

        foreach ($users as $label => $user) {
            if (! $user instanceof User) {
                $this->error("Missing the {$label} account. Seed the prototype logins first.");

                return self::FAILURE;
            }
        }

        $cityId = City::query()->where('slug', 'karachi-west')->value('id');

        if ($cityId === null) {
            $this->error('Missing the Karachi West district. Seed the prototype geography first.');

            return self::FAILURE;
        }

        DB::listen(function (): void {
            if ($this->counting) {
                $this->queries++;
            }
        });

        $paths = [
            '/command',
            '/command/map',
            '/command/alerts',
            '/command/sectors/public',
            '/api/command/frame',
            '/api/command/cumulative',
            '/api/command/intelligence',
            '/api/command/sectors/public',
            '/api/command/sectors/private',
            '/api/command/sectors/ppm',
            '/api/command/sectors/public/districts/'.$cityId.'/bmus',
            '/api/command/map',
            '/api/command/drivers',
            '/api/command/ai-tests',
            '/api/command/incidence',
        ];

        $rows = [];

        foreach ($users as $label => $user) {
            foreach ($paths as $path) {
                $times = [];
                $counts = [];
                $status = 0;

                for ($run = 0; $run < 5; $run++) {
                    [$seconds, $queryCount, $status] = $this->once($kernel, $user, $path);
                    $times[] = $seconds;
                    $counts[] = $queryCount;
                }

                $rows[] = [
                    'path' => $path,
                    'role' => $label,
                    'seconds' => $this->median($times),
                    'queries' => $this->median($counts),
                    'status' => $status,
                ];

                $this->line(sprintf(
                    '%s %s  %.3f s  %s queries  HTTP %d',
                    $path,
                    $label,
                    $this->median($times),
                    $this->formatMedian($this->median($counts)),
                    $status,
                ));
            }
        }

        $label = $this->option('label');
        $markdown = $this->markdown($rows, is_string($label) && $label !== '' ? $label : 'before');
        $this->newLine();
        $this->line($markdown);

        $output = $this->option('output');

        if (is_string($output) && $output !== '') {
            $directory = dirname($output);

            if (! is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            file_put_contents($output, $markdown);
            $this->info('Wrote '.$output);
        }

        return self::SUCCESS;
    }

    /**
     * @return array{0: float, 1: int, 2: int}
     */
    private function once(Kernel $kernel, User $user, string $path): array
    {
        Auth::guard('web')->setUser($user);
        Auth::shouldUse('web');

        $server = str_starts_with($path, '/api/')
            ? ['HTTP_ACCEPT' => 'application/json', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']
            : ['HTTP_ACCEPT' => 'text/html,application/xhtml+xml'];

        $request = Request::create($path, 'GET', [], [], [], $server);
        $started = hrtime(true);
        $this->queries = 0;
        $this->counting = true;

        try {
            $response = $kernel->handle($request);
        } finally {
            $this->counting = false;
        }

        $seconds = (hrtime(true) - $started) / 1_000_000_000;
        $count = $this->queries;
        $status = $response->getStatusCode();
        $kernel->terminate($request, $response);

        if ($status >= 400) {
            $this->warn($path.' answered HTTP '.$status.' for '.$user->email);
        }

        return [$seconds, $count, $status];
    }

    /**
     * @param  list<float|int>  $values
     */
    private function median(array $values): float
    {
        sort($values);
        $count = count($values);
        $middle = intdiv($count, 2);

        if ($count % 2 === 1) {
            return (float) $values[$middle];
        }

        return ((float) $values[$middle - 1] + (float) $values[$middle]) / 2;
    }

    private function formatMedian(float $value): string
    {
        return fmod($value, 1.0) === 0.0 ? (string) (int) $value : number_format($value, 1, '.', '');
    }

    /**
     * @param  list<array{path: string, role: string, seconds: float, queries: float, status: int}>  $rows
     */
    private function markdown(array $rows, string $label): string
    {
        $lines = [
            '# Command center performance: '.$label,
            '',
            'Measured with `php artisan tbai:perf` on '.now()->timezone('Asia/Karachi')->toDateTimeString().' Asia/Karachi.',
            'Environment: local. Cache store: '.config('cache.default').'. Database: '.config('database.default').'.',
            'Prototype seed: '.Facility::query()->where('status', 'active')->count().' active facilities, '.DailyReturn::query()->count().' daily returns.',
            'Each cell is the median of 5 in-process runs (query count from DB::listen, including middleware).',
            '',
            '| Request | Role | Median seconds | Median queries | HTTP |',
            '| --- | --- | ---: | ---: | ---: |',
        ];

        foreach ($rows as $row) {
            $lines[] = sprintf(
                '| `%s` | %s | %s | %s | %d |',
                $row['path'],
                $row['role'],
                number_format($row['seconds'], 3, '.', ''),
                $this->formatMedian($row['queries']),
                $row['status'],
            );
        }

        $lines[] = '';

        return implode("\n", $lines);
    }
}
