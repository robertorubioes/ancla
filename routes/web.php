<?php

use App\Http\Controllers\Api\PublicVerificationController;
use App\Http\Controllers\DocumentController;
use App\Livewire\Verification\VerificationPage;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

/*
|--------------------------------------------------------------------------
| Public Verification Routes (No Authentication Required)
|--------------------------------------------------------------------------
|
| These routes are publicly accessible for document verification.
| Rate limiting is applied to prevent abuse.
|
*/

Route::middleware(['rate.limit.public:verification'])->group(function () {
    // Web verification page
    Route::get('/verify', VerificationPage::class)
        ->name('verify.show');

    Route::get('/verify/{code}', VerificationPage::class)
        ->name('verify.code');

    // Short URL for QR codes
    Route::get('/v/{code}', VerificationPage::class)
        ->name('verify.short');
});

/*
|--------------------------------------------------------------------------
| Public API Routes (No Authentication Required)
|--------------------------------------------------------------------------
|
| RESTful API endpoints for programmatic document verification.
| Rate limiting is applied to prevent abuse.
|
*/

Route::prefix('api/v1/public')->middleware(['rate.limit.public:verification'])->group(function () {
    // Verify by code
    Route::get('/verify/{code}', [PublicVerificationController::class, 'verifyByCode'])
        ->name('api.public.verify.code');

    // Verify by hash
    Route::post('/verify/hash', [PublicVerificationController::class, 'verifyByHash'])
        ->name('api.public.verify.hash');

    // Get verification details
    Route::get('/verify/{code}/details', [PublicVerificationController::class, 'getDetails'])
        ->name('api.public.verify.details');

    // Get QR code
    Route::get('/verify/{code}/qr', [PublicVerificationController::class, 'getQrCode'])
        ->name('api.public.verify.qr');

    // Get URLs
    Route::get('/verify/{code}/urls', [PublicVerificationController::class, 'getUrls'])
        ->name('api.public.verify.urls');
});

// Evidence download with stricter rate limit
Route::prefix('api/v1/public')->middleware(['rate.limit.public:download'])->group(function () {
    Route::get('/verify/{code}/evidence', [PublicVerificationController::class, 'downloadEvidence'])
        ->name('api.public.verify.evidence');
});

/*
|--------------------------------------------------------------------------
| Document Routes
|--------------------------------------------------------------------------
|
| Routes for document management including upload, view, download,
| thumbnail retrieval, and deletion.
|
*/

Route::middleware(['auth', 'identify.tenant'])->group(function () {
    // Document CRUD routes
    Route::get('/documents', [DocumentController::class, 'index'])
        ->name('documents.index');

    Route::post('/documents', [DocumentController::class, 'store'])
        ->name('documents.store');

    Route::get('/documents/{document}', [DocumentController::class, 'show'])
        ->name('documents.show');

    Route::delete('/documents/{document}', [DocumentController::class, 'destroy'])
        ->name('documents.destroy');

    // Document verification
    Route::post('/documents/{document}/verify', [DocumentController::class, 'verify'])
        ->name('documents.verify');
});

// Signed URL routes for document download and thumbnail
Route::middleware(['auth'])->group(function () {
    Route::get('/documents/{document}/download', [DocumentController::class, 'download'])
        ->name('documents.download')
        ->middleware('signed');

    Route::get('/documents/{document}/thumbnail', [DocumentController::class, 'thumbnail'])
        ->name('documents.thumbnail')
        ->middleware('signed');
});
