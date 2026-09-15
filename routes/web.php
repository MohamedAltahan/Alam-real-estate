<?php

use App\Http\Controllers\Dashboard\AreaController;
use App\Http\Controllers\Dashboard\CityController;
use App\Http\Controllers\Dashboard\ClientController;
use App\Http\Controllers\Dashboard\ContactRequestController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Dashboard\FieldOwnerController;
use App\Http\Controllers\Dashboard\MarketingSourceController;
use App\Http\Controllers\Dashboard\NotificationController;
use App\Http\Controllers\Dashboard\PermissionMatrixController;
use App\Http\Controllers\Dashboard\ProfileSettingsController;
use App\Http\Controllers\Dashboard\PropertyController;
use App\Http\Controllers\Dashboard\PropertyOwnerController;
use App\Http\Controllers\Dashboard\PublishingChannelController;
use App\Http\Controllers\Dashboard\ReportController;
use App\Http\Controllers\Dashboard\RoleController;
use App\Http\Controllers\Dashboard\SupervisorController;
use App\Http\Controllers\Dashboard\TaskController;
use App\Http\Controllers\Dashboard\UnitTypeController;
use App\Http\Controllers\Dashboard\ViewingController;
use App\Http\Controllers\Dashboard\WebsiteController;
use App\Http\Controllers\Dashboard\WhatsAppController;
use App\Http\Controllers\Site\SiteController;
use App\Http\Controllers\Webhooks\KhabeerSoftWebhookController;
use Illuminate\Support\Facades\Route;

// ===== الموقع العام =====
Route::get('/', [SiteController::class, 'home'])->name('site.home');
Route::get('/locale/{locale}', [SiteController::class, 'switchLocale'])->name('site.locale');
Route::get('/properties', [SiteController::class, 'properties'])->name('site.properties');
Route::get('/properties/{property}', [SiteController::class, 'property'])->name('site.property');
Route::get('/agents/{agent}', [SiteController::class, 'agent'])->name('site.agent');
Route::get('/about', [SiteController::class, 'about'])->name('site.about');
Route::get('/contact', [SiteController::class, 'contact'])->name('site.contact');
Route::post('/contact', [SiteController::class, 'storeContact'])->middleware('throttle:5,1')->name('site.contact.store');
Route::get('/list-property', [SiteController::class, 'listProperty'])->name('site.list-property');
Route::post('/list-property', [SiteController::class, 'storeListProperty'])->middleware('throttle:5,1')->name('site.list-property.store');
Route::get('/faq', [SiteController::class, 'faq'])->name('site.faq');
Route::get('/terms', [SiteController::class, 'terms'])->name('site.terms');
Route::get('/privacy', [SiteController::class, 'privacy'])->name('site.privacy');

