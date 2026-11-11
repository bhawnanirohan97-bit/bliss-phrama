<?php

namespace App\Services\Drafting;

/**
 * One rendered template, before the fact check runs.
 */
final readonly class RenderedDraft
{
    public function __construct(
        public ?string $subject,
        public string $body,
        public string $templateId,
        public string $templateVersion,
    ) {}
}
