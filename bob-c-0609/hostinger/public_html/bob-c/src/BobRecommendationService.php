<?php

declare(strict_types=1);

final class BobRecommendationService
{
    public function generate(array $flaggedItems, string $pspName = 'PSP'): string
    {
        if (count($flaggedItems) === 0) {
            return 'All checks are green. No Bob recommendations needed.';
        }

        $lines = [
            "Dear {$pspName} Team,",
            '',
            'During the Readies PSP pre-flight check we found the following items that must be completed before live activation:',
            '',
        ];

        foreach ($flaggedItems as $index => $item) {
            $category = is_array($item) ? ($item['category'] ?? $item['name'] ?? 'check') : (string) $item;
            $details = is_array($item)
                ? ($item['details'] ?? $item['message'] ?? $item['recommendation'] ?? 'Needs confirmation.')
                : 'Needs confirmation.';
            $lines[] = ($index + 1) . ". {$category}: {$details}";
        }

        $lines[] = '';
        $lines[] = 'Please provide the missing documentation, sample payloads, signature details, or written approval so Readies can complete the go-live review.';
        $lines[] = '';
        $lines[] = 'Thank you.';

        return implode("\n", $lines);
    }
}
