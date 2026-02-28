<?php

namespace App\Console\Commands;

use Illuminate\Support\Facades\File;
use Illuminate\Console\Command;

class PreflightBrief extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'preflight:brief
                            {--input= : Input JSON report path}
                            {--output= : Output Markdown file path}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a human-readable Markdown brief from preflight JSON output';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $inputPath = trim((string)$this->option('input'));
        if ($inputPath === '') {
            $inputPath = base_path('../docs/reports/preflight/preflight-latest.json');
        } elseif (!str_starts_with($inputPath, '/')) {
            $inputPath = base_path($inputPath);
        }

        $outputPath = trim((string)$this->option('output'));
        if ($outputPath === '') {
            $outputPath = base_path('../docs/reports/preflight/preflight-latest.md');
        } elseif (!str_starts_with($outputPath, '/')) {
            $outputPath = base_path($outputPath);
        }

        if (!File::exists($inputPath)) {
            $this->error("Input JSON not found: {$inputPath}");
            $this->line('Run `php artisan preflight:history --module=all` first.');
            return self::FAILURE;
        }

        $raw = File::get($inputPath);
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            $this->error("Invalid JSON in: {$inputPath}");
            return self::FAILURE;
        }

        $checks = is_array($data['checks'] ?? null) ? $data['checks'] : [];
        $summary = is_array($data['summary'] ?? null) ? $data['summary'] : ['pass' => 0, 'warn' => 0, 'fail' => 0];
        $overall = (string)($data['overall_status'] ?? 'UNKNOWN');
        $module = (string)($data['module'] ?? 'unknown');

        $failChecks = array_values(array_filter($checks, fn ($c) => (($c['status'] ?? '') === 'FAIL')));
        $warnChecks = array_values(array_filter($checks, fn ($c) => (($c['status'] ?? '') === 'WARN')));
        $passChecks = array_values(array_filter($checks, fn ($c) => (($c['status'] ?? '') === 'PASS')));

        $lines = [];
        $lines[] = '# Preflight Readiness Brief';
        $lines[] = '';
        $lines[] = '- Generated at: ' . now()->toDateTimeString();
        $lines[] = "- Module: `{$module}`";
        $lines[] = "- Overall status: `{$overall}`";
        $lines[] = '- Source JSON: `' . $inputPath . '`';
        $lines[] = '';
        $lines[] = '## Summary';
        $lines[] = '';
        $lines[] = '- PASS: ' . (int)($summary['pass'] ?? 0);
        $lines[] = '- WARN: ' . (int)($summary['warn'] ?? 0);
        $lines[] = '- FAIL: ' . (int)($summary['fail'] ?? 0);
        $lines[] = '';
        $lines[] = '## Priority Findings';
        $lines[] = '';

        if (count($failChecks) === 0 && count($warnChecks) === 0) {
            $lines[] = '- No FAIL or WARN checks.';
        } else {
            foreach ($failChecks as $check) {
                $lines[] = '- FAIL - `' . (string)($check['check'] ?? 'unknown') . '`: ' . (string)($check['detail'] ?? '');
            }
            foreach ($warnChecks as $check) {
                $lines[] = '- WARN - `' . (string)($check['check'] ?? 'unknown') . '`: ' . (string)($check['detail'] ?? '');
            }
        }

        $lines[] = '';
        $lines[] = '## Passing Checks';
        $lines[] = '';
        if (count($passChecks) === 0) {
            $lines[] = '- No PASS checks.';
        } else {
            foreach ($passChecks as $check) {
                $lines[] = '- PASS - `' . (string)($check['check'] ?? 'unknown') . '`: ' . (string)($check['detail'] ?? '');
            }
        }
        $lines[] = '';

        $outputDir = dirname($outputPath);
        File::ensureDirectoryExists($outputDir);
        File::put($outputPath, implode(PHP_EOL, $lines));

        $this->info("Saved preflight brief: {$outputPath}");
        return self::SUCCESS;
    }
}