// ===== ويب هوك بوابة واتساب: حالة الرسائل (أُرسلت · وصلت · قُرئت) — بلا جلسة، موقَّع بـ HMAC =====
Route::post('webhooks/khabeersoft', KhabeerSoftWebhookController::class)->name('webhooks.khabeersoft');

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::prefix('dashboard')->name('dashboard.')->group(function () {

        // ===== إعدادات الحساب الشخصي =====
        Route::get('profile', [ProfileSettingsController::class, 'edit'])->name('profile.edit');
        Route::put('profile', [ProfileSettingsController::class, 'updateProfile'])->name('profile.update');
        Route::put('profile/avatar', [ProfileSettingsController::class, 'updateAvatar'])->name('profile.avatar');
        Route::put('profile/password', [ProfileSettingsController::class, 'updatePassword'])->name('profile.password');
        Route::put('profile/notifications', [ProfileSettingsController::class, 'updateNotifications'])->name('profile.notifications');
        Route::put('profile/preferences', [ProfileSettingsController::class, 'updatePreferences'])->name('profile.preferences');

        // ===== الإشعارات =====
        Route::middleware('can:notifications.view')->group(function () {
            // استطلاع كل دقيقة من المتصفح: يرسل تذكيرات المعاينات المستحقة ويعيد العدّادات
            Route::get('notifications/poll', [NotificationController::class, 'poll'])->name('notifications.poll');
            Route::get('notifications/{id}/open', [NotificationController::class, 'open'])->name('notifications.open');
            Route::post('notifications/read-mine', [NotificationController::class, 'readMine'])->name('notifications.read-mine');
        });

        // تعليم كل الإشعارات كمقروءة (من القائمة المنسدلة في الشريط العلوي)
        Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->middleware([
            'can:notifications.view',
            'can:notifications.edit',
            'can:contact_requests.view',
        ])->name('notifications.read-all');

        // ===== إدارة العملاء =====
        Route::middleware('can:clients.view')->group(function () {
            Route::get('clients', [ClientController::class, 'index'])->name('clients.index');
            Route::post('clients', [ClientController::class, 'store'])->name('clients.store');
            // قبل clients/{client} حتى لا يُفسَّر «property-lookup» كمعرّف عميل
            Route::get('clients/property-lookup', [ClientController::class, 'propertyLookup'])->name('clients.property-lookup');
            Route::get('clients/{client}', [ClientController::class, 'show'])->whereNumber('client')->name('clients.show');
            // محتوى نافذة «العقارات المستهدفة» في القائمة (XHR)
            Route::get('clients/{client}/viewings', [ClientController::class, 'viewingsPanel'])->whereNumber('client')->name('clients.viewings');
            Route::put('clients/{client}', [ClientController::class, 'update'])->name('clients.update');
            Route::delete('clients/{client}', [ClientController::class, 'destroy'])->name('clients.destroy');
            Route::post('clients/{client}/interactions', [ClientController::class, 'logInteraction'])->name('clients.interactions.store');

            // المعاينات — كل المواعيد مع فلاتر التاريخ والمسؤول
            Route::get('viewings', [ViewingController::class, 'index'])->name('viewings.index');
            Route::patch('viewings/{viewing}/outcome', [ViewingController::class, 'updateOutcome'])->name('viewings.outcome');
            Route::patch('viewings/{viewing}/notes', [ViewingController::class, 'updateNotes'])->name('viewings.notes');
            // إرسال تفاصيل المعاينة / نتيجتها لمسؤولي العقار عبر واتساب
            Route::post('viewings/{viewing}/whatsapp', [ViewingController::class, 'sendWhatsApp'])->name('viewings.whatsapp');
        });

        // ===== لوحة المهام =====
        Route::middleware('can:tasks.view')->group(function () {
            Route::get('tasks', [TaskController::class, 'index'])->name('tasks.index');
            Route::post('tasks', [TaskController::class, 'store'])->name('tasks.store');
            // قبل tasks/{task} حتى لا يُفسَّر «property-lookup» كمعرّف مهمة
            Route::get('tasks/property-lookup', [TaskController::class, 'propertyLookup'])->name('tasks.property-lookup');
            Route::get('tasks/{task}', [TaskController::class, 'show'])->whereNumber('task')->name('tasks.show');
            Route::put('tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
            Route::patch('tasks/{task}/move', [TaskController::class, 'move'])->name('tasks.move');
            Route::patch('tasks/{task}/assign', [TaskController::class, 'assign'])->name('tasks.assign');
            Route::post('tasks/{task}/comments', [TaskController::class, 'comment'])->name('tasks.comments.store');
            Route::delete('tasks/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');
        });

        // ===== التقارير =====
        Route::middleware('can:reports.view')->group(function () {
            Route::get('reports/conversion', [ReportController::class, 'conversion'])->name('reports.conversion');
            Route::get('reports/clients-conversion', [ReportController::class, 'clientsConversion'])->name('reports.clients-conversion');
            Route::get('reports/viewings', [ReportController::class, 'viewings'])->name('reports.viewings');
        });

        // ===== ملّاك العقارات =====
        Route::middleware('can:property_owners.view')->group(function () {
            Route::get('owners', [PropertyOwnerController::class, 'index'])->name('owners.index');
            Route::post('owners', [PropertyOwnerController::class, 'store'])->name('owners.store');
            Route::get('owners/{owner}', [PropertyOwnerController::class, 'show'])->name('owners.show');
            Route::put('owners/{owner}', [PropertyOwnerController::class, 'update'])->name('owners.update');
            Route::delete('owners/{owner}', [PropertyOwnerController::class, 'destroy'])->name('owners.destroy');
        });

        // ===== ميداني: زيارات ملاك عقارات جدد =====
        Route::middleware('can:field_owners.view')->group(function () {
            Route::get('field', [FieldOwnerController::class, 'index'])->name('field-owners.index');
            // قبل field/{fieldOwner} حتى لا يُفسَّر «create» كمعرّف زيارة
            Route::get('field/create', [FieldOwnerController::class, 'create'])->name('field-owners.create');
            Route::post('field', [FieldOwnerController::class, 'store'])->name('field-owners.store');
            Route::get('field/{fieldOwner}', [FieldOwnerController::class, 'show'])->whereNumber('fieldOwner')->name('field-owners.show');
            Route::get('field/{fieldOwner}/edit', [FieldOwnerController::class, 'edit'])->whereNumber('fieldOwner')->name('field-owners.edit');
            Route::put('field/{fieldOwner}', [FieldOwnerController::class, 'update'])->whereNumber('fieldOwner')->name('field-owners.update');
            Route::delete('field/{fieldOwner}', [FieldOwnerController::class, 'destroy'])->whereNumber('fieldOwner')->name('field-owners.destroy');
            // حفظ الزيارة كمالك حقيقي في شاشة الملاك
            Route::post('field/{fieldOwner}/convert-owner', [FieldOwnerController::class, 'convertOwner'])->whereNumber('fieldOwner')->name('field-owners.convert-owner');
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
            Route::get('requests/featured', [ContactRequestController::class, 'featured'])->name('requests.featured');
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
            // تغيير الحالة من عمود الحالة في الجدول
            Route::patch('properties/{property}/status', [PropertyController::class, 'updateStatus'])->name('properties.status');
            // قنوات النشر (مواقع/سوشال) التي نُشر عليها العقار
            Route::put('properties/{property}/channels', [PropertyController::class, 'updateChannels'])->name('properties.channels.update');
        });

        // ===== قنوات النشر: المواقع الإلكترونية والسوشال ميديا =====
        Route::middleware('can:publishing_channels.view')->group(function () {
            Route::get('websites', [PublishingChannelController::class, 'index'])->name('websites.index');
            Route::post('websites', [PublishingChannelController::class, 'store'])->name('websites.store');
            Route::put('websites/{channel}', [PublishingChannelController::class, 'update'])->name('websites.update');
            Route::delete('websites/{channel}', [PublishingChannelController::class, 'destroy'])->name('websites.destroy');

            Route::get('social-channels', [PublishingChannelController::class, 'index'])->name('social-channels.index');
            Route::post('social-channels', [PublishingChannelController::class, 'store'])->name('social-channels.store');
            Route::put('social-channels/{channel}', [PublishingChannelController::class, 'update'])->name('social-channels.update');
            Route::delete('social-channels/{channel}', [PublishingChannelController::class, 'destroy'])->name('social-channels.destroy');
        });

        // ===== واتساب: ربط رقم المكتب، قوالب الرسائل، سجل الرسائل =====
        Route::middleware('can:whatsapp.view')->prefix('whatsapp')->name('whatsapp.')->group(function () {
            Route::get('/', [WhatsAppController::class, 'index'])->name('index');
            Route::post('connect', [WhatsAppController::class, 'connect'])->name('connect');
            Route::get('qr', [WhatsAppController::class, 'qr'])->name('qr');
            Route::post('disconnect', [WhatsAppController::class, 'disconnect'])->name('disconnect');
            Route::put('templates/{kind}', [WhatsAppController::class, 'updateTemplate'])->name('templates.update');
            // زر «تحديث الحالات» في سجل الرسائل
            Route::post('messages/refresh', [WhatsAppController::class, 'refreshStatuses'])->name('messages.refresh');
        });

        // ===== إدارة المناطق والمدن (صلاحيات المناطق نفسها) =====
        Route::middleware('can:areas.view')->group(function () {
            Route::get('areas', [AreaController::class, 'index'])->name('areas.index');
            Route::post('areas', [AreaController::class, 'store'])->name('areas.store');
            Route::put('areas/{area}', [AreaController::class, 'update'])->name('areas.update');
            Route::delete('areas/{area}', [AreaController::class, 'destroy'])->name('areas.destroy');

            Route::get('cities', [CityController::class, 'index'])->name('cities.index');
            Route::post('cities', [CityController::class, 'store'])->name('cities.store');
            Route::put('cities/{city}', [CityController::class, 'update'])->name('cities.update');
            Route::delete('cities/{city}', [CityController::class, 'destroy'])->name('cities.destroy');
        });

        // ===== إدارة أنواع العقارات =====
        Route::middleware('can:unit_types.view')->group(function () {
            Route::get('unit-types', [UnitTypeController::class, 'index'])->name('unit-types.index');
            Route::post('unit-types', [UnitTypeController::class, 'store'])->name('unit-types.store');
            Route::put('unit-types/{unitType}', [UnitTypeController::class, 'update'])->name('unit-types.update');
            Route::delete('unit-types/{unitType}', [UnitTypeController::class, 'destroy'])->name('unit-types.destroy');
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
