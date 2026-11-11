<?php

use App\Http\Controllers\Api\AiBotController;
use App\Http\Controllers\Api\IndiamartWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/indiamart/{secret}', [IndiamartWebhookController::class, 'store'])
    ->name('webhooks.indiamart');

Route::prefix('ai-bot')->group(function () {
    Route::get('/settings', [AiBotController::class, 'index']);
    Route::put('/settings', [AiBotController::class, 'update']);
    Route::post('/toggle', [AiBotController::class, 'toggle']);
    Route::post('/evaluate', [AiBotController::class, 'evaluate']);
    Route::post('/check-lead', [AiBotController::class, 'evaluate']);
    Route::post('/record-lead', [AiBotController::class, 'recordBoughtLead']);
    Route::post('/record-bought-lead', [AiBotController::class, 'recordBoughtLead']);
});
