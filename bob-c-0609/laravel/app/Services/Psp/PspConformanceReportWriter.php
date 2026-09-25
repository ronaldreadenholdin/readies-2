<?php

namespace App\Services\Psp;

use RuntimeException;

final class PspConformanceReportWriter
{
    public function __construct(private string $outputRoot)
    {
    }

    public function write(array $report): array
    {
        $timestamp = (string) ($report['run']['timestamp'] ?? gmdate('Ymd-His'));
        $runDir = rtrim($this->outputRoot, '/') . '/' . $timestamp;
        if (! is_dir($runDir) && ! mkdir($runDir, 0775, true) && ! is_dir($runDir)) {
            throw new RuntimeException("Unable to create conformance report directory: {$runDir}");
        }

        $jsonPath = "{$runDir}/psp-conformance-report.json";
        $markdownPath = "{$runDir}/psp-conformance-report.md";

        file_put_contents($jsonPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
        file_put_contents($markdownPath, $this->markdown($report));

        return [
            'json' => $jsonPath,
            'markdown' => $markdownPath,
        ];
    }

    private function markdown(array $report): string
    {
        $lines = [
            '# PSP adapter conformance report',
            '',
            '- Run timestamp: `' . ($report['run']['timestamp'] ?? 'unknown') . '`',
            '- Fixture mode: offline recorded fixtures; no live PSP calls and no real keys.',
            '',
        ];

        foreach ($report['summary'] ?? [] as $pspCode => $summary) {
            $eligible = ($summary['eligible_for_cascade'] ?? false) ? 'yes' : 'no';
            $lines[] = "## {$pspCode}";
            $lines[] = '';
            $lines[] = '- Connection: `' . ($summary['connection_code'] ?? $pspCode) . '`';
            $lines[] = '- Adapter: `' . ($summary['adapter_number'] ?? 'unknown') . '` ' . ($summary['adapter_name'] ?? '');
            $lines[] = '- Rule: eligible only at `100%`; no code overrides.';
            $lines[] = '';
            $lines[] = '| Total checks | Passed | Failed | Score | Eligible for cascade |';
            $lines[] = '|---:|---:|---:|---:|:---:|';
            $lines[] = '| ' . ($summary['total_checks'] ?? 0) . ' | ' . ($summary['passed'] ?? 0) . ' | ' . ($summary['failed'] ?? 0) . ' | ' . number_format((float) ($summary['score_percent'] ?? 0), 2) . '% | ' . $eligible . ' |';
            $lines[] = '';

            $issues = $this->issuesForPsp($report['conversion_killers'] ?? [], (string) $pspCode);
            if ($issues === []) {
                $lines[] = 'No conversion-killer issues found.';
                $lines[] = '';
            }

            foreach ($this->groupByConnection($issues) as $connection => $rows) {
                $lines[] = "### {$connection}";
                $lines[] = '';
                foreach ($rows as $issue) {
                    $lines[] = '- **' . $issue['preflight_check_id'] . ' / ' . $issue['preflight_check_name'] . '**';
                    $lines[] = '  - Category: `' . $issue['killer_category'] . '`';
                    $lines[] = '  - Field: internal `' . $issue['field_path']['internal'] . '` -> PSP `' . $issue['field_path']['psp'] . '`';
                    $lines[] = '  - Expected: `' . $this->formatValue($issue['expected']) . '`';
                    $lines[] = '  - Actual: `' . $this->formatValue($issue['actual']) . '`';
                    $lines[] = '  - Why: ' . $issue['why'];
                    $lines[] = '  - Code: `' . $issue['code_location'] . '`';
                    $lines[] = '  - Severity: `' . $issue['severity'] . '`';
                    $lines[] = '  - Suggested fix: ' . $issue['suggested_fix'];
                }
                $lines[] = '';
            }

            $questions = $report['open_questions'][$pspCode] ?? [];
            $lines[] = '### Open PSP questions';
            $lines[] = '';
            if ($questions === []) {
                $lines[] = 'No open commercial/go-live questions.';
            } else {
                foreach ($questions as $question) {
                    $lines[] = '- `' . $question['field'] . '`: ' . $question['question'] . ' (' . $question['severity'] . ')';
                }
            }
            $lines[] = '';
        }

        return implode("\n", $lines) . "\n";
    }

    private function issuesForPsp(array $issues, string $pspCode): array
    {
        return array_values(array_filter($issues, static fn (array $issue): bool => ($issue['psp_code'] ?? null) === $pspCode));
    }

    private function groupByConnection(array $issues): array
    {
        $grouped = [];
        foreach ($issues as $issue) {
            $grouped[$issue['connection'] ?? 'unknown'][] = $issue;
        }

        return $grouped;
    }

    private function formatValue(mixed $value): string
    {
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_SLASHES);
        }
        if ($value === null) {
            return 'null';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    }
}
