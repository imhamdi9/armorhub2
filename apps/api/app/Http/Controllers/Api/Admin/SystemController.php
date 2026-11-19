<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** Whether the machinery behind the platform is running: the scheduler, the queue, the database, the disk. Platform administrators only. */
class SystemController extends Controller
{
    public function show()
    {
        $beat = Cache::get('scheduler:last_run');
        $beatAge = $beat ? now()->diffInSeconds(\Carbon\Carbon::parse($beat)) : null;
        $free = @disk_free_space(storage_path());
        $total = @disk_total_space(storage_path());

        $failed = DB::table('failed_jobs')->orderByDesc('id')->limit(50)->get()->map(fn ($j) => [
            'uuid' => $j->uuid,
            'queue' => $j->queue,
            'job' => (string) (json_decode($j->payload, true)['displayName'] ?? 'unknown'),
            'error' => \Illuminate\Support\Str::limit(strtok((string) $j->exception, "\n") ?: '', 200),
            'failed_at' => $j->failed_at,
        ])->all();

        return [
            'checks' => [
                // "detail" is a sentence with {placeholders} and "params" fills them: the screen translates the sentence, then fills it.
                ['key' => 'scheduler', 'label' => 'Scheduler', 'status' => $beatAge === null ? 'fail' : ($beatAge <= 180 ? 'ok' : 'fail'),
                    'detail' => $beatAge === null ? 'No heartbeat yet. Run `php artisan schedule:run` every minute (or `schedule:work`).' : 'Last ran {n} seconds ago.', 'params' => ['n' => $beatAge]],
                ['key' => 'queue', 'label' => 'Background jobs', 'status' => count($failed) > 0 ? 'warn' : 'ok',
                    'detail' => '{driver} queue: {waiting} waiting, {failed} failed.', 'params' => ['driver' => config('queue.default'), 'waiting' => $this->pending(), 'failed' => DB::table('failed_jobs')->count()]],
                ['key' => 'database', 'label' => 'Database', 'status' => $this->database() ? 'ok' : 'fail',
                    'detail' => $this->database() ? '{driver} connection works.' : '{driver} connection failed.', 'params' => ['driver' => config('database.default')]],
                ['key' => 'cache', 'label' => 'Cache', 'status' => $this->cache() ? 'ok' : 'fail',
                    'detail' => $this->cache() ? '{driver} store works.' : '{driver} store failed.', 'params' => ['driver' => config('cache.default')]],
                ['key' => 'disk', 'label' => 'Disk space', 'status' => ($free && $total && $free / $total < 0.1) ? 'warn' : 'ok',
                    'detail' => $free && $total ? '{free} GB free of {total} GB.' : 'Unknown.', 'params' => $free && $total ? ['free' => round($free / 1073741824, 1), 'total' => round($total / 1073741824, 1)] : []],
            ],
            'failed_jobs' => $failed,
            'runtime' => ['php' => PHP_VERSION, 'environment' => app()->environment(), 'queue' => config('queue.default'), 'mail' => config('mail.default')],
        ];
    }

    public function retry(Request $request, string $uuid)
    {
        abort_unless(DB::table('failed_jobs')->where('uuid', $uuid)->exists(), 404);
        Artisan::call('queue:retry', ['id' => [$uuid]]);
        Audit::record('system.failed_job_retried', null, ['uuid' => $uuid], $request->user());

        return ['retried' => $uuid];
    }

    public function forget(Request $request, string $uuid)
    {
        abort_unless(DB::table('failed_jobs')->where('uuid', $uuid)->exists(), 404);
        Artisan::call('queue:forget', ['id' => $uuid]);
        Audit::record('system.failed_job_removed', null, ['uuid' => $uuid], $request->user());

        return response()->noContent();
    }

    private function pending(): int
    {
        try {
            return config('queue.default') === 'database' ? DB::table('jobs')->count() : 0;
        } catch (\Throwable) {
            return 0;
        }
    }

    private function database(): bool
    {
        try {
            DB::select('select 1');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function cache(): bool
    {
        try {
            Cache::put('system:probe', 1, 10);

            return Cache::get('system:probe') === 1;
        } catch (\Throwable) {
            return false;
        }
    }
}
