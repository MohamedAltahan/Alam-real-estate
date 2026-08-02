<?php

use App\Http\Controllers\Dashboard\ClientController;
use App\Http\Controllers\Dashboard\ContactRequestController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Dashboard\MarketingSourceController;
use App\Http\Controllers\Dashboard\PermissionMatrixController;
use App\Http\Controllers\Dashboard\PropertyController;
use App\Http\Controllers\Dashboard\PropertyOwnerController;
use App\Http\Controllers\Dashboard\RoleController;
use App\Http\Controllers\Dashboard\SupervisorController;
use App\Http\Controllers\Dashboard\WebsiteController;
use App\Http\Controllers\Site\SiteController;
use App\Models\ContactRequest;
use Illuminate\Support\Facades\Route;

// ===== الموقع العام =====
Route::get('/', [SiteController::class, 'home'])->name('site.home');
Route::get('/locale/{locale}', [SiteController::class, 'switchLocale'])->name('site.locale');
Route::get('/properties', [SiteController::class, 'properties'])->name('site.properties');
Route::get('/properties/{property}', [SiteController::class, 'property'])->name('site.property');
Route::get('/agents/{agent}', [SiteController::class, 'agent'])->name('site.agent');
Route::get('/about', [SiteController::class, 'about'])->name('site.about');
Route::get('/contact', [SiteController::class, 'contact'])->name('site.contact');
Route::post('/contact', [SiteController::class, 'storeContact'])->name('site.contact.store');
Route::get('/list-property', [SiteController::class, 'listProperty'])->name('site.list-property');
Route::post('/list-property', [SiteController::class, 'storeListProperty'])->name('site.list-property.store');
Route::get('/faq', [SiteController::class, 'faq'])->name('site.faq');
Route::get('/terms', [SiteController::class, 'terms'])->name('site.terms');
Route::get('/privacy', [SiteController::class, 'privacy'])->name('site.privacy');

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::prefix('dashboard')->name('dashboard.')->group(function () {

        // تعليم كل الإشعارات كمقروءة (من القائمة المنسدلة في الشريط العلوي)
        Route::post('notifications/read-all', function () {
            ContactRequest::unread()->update(['is_read' => true]);

            return back();
        })->name('notifications.read-all');

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
            Route::post('requests/{contactRequest}/convert', [ContactRequestController::class, 'convert'])->name('requests.convert');
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
