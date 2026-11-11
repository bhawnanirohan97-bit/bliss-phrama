<?php

namespace App\Http\Controllers;

use App\Enums\DraftChannel;
use App\Models\Lead;
use App\Services\Drafting\DraftService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Regenerating a draft is the only write on this module.
 *
 * Approval and send records are deliberately not here yet: v1 never sends, and
 * an approval that does not correspond to a real send would be false evidence.
 */
class DraftController extends Controller
{
    public function __construct(private readonly DraftService $drafts) {}

    public function regenerate(Request $request, Lead $lead): RedirectResponse
    {
        $validated = $request->validate([
            'channel' => ['nullable', 'string', 'in:whatsapp,email,call'],
        ]);

        $channel = isset($validated['channel']) && $validated['channel'] !== ''
            ? DraftChannel::from($validated['channel'])
            : null;

        $created = $this->drafts->regenerate($lead, $channel);

        if ($created === []) {
            return back()->with(
                'status',
                $lead->latestAssessment === null
                    ? 'Assess the lead first: drafts are anchored to a stored assessment.'
                    : 'No drafts were generated because this lead fired a hard gate.',
            );
        }

        $label = $channel?->label() ?? 'All three';

        return back()->with('status', $label.' draft(s) regenerated: '.count($created).'.');
    }
}
