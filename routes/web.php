<?php

use App\Http\Controllers\Dashboard\ClientController;
use App\Http\Controllers\Dashboard\ContactRequestController;
use App\Http\Controllers\Dashboard\MarketingSourceController;
use App\Http\Controllers\Dashboard\PermissionMatrixController;
use App\Http\Controllers\Dashboard\PropertyController;
use App\Http\Controllers\Dashboard\PropertyOwnerController;
use App\Http\Controllers\Dashboard\RoleController;
use App\Http\Controllers\Dashboard\SupervisorController;
use App\Http\Controllers\Dashboard\WebsiteController;
use App\Models\Client;
use App\Models\ContactRequest;
use App\Models\Property;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard.index', [
            'stats' => [
                'properties' => Property::count(),
                'clients' => Client::count(),
                'requests' => ContactRequest::where('is_read', false)->count(),
                'agents' => User::where('is_agent', true)->count(),
            ],
        ]);
    })->name('dashboard');

    Route::prefix('dashboard')->name('dashboard.')->group(function () {

        // ===== إدارة العملاء =====
        Route::middleware('can:clients.view')->group(function () {
            Route::get('clients', [ClientController::class, 'index'])->name('clients.index');
            Route::post('clients', [ClientController::class, 'store'])->name('clients.store');
            Route::get('clients/{client}', [ClientController::class, 'show'])->name('clients.show');
            Route::put('clients/{client}', [ClientController::class, 'update'])->name('clients.update');
            Route::delete('clients/{client}', [ClientController::class, 'destroy'])->name('clients.destroy');
            Route::post('clients/{client}/interactions', [ClientController::class, 'logInteraction'])->name('clients.interactions.store');
            Route::post('clients/{client}/properties', [ClientController::class, 'attachProperty'])->name('clients.properties.attach');
            Route::delete('clients/{client}/properties/{property}', [ClientController::class, 'detachProperty'])->name('clients.properties.detach');
        });

        // ===== ملّاك العقارات =====
        Route::middleware('can:property_owners.view')->group(function () {
            Route::get('owners', [PropertyOwnerController::class, 'index'])->name('owners.index');
            Route::post('owners', [PropertyOwnerController::class, 'store'])->name('owners.store');
            Route::put('owners/{owner}', [PropertyOwnerController::class, 'update'])->name('owners.update');
            Route::delete('owners/{owner}', [PropertyOwnerController::class, 'destroy'])->name('owners.destroy');
        });

        // ===== مصادر التسويق =====
        Route::middleware('can:marketing_sources.view')->group(function () {
            Route::get('sources', [MarketingSourceController::class, 'index'])->name('sources.index');
            Route::post('sources', [MarketingSourceController::class, 'store'])->name('sources.store');
            Route::put('sources/{source}', [MarketingSourceController::class, 'update'])->name('sources.update');
            Route::delete('sources/{source}', [MarketingSourceController::class, 'destroy'])->name('sources.destroy');
        });

        // ===== المشرفين =====
        Route::middleware('can:supervisors.view')->group(function () {
            Route::get('supervisors', [SupervisorController::class, 'index'])->name('supervisors.index');
            Route::post('supervisors', [SupervisorController::class, 'store'])->name('supervisors.store');
            Route::put('supervisors/{supervisor}', [SupervisorController::class, 'update'])->name('supervisors.update');
            Route::delete('supervisors/{supervisor}', [SupervisorController::class, 'destroy'])->name('supervisors.destroy');
        });

        // ===== طلبات التواصل =====
        Route::middleware('can:contact_requests.view')->group(function () {
            Route::get('requests', [ContactRequestController::class, 'index'])->name('requests.index');
            Route::put('requests/{contactRequest}/contacted', [ContactRequestController::class, 'markContacted'])->name('requests.contacted');
            Route::delete('requests/{contactRequest}', [ContactRequestController::class, 'destroy'])->name('requests.destroy');
        });

        // ===== العقارات =====
        Route::middleware('can:properties.view')->group(function () {
            Route::get('properties', [PropertyController::class, 'index'])->name('properties.index');
            Route::get('properties/create', [PropertyController::class, 'create'])->name('properties.create');
            Route::post('properties', [PropertyController::class, 'store'])->name('properties.store');
            Route::get('properties/{property}', [PropertyController::class, 'show'])->name('properties.show');
            Route::get('properties/{property}/edit', [PropertyController::class, 'edit'])->name('properties.edit');
            Route::put('properties/{property}', [PropertyController::class, 'update'])->name('properties.update');
            Route::delete('properties/{property}', [PropertyController::class, 'destroy'])->name('properties.destroy');
            Route::post('properties/{property}/reviews', [PropertyController::class, 'addReview'])->name('properties.reviews.store');
        });

        // ===== إدارة الأدوار =====
        Route::middleware('can:roles.view')->group(function () {
            Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
            Route::post('roles', [RoleController::class, 'store'])->name('roles.store');
            Route::put('roles/{role}', [RoleController::class, 'update'])->name('roles.update');
            Route::delete('roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
        });

        // ===== مصفوفة الصلاحيات =====
        Route::middleware('can:permissions.view')->group(function () {
            Route::get('permissions', [PermissionMatrixController::class, 'index'])->name('permissions.index');
            Route::put('permissions', [PermissionMatrixController::class, 'update'])->name('permissions.update');
        });

        // ===== إدارة الموقع (CMS) =====
        Route::middleware('can:website.view')->prefix('website')->name('website.')->group(function () {
            Route::get('/', [WebsiteController::class, 'index'])->name('index');
            Route::put('settings', [WebsiteController::class, 'updateSettings'])->name('settings');
            Route::put('homepage', [WebsiteController::class, 'updateHomepage'])->name('homepage');
            Route::put('about', [WebsiteController::class, 'updateAbout'])->name('about');
            Route::put('seo', [WebsiteController::class, 'updateSeo'])->name('seo');
            Route::put('legal/{slug}', [WebsiteController::class, 'updateLegal'])->name('legal');
            Route::put('listing/{slug}', [WebsiteController::class, 'updateListing'])->name('listing');
            Route::post('faqs', [WebsiteController::class, 'storeFaq'])->name('faqs.store');
            Route::put('faqs/{faq}', [WebsiteController::class, 'updateFaq'])->name('faqs.update');
            Route::delete('faqs/{faq}', [WebsiteController::class, 'destroyFaq'])->name('faqs.destroy');
            Route::post('testimonials', [WebsiteController::class, 'storeTestimonial'])->name('testimonials.store');
            Route::put('testimonials/{testimonial}', [WebsiteController::class, 'updateTestimonial'])->name('testimonials.update');
            Route::delete('testimonials/{testimonial}', [WebsiteController::class, 'destroyTestimonial'])->name('testimonials.destroy');
        });
    });
});
