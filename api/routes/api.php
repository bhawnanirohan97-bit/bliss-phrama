<?php

use App\Http\Controllers\Api\IndiamartWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/indiamart/{secret}', [IndiamartWebhookController::class, 'store'])
    ->name('webhooks.indiamart');
