<?php

namespace App\Services\Psp\Preflight;

interface PspV2PreflightInterface
{
    /**
     * @return array{score_percent: int, green: bool, missing: list<string>}
     */
    public function evaluate(array $evidence): array;
}
