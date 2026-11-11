<?php

namespace App\Services\Drafting;

/**
 * The verdict of the automated fact check, plus the evidence for a failure.
 */
final readonly class FactCheckResult
{
    /**
     * @param  list<string>  $violations
     */
    public function __construct(
        public bool $passed,
        public array $violations,
    ) {}

    /**
     * @return array{passed: bool, violations: list<string>}
     */
    public function toDetail(): array
    {
        return [
            'passed' => $this->passed,
            'violations' => $this->violations,
        ];
    }
}
