<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LeadSource;
use App\Models\WebhookEvent;
use App\Services\Ingestion\LeadIngestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class IndiamartWebhookController extends Controller
{
    public function __construct(private readonly LeadIngestionService $ingestion) {}

    /**
     * Receive a real-time lead pushed by IndiaMART's Lead Manager Push API.
     *
     * The endpoint must answer quickly and must not partially persist a lead:
     * IndiaMART retries any push that does not return HTTP 200, and deactivates
     * the integration if pushes fail for 48 continuous hours.
     */
    public function store(Request $request, string $secret): JsonResponse
    {
        $expected = (string) config('services.indiamart.webhook_secret');

        if ($expected === '' || ! hash_equals($expected, $secret)) {
            abort(404);
        }

        $body = $request->json()->all();
        $inner = data_get($body, 'body.RESPONSE');
        $payload = is_array($inner) ? $inner : $body;

        $source = LeadSource::firstOrCreate(
            ['connector_type' => 'indiamart'],
            ['name' => 'IndiaMART', 'integration_status' => 'connected'],
        );

        $externalId = $payload['UNIQUE_QUERY_ID'] ?? null;

        $event = WebhookEvent::create([
            'lead_source_id' => $source->id,
            'external_lead_id' => $externalId,
            'ip' => $request->ip(),
            'payload' => $body,
            'outcome' => 'received',
        ]);

        if ($externalId === null || $externalId === '') {
            $event->update([
                'outcome' => 'ignored',
                'error' => 'Missing UNIQUE_QUERY_ID',
            ]);

            return response()->json(['CODE' => 200, 'STATUS' => 'SUCCESS']);
        }

        try {
            $this->ingestion->ingest($source, $payload);

            $source->update(['last_success_at' => now()]);
            $event->update(['outcome' => 'stored']);
        } catch (Throwable $exception) {
            $event->update([
                'outcome' => 'error',
                'error' => $exception->getMessage(),
            ]);

            Log::error('IndiaMART webhook failed to store lead.', [
                'external_lead_id' => $externalId,
                'exception' => $exception->getMessage(),
            ]);

            return response()->json(['CODE' => 500, 'STATUS' => 'ERROR'], 500);
        }

        return response()->json(['CODE' => 200, 'STATUS' => 'SUCCESS']);
    }
}
