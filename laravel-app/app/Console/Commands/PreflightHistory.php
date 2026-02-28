<?php

namespace App\Console\Commands;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Console\Command;

class PreflightHistory extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'preflight:history
                            {--module=all : all|core|kpi|inventory|timeclock}
                            {--output= : Optional report directory path}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run preflight JSON check and persist a timestamped report file';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $module = strtolower((string)$this->option('module'));
        if (!in_array($module, ['all', 'core', 'kpi', 'inventory', 'timeclock'], true)) {
            $this->error('Invalid module. Use: all|core|kpi|inventory|timeclock');
            return self::FAILURE;
        }

        $outputDir = trim((string)$this->option('output'));
        if ($outputDir === '') {
            $outputDir = base_path('../docs/reports/preflight');
        } elseif (!str_starts_with($outputDir, '/')) {
            $outputDir = base_path($outputDir);
        }

        File::ensureDirectoryExists($outputDir);

        $exitCode = Artisan::call('preflight:check', [
            '--module' => $module,
            '--json' => true,
        ]);

        $json = trim(Artisan::output());
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            $this->error('Unable to parse preflight JSON output.');
            return self::FAILURE;
        }

        $timestamp = now()->format('Ymd_His');
        $fileName = sprintf('preflight-%s-%s.json', $module, $timestamp);
        $targetFile = rtrim($outputDir, '/').'/'.$fileName;
        File::put($targetFile, json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);

        $latestFile = rtrim($outputDir, '/').'/preflight-latest.json';
        File::put($latestFile, json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);

        $summary = $decoded['summary'] ?? ['pass' => 0, 'warn' => 0, 'fail' => 0];
        $status = (string)($decoded['overall_status'] ?? 'UNKNOWN');
        $this->info("Saved preflight report: {$targetFile}");
        $this->line("Status: {$status} | PASS={$summary['pass']} WARN={$summary['warn']} FAIL={$summary['fail']}");
        $this->line("Latest pointer updated: {$latestFile}");

        return $exitCode;
    }
}
