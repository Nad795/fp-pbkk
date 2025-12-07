<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SentimentAnalysisController;
use Illuminate\Http\Request;

// Endpoint untuk analisis sentimen dan readability (no throttling)
Route::post('analyze', [SentimentAnalysisController::class, 'analyze'])
    ->middleware(['log.requests', 'validate.text'])
    ->withoutMiddleware(['throttle']);

// Health check endpoint - ringan, tanpa middleware custom dan tanpa throttle
Route::get('health', [SentimentAnalysisController::class, 'health'])
    ->withoutMiddleware(['throttle', 'log.requests', 'validate.text']);

// Virus scanner status endpoint
Route::get('scanner-status', [SentimentAnalysisController::class, 'scannerStatus'])
    ->withoutMiddleware(['throttle', 'log.requests', 'validate.text']);

// Download user manual
Route::get('download-manual', [SentimentAnalysisController::class, 'downloadUserManual'])
    ->withoutMiddleware(['throttle', 'log.requests', 'validate.text']);
