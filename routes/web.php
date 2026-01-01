<?php

use App\Http\Controllers\Api\PublicVerificationController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\DocumentDownloadController;
use App\Http\Controllers\InvitationController;
use App\Livewire\Admin\TenantManagement;
use App\Livewire\Settings\UserManagement;
use App\Livewire\Signing\SigningPage;
use App\Livewire\SigningProcess\CreateSigningProcess;
use App\Livewire\SigningProcess\ProcessesDashboard;
use App\Livewire\Verification\VerificationPage;
use Illuminate\Support\Facades\Route;

// Root route - redirect based on authentication status
Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('signing-processes.index');
    }

    return redirect()->route('login');
});

// Home route after authentication
Route::middleware(['auth', 'identify.tenant'])->group(function () {
    Route::get('/home', function () {
        return redirect()->route('signing-processes.index');
    })->name('home');

    Route::get('/dashboard', function () {
        return redirect()->route('signing-processes.index');
    })->name('dashboard');
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
| Public Signing Routes (No Authentication Required)
|--------------------------------------------------------------------------
|
| These routes are publicly accessible for signers to access and sign
| documents via unique token links. Rate limiting is applied.
|
*/

Route::middleware(['rate.limit.public:signing'])->group(function () {
    // Signing page with unique token
    Route::get('/sign/{token}', SigningPage::class)
        ->name('sign.show');

    // Document preview for signers (requires OTP verification)
    Route::get('/documents/{document}/preview', [DocumentController::class, 'preview'])
        ->name('documents.preview');
});

/*
|--------------------------------------------------------------------------
| Public Invitation Routes (No Authentication Required)
|--------------------------------------------------------------------------
|
| These routes allow invited users to accept invitations and create
| their accounts. The token is validated before showing the form.
|
*/

Route::middleware(['rate.limit.public:invitation'])->group(function () {
    // Show invitation acceptance form
    Route::get('/invitation/{token}', [InvitationController::class, 'show'])
        ->name('invitation.show');

    // Accept invitation and create account
    Route::post('/invitation/{token}', [InvitationController::class, 'accept'])
        ->name('invitation.accept');
});

/*
|--------------------------------------------------------------------------
| Public Document Download Routes (Token-based)
|--------------------------------------------------------------------------
|
| These routes allow signers to download their signed document copy
| using a unique token that expires in 30 days.
|
*/

Route::middleware(['rate.limit.public:download'])->group(function () {
    // Download signed document copy (token-based, expires in 30 days)
    Route::get('/download/{token}', [DocumentDownloadController::class, 'download'])
        ->name('document.download');
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

/*
|--------------------------------------------------------------------------
| Signing Process Routes
|--------------------------------------------------------------------------
|
| Routes for managing electronic signature processes including creation,
| viewing process status, and managing signers.
|
*/

Route::middleware(['auth', 'identify.tenant'])->group(function () {
    // List signing processes
    Route::get('/signing-processes', ProcessesDashboard::class)
        ->name('signing-processes.index');

    // Create signing process
    Route::get('/signing-processes/create', CreateSigningProcess::class)
        ->name('signing-processes.create');

    // Create with pre-selected document
    Route::get('/signing-processes/create/{documentId}', CreateSigningProcess::class)
        ->name('signing-processes.create.document');

    // Download signed document (promoter)
    Route::get('/signing-processes/{signingProcess}/download-document', [DocumentDownloadController::class, 'downloadDocument'])
        ->name('signing-processes.download-document');

    // Download evidence dossier (promoter)
    Route::get('/signing-processes/{signingProcess}/download-dossier', [DocumentDownloadController::class, 'downloadDossier'])
        ->name('signing-processes.download-dossier');

    // Download bundle ZIP (promoter)
    Route::get('/signing-processes/{signingProcess}/download-bundle', [DocumentDownloadController::class, 'downloadBundle'])
        ->name('signing-processes.download-bundle');
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

/*
|--------------------------------------------------------------------------
| Superadmin Routes
|--------------------------------------------------------------------------
|
| Routes for superadmin panel to manage tenants (organizations).
| Only accessible by users with superadmin role.
|
*/

Route::middleware(['auth', App\Http\Middleware\EnsureSuperadmin::class])->prefix('admin')->group(function () {
    // Tenant management
    Route::get('/tenants', TenantManagement::class)
        ->name('admin.tenants');
});

/*
|--------------------------------------------------------------------------
| Settings Routes (Tenant Admin)
|--------------------------------------------------------------------------
|
| Routes for tenant settings including user management.
| Only accessible by tenant admins with manage_users permission.
|
*/

Route::middleware(['auth', 'identify.tenant', App\Http\Middleware\EnsureTenantAdmin::class])->prefix('settings')->group(function () {
    // User management
    Route::get('/users', UserManagement::class)
        ->name('settings.users');
});
