<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

// Auth routes
Route::middleware('guest')->group(function () {
    Route::get('/login',    \App\Livewire\Auth\Login::class)->name('login');
    Route::get('/register', \App\Livewire\Auth\Register::class)->name('register');
});

Route::post('/logout', function () {
    auth()->logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();
    return redirect()->route('login');
})->name('logout')->middleware('auth');

// Authenticated routes
Route::middleware(['auth', 'account.status'])->group(function () {

    Route::get('/dashboard', \App\Livewire\Dashboard::class)->name('dashboard');

    // Trips
    Route::get('/trips',                                  \App\Livewire\Trips\TripList::class)->name('trips.index');
    Route::get('/trips/{trip}',                           \App\Livewire\Trips\TripDetail::class)->name('trips.show');
    Route::get('/trips/{trip}/signup/{signup}',           \App\Livewire\Trips\SignupWizard::class)->name('trips.signup');
    Route::get('/my-trips',                               \App\Livewire\Trips\MySignups::class)->name('my-signups');

    // Reviews
    Route::get('/trips/{trip}/reviews/create',            \App\Livewire\Reviews\ReviewForm::class)->name('trips.reviews.create');
    Route::get('/trips/{trip}/reviews/{review}/edit',     \App\Livewire\Reviews\ReviewForm::class)->name('trips.reviews.edit');

    // Admin trip management
    Route::middleware('admin')->group(function () {
        Route::get('/admin/trips/create',       \App\Livewire\Trips\TripManage::class)->name('admin.trips.create');
        Route::get('/admin/trips/{trip}/edit',  \App\Livewire\Trips\TripManage::class)->name('admin.trips.manage');
    });

    // ── Credentialing ─────────────────────────────────────────────────────────
    // Doctor's own profile + docs + case management
    Route::get('/credentialing/profile', \App\Livewire\Credentialing\DoctorProfile::class)->name('credentialing.profile');

    // Reviewer / admin: case list + detail
    Route::get('/credentialing/cases',        \App\Livewire\Credentialing\CaseList::class)->name('credentialing.cases');
    Route::get('/credentialing/cases/{case}', \App\Livewire\Credentialing\CaseDetail::class)->name('credentialing.cases.show');

    // Document download — access-checked in controller
    Route::get('/credentialing/documents/{document}/download', function (\App\Models\DoctorDocument $document) {
        $service = new \App\Services\DocumentService();

        if (! $service->canDownload($document, auth()->user())) {
            abort(403, 'You are not authorised to download this document.');
        }

        $path = storage_path('app/' . $document->file_path);

        if (! file_exists($path)) {
            abort(404, 'File not found.');
        }

        return response()->download($path, $document->file_name, [
            'Content-Type' => $document->mime_type,
        ]);
    })->name('credentialing.documents.download');

    // Redirect old /credentialing routes — doctors go to their profile, everyone else to the case list.
    Route::get('/credentialing', function () {
        return auth()->user()->hasRole(\App\Enums\UserRole::DOCTOR)
            ? redirect()->route('credentialing.profile')
            : redirect()->route('credentialing.cases');
    })->name('credentialing.index');
    Route::get('/credentialing/{case}', fn () => redirect()->route('credentialing.cases'))->name('credentialing.show');

    // Membership
    Route::get('/membership',                                        \App\Livewire\Membership\PlanCatalog::class)->name('membership.index');
    Route::get('/membership/my',                                     \App\Livewire\Membership\MyMembership::class)->name('membership.my');
    Route::get('/membership/purchase/{plan}',                        \App\Livewire\Membership\PurchaseFlow::class)->name('membership.purchase');
    Route::get('/membership/top-up/{plan}',                          \App\Livewire\Membership\TopUpFlow::class)->name('membership.top-up');
    Route::get('/membership/orders/{order}/refund',                  \App\Livewire\Membership\RefundRequest::class)->name('membership.refund');
    Route::get('/membership/orders', \App\Livewire\Membership\OrderHistory::class)->name('membership.orders');

    // Finance — refund approval (Finance Specialist or Admin)
    Route::get('/finance/refunds', \App\Livewire\Membership\RefundApproval::class)->name('finance.refunds');

    // Finance
    Route::get('/finance',                              \App\Livewire\Finance\FinanceDashboard::class)->name('finance.index');
    Route::get('/finance/payments',                     \App\Livewire\Finance\PaymentIndex::class)->name('finance.payments');
    Route::get('/finance/payments/record',              \App\Livewire\Finance\PaymentRecord::class)->name('finance.payments.record');
    Route::get('/finance/payments/{payment}',           \App\Livewire\Finance\PaymentDetail::class)->name('finance.payments.show');
    Route::get('/finance/settlements',                  \App\Livewire\Finance\SettlementIndex::class)->name('finance.settlements');
    Route::get('/finance/settlements/{settlement}',     \App\Livewire\Finance\SettlementDetail::class)->name('finance.settlements.show');
    Route::get('/finance/invoices',                     \App\Livewire\Finance\InvoiceIndex::class)->name('finance.invoices');
    Route::get('/finance/invoices/create',              \App\Livewire\Finance\InvoiceBuilder::class)->name('finance.invoices.create');
    Route::get('/finance/invoices/{invoice}',           \App\Livewire\Finance\InvoiceDetail::class)->name('finance.invoices.show');
    Route::get('/finance/invoices/{invoice}/edit',      \App\Livewire\Finance\InvoiceBuilder::class)->name('finance.invoices.edit');
    Route::get('/finance/statements/export',            \App\Livewire\Finance\StatementExport::class)->name('finance.statements.export');

    // Search & Recommendations
    Route::get('/search',           \App\Livewire\Search\TripSearch::class)->name('search');
    Route::get('/recommendations',  \App\Livewire\Search\Recommendations::class)->name('recommendations');

    // Admin — System Administrator only
    Route::middleware('admin')->group(function () {
        Route::get('/admin/users',           \App\Livewire\Admin\UserList::class)->name('admin.users');
        Route::get('/admin/users/{user}',    \App\Livewire\Admin\UserDetail::class)->name('admin.users.show');
        Route::get('/admin/audit',           \App\Livewire\Admin\AuditLogViewer::class)->name('admin.audit');
        Route::get('/admin/config',          \App\Livewire\Admin\SystemConfig::class)->name('admin.config');
        Route::get('/admin/reviews',         \App\Livewire\Reviews\ReviewModeration::class)->name('admin.reviews');
    });

    // Profile
    Route::get('/profile', \App\Livewire\Auth\Profile::class)->name('profile');
});
