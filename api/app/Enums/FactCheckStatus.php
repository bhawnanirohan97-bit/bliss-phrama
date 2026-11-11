<?php

namespace App\Enums;

/**
 * The result of the automated fact check that runs before a draft is shown.
 *
 * A failed draft is still stored, because the operator needs to see that the
 * template produced something unsupportable, but its body is withheld on the
 * lead screen (PRD Amendment 19).
 */
enum FactCheckStatus: string
{
    case Passed = 'passed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Passed => 'Fact check passed',
            self::Failed => 'Fact check failed',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Passed => 'green',
            self::Failed => 'red',
        };
    }
}
