<?php

use App\Http\Controllers\Admin\ActivityController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\BookController;
use App\Http\Controllers\Admin\ContentController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DeliveryController;
use App\Http\Controllers\Admin\ExamSetController;
use App\Http\Controllers\Admin\FaqController;
use App\Http\Controllers\Admin\InventoryController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\MessageController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\PackageController;
use App\Http\Controllers\Admin\PaperController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\SubscriberController;
use App\Http\Controllers\Admin\TeamController;
use App\Http\Controllers\Admin\TaxonomyController;
use App\Http\Controllers\Admin\TestimonialController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin console (module 2): static views inside one shared layout
|--------------------------------------------------------------------------
| Module 3: sign-in pages sit behind admin.guest, everything else behind
| admin.auth (the custom LoggedAdmin session middleware).
| The pages keep the prototype's file names (book-form, paper-form ...) so the
| prototype JS links keep working; later modules swap them for resource routes.
| Module 7: the catalogue pages (books, past papers, exam sets, packages, subjects and levels, media, inventory)
| are real. Looking is open to every signed-in admin; saving, deleting and uploading also needs admin.catalogue
| (super admin, manager or editor).
| Module 10: the dashboard, reports, team and activity log are real. Looking is open to every signed-in admin; downloading reports needs
| admin.can:reports (super admin or manager) and managing the team needs admin.can:team (super admin).
| Module 8: the sales pages (orders, order, payments, deliveries, customers) are real. Looking is open to every signed-in admin;
| changing orders, deliveries and customers needs admin.can:sales (super admin, manager or support); handling money
| (confirm and refund payments, payment methods, delivery fees) needs admin.can:payments (super admin or manager).
*/
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/', fn () => redirect()->route('admin.dashboard'))->name('home');

    // Sign-in screens: only for people who are not signed in yet
    Route::middleware('admin.guest')->group(function () {
        Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
        Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
        Route::get('/forgot-password', [AuthController::class, 'showForgot'])->name('forgot-password');
        Route::post('/forgot-password', [AuthController::class, 'sendReset'])->name('forgot-password.send');
        Route::get('/reset-password', [AuthController::class, 'showReset'])->name('reset-password');
        Route::post('/reset-password', [AuthController::class, 'reset'])->name('reset-password.update');
        Route::get('/verify', [AuthController::class, 'showVerify'])->name('verify');
        Route::post('/verify', [AuthController::class, 'verify'])->name('verify.submit');
        Route::post('/verify/resend', [AuthController::class, 'resendOtp'])->name('verify.resend');
    });

    // Sign out works even when the session has expired (the CSRF check still applies)
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Everything below needs a signed-in admin
    Route::middleware('admin.auth')->group(function () {
        Route::post('/lock', [AuthController::class, 'lock'])->name('lock');
        Route::get('/locked', [AuthController::class, 'showLocked'])->name('locked');
        Route::post('/unlock', [AuthController::class, 'unlock'])->name('unlock');
        Route::post('/security/two-factor', [AuthController::class, 'twoFactor'])->name('two-factor');

        // Pages that use the sidebar + topbar shell
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        // ---- Catalogue (module 7): looking ----
        Route::get('/books', [BookController::class, 'index'])->name('books');
        Route::get('/book-form', [BookController::class, 'form'])->name('book-form');
        Route::get('/past-papers', [PaperController::class, 'index'])->name('past-papers');
        Route::get('/paper-form', [PaperController::class, 'form'])->name('paper-form');
        Route::get('/papers/{paper:slug}/file', [PaperController::class, 'file'])->name('papers.file');
        Route::get('/exam-sets', [ExamSetController::class, 'index'])->name('exam-sets');
        Route::get('/set-form', [ExamSetController::class, 'form'])->name('set-form');
        Route::get('/packages', [PackageController::class, 'index'])->name('packages');
        Route::get('/package-form', [PackageController::class, 'form'])->name('package-form');
        Route::get('/taxonomy', [TaxonomyController::class, 'index'])->name('taxonomy');
        Route::get('/media', [MediaController::class, 'index'])->name('media');
        Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory');

        // ---- Catalogue (module 7): changing ----
        Route::middleware('admin.catalogue')->group(function () {
            Route::post('/books', [BookController::class, 'store'])->name('books.store');
            Route::post('/books/bulk', [BookController::class, 'bulk'])->name('books.bulk');
            Route::put('/books/{book:slug}', [BookController::class, 'update'])->name('books.update');
            Route::delete('/books/{book:slug}', [BookController::class, 'destroy'])->name('books.destroy');
            Route::post('/books/{book:slug}/duplicate', [BookController::class, 'duplicate'])->name('books.duplicate');

            Route::post('/papers', [PaperController::class, 'store'])->name('papers.store');
            Route::post('/papers/bulk', [PaperController::class, 'bulk'])->name('papers.bulk');
            Route::post('/papers/defaults', [PaperController::class, 'defaults'])->name('papers.defaults');
            Route::post('/papers/bulk-upload', [PaperController::class, 'bulkUpload'])->name('papers.bulk-upload');
            Route::put('/papers/{paper:slug}', [PaperController::class, 'update'])->name('papers.update');
            Route::patch('/papers/{paper:slug}/quick', [PaperController::class, 'quick'])->name('papers.quick');
            Route::post('/papers/{paper:slug}/file', [PaperController::class, 'uploadFile'])->name('papers.upload');
            Route::delete('/papers/{paper:slug}', [PaperController::class, 'destroy'])->name('papers.destroy');

            Route::post('/exam-sets', [ExamSetController::class, 'store'])->name('exam-sets.store');
            Route::put('/exam-sets/{set:slug}', [ExamSetController::class, 'update'])->name('exam-sets.update');
            Route::delete('/exam-sets/{set:slug}', [ExamSetController::class, 'destroy'])->name('exam-sets.destroy');

            Route::post('/packages', [PackageController::class, 'store'])->name('packages.store');
            Route::put('/packages/{package:slug}', [PackageController::class, 'update'])->name('packages.update');
            Route::delete('/packages/{package:slug}', [PackageController::class, 'destroy'])->name('packages.destroy');

            Route::put('/taxonomy/levels', [TaxonomyController::class, 'levels'])->name('taxonomy.levels');
            Route::put('/taxonomy/lists', [TaxonomyController::class, 'lists'])->name('taxonomy.lists');
            Route::post('/taxonomy/subjects', [TaxonomyController::class, 'storeSubject'])->name('taxonomy.subjects.store');
            Route::put('/taxonomy/subjects/{subject}', [TaxonomyController::class, 'updateSubject'])->whereNumber('subject')->name('taxonomy.subjects.update');
            Route::delete('/taxonomy/subjects/{subject}', [TaxonomyController::class, 'destroySubject'])->whereNumber('subject')->name('taxonomy.subjects.destroy');

            Route::post('/media', [MediaController::class, 'store'])->name('media.store');
            Route::post('/media/replace', [MediaController::class, 'replace'])->name('media.replace');
            Route::post('/media/delete', [MediaController::class, 'destroy'])->name('media.destroy');

            Route::post('/inventory/adjust', [InventoryController::class, 'adjust'])->name('inventory.adjust');
            Route::post('/inventory/alert', [InventoryController::class, 'alert'])->name('inventory.alert');
        });

        // ---- Engagement and site content (module 9): looking ----
        Route::get('/messages', [MessageController::class, 'index'])->name('messages');
        Route::get('/subscribers', [SubscriberController::class, 'index'])->name('subscribers');
        Route::get('/testimonials', [TestimonialController::class, 'index'])->name('testimonials');
        Route::get('/faqs', [FaqController::class, 'index'])->name('faqs');
        Route::get('/content', [ContentController::class, 'index'])->name('content');
        Route::get('/settings', [SettingsController::class, 'index'])->name('settings');

        // ---- Messages and subscribers: super admin, manager or support ----
        Route::middleware('admin.can:inbox')->group(function () {
            Route::post('/messages/{ref}/reply', [MessageController::class, 'reply'])->where('ref', '[MT]-[0-9]+')->name('messages.reply');
            Route::post('/messages/{ref}/status', [MessageController::class, 'status'])->where('ref', '[MT]-[0-9]+')->name('messages.status');
            Route::post('/messages/{ref}/read', [MessageController::class, 'read'])->where('ref', '[MT]-[0-9]+')->name('messages.read');
            Route::post('/messages/{ref}/quote', [MessageController::class, 'quote'])->where('ref', '[MT]-[0-9]+')->name('messages.quote');
            Route::delete('/messages/{ref}', [MessageController::class, 'destroy'])->where('ref', '[MT]-[0-9]+')->name('messages.destroy');

            Route::post('/subscribers', [SubscriberController::class, 'store'])->name('subscribers.store');
            Route::post('/subscribers/bulk', [SubscriberController::class, 'bulk'])->name('subscribers.bulk');
            Route::post('/subscribers/send', [SubscriberController::class, 'send'])->name('subscribers.send');
            Route::post('/subscribers/{subscriber}/status', [SubscriberController::class, 'status'])->whereNumber('subscriber')->name('subscribers.status');
            Route::delete('/subscribers/{subscriber}', [SubscriberController::class, 'destroy'])->whereNumber('subscriber')->name('subscribers.destroy');
        });

        // ---- Website words, testimonials, questions: super admin, manager or editor ----
        Route::middleware('admin.can:content')->group(function () {
            Route::post('/content', [ContentController::class, 'save'])->name('content.save');

            Route::post('/testimonials', [TestimonialController::class, 'store'])->name('testimonials.store');
            Route::put('/testimonials/{testimonial}', [TestimonialController::class, 'update'])->whereNumber('testimonial')->name('testimonials.update');
            Route::post('/testimonials/{testimonial}/toggle', [TestimonialController::class, 'toggle'])->whereNumber('testimonial')->name('testimonials.toggle');
            Route::delete('/testimonials/{testimonial}', [TestimonialController::class, 'destroy'])->whereNumber('testimonial')->name('testimonials.destroy');

            Route::post('/faqs', [FaqController::class, 'store'])->name('faqs.store');
            Route::post('/faqs/reorder', [FaqController::class, 'reorder'])->name('faqs.reorder');
            Route::put('/faqs/{faq}', [FaqController::class, 'update'])->whereNumber('faq')->name('faqs.update');
            Route::post('/faqs/{faq}/toggle', [FaqController::class, 'toggle'])->whereNumber('faq')->name('faqs.toggle');
            Route::delete('/faqs/{faq}', [FaqController::class, 'destroy'])->whereNumber('faq')->name('faqs.destroy');
        });

        // ---- Settings: super admin or manager ----
        Route::middleware('admin.can:settings')->group(function () {
            Route::get('/settings/export/{what}', [SettingsController::class, 'export'])->where('what', 'orders|customers|subscribers|books')->name('settings.export');
            Route::post('/settings/general', [SettingsController::class, 'general'])->name('settings.general');
            Route::post('/settings/downloads', [SettingsController::class, 'downloads'])->name('settings.downloads');
            Route::post('/settings/notifications', [SettingsController::class, 'notifications'])->name('settings.notifications');
            Route::post('/settings/integrations', [SettingsController::class, 'integrations'])->name('settings.integrations');
        });

        // ---- Sales (module 8): looking ----
        Route::get('/orders', [OrderController::class, 'index'])->name('orders');
        Route::get('/order', [OrderController::class, 'show'])->name('order');
        Route::get('/payments', [PaymentController::class, 'index'])->name('payments');
        Route::get('/deliveries', [DeliveryController::class, 'index'])->name('deliveries');
        Route::get('/customers', [CustomerController::class, 'index'])->name('customers');

        // ---- Sales (module 8): orders, deliveries and customers ----
        Route::middleware('admin.can:sales')->group(function () {
            Route::post('/orders', [OrderController::class, 'store'])->name('orders.store');
            Route::post('/orders/bulk', [OrderController::class, 'bulk'])->name('orders.bulk');
            Route::post('/orders/{order:number}/status', [OrderController::class, 'status'])->name('orders.status');
            Route::post('/orders/{order:number}/note', [OrderController::class, 'note'])->name('orders.note');
            Route::post('/orders/{order:number}/courier', [OrderController::class, 'courier'])->name('orders.courier');
            Route::post('/orders/{order:number}/email', [OrderController::class, 'email'])->name('orders.email');
            Route::post('/orders/{order:number}/files', [OrderController::class, 'files'])->name('orders.files');

            Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
            Route::post('/customers/{customer}/note', [CustomerController::class, 'note'])->whereNumber('customer')->name('customers.note');
        });

        // ---- Sales (module 8): money ----
        Route::middleware('admin.can:payments')->group(function () {
            Route::post('/orders/{order:number}/payment/verify', [PaymentController::class, 'verify'])->name('payments.verify');
            Route::post('/orders/{order:number}/payment/refund', [PaymentController::class, 'refund'])->name('payments.refund');
            Route::put('/payments/methods', [PaymentController::class, 'methods'])->name('payments.methods');
            Route::post('/payments/reconcile', [PaymentController::class, 'reconcile'])->name('payments.reconcile');
            Route::put('/deliveries/options', [DeliveryController::class, 'options'])->name('deliveries.options');
        });

        // ---- Insights and system (module 10): looking is open to every signed-in admin ----
        Route::get('/reports', [ReportController::class, 'index'])->name('reports');
        Route::get('/team', [TeamController::class, 'index'])->name('team');
        Route::get('/activity', [ActivityController::class, 'index'])->name('activity');

        // Downloads and the emailed report carry customer details: super admin or manager
        Route::middleware('admin.can:reports')->group(function () {
            Route::get('/reports/export/{report}.{format}', [ReportController::class, 'export'])
                ->where('report', 'sales|orders|products|customers|payments|stock')->where('format', 'csv|pdf')->name('reports.export');
            Route::post('/reports/schedule', [ReportController::class, 'schedule'])->name('reports.schedule');
        });

        // Inviting, changing and removing team members: super admin only
        Route::middleware('admin.can:team')->group(function () {
            Route::post('/team', [TeamController::class, 'store'])->name('team.store');
            Route::put('/team/{member}', [TeamController::class, 'update'])->whereNumber('member')->name('team.update');
            Route::post('/team/{member}/invite', [TeamController::class, 'invite'])->whereNumber('member')->name('team.invite');
            Route::delete('/team/{member}', [TeamController::class, 'destroy'])->whereNumber('member')->name('team.destroy');
        });

        Route::view('/profile', 'admin.profile')->name('profile');
    });

    // Prototype links (books.html, order.html?id=...) keep working and land on the new URLs.
    Route::get('/{legacy}.html', function (Request $request, string $legacy) {
        $map = [
            'index' => 'admin.dashboard',
            'books' => 'admin.books',
            'book-form' => 'admin.book-form',
            'past-papers' => 'admin.past-papers',
            'paper-form' => 'admin.paper-form',
            'exam-sets' => 'admin.exam-sets',
            'set-form' => 'admin.set-form',
            'packages' => 'admin.packages',
            'package-form' => 'admin.package-form',
            'taxonomy' => 'admin.taxonomy',
            'media' => 'admin.media',
            'inventory' => 'admin.inventory',
            'orders' => 'admin.orders',
            'order' => 'admin.order',
            'payments' => 'admin.payments',
            'deliveries' => 'admin.deliveries',
            'customers' => 'admin.customers',
            'messages' => 'admin.messages',
            'subscribers' => 'admin.subscribers',
            'testimonials' => 'admin.testimonials',
            'faqs' => 'admin.faqs',
            'content' => 'admin.content',
            'reports' => 'admin.reports',
            'team' => 'admin.team',
            'activity' => 'admin.activity',
            'settings' => 'admin.settings',
            'profile' => 'admin.profile',
            'login' => 'admin.login',
            'forgot-password' => 'admin.forgot-password',
            'reset-password' => 'admin.reset-password',
            'verify' => 'admin.verify',
            'locked' => 'admin.locked',
        ];
        $url = route($map[$legacy]);
        $qs  = $request->getQueryString();

        return redirect()->to($qs ? $url . '?' . $qs : $url, 301);
    })->where('legacy', 'index|books|book-form|past-papers|paper-form|exam-sets|set-form|packages|package-form|taxonomy|media|inventory|orders|order|payments|deliveries|customers|messages|subscribers|testimonials|faqs|content|reports|team|activity|settings|profile|login|forgot-password|reset-password|verify|locked');

    // Anything else under /admin shows the admin 404 page
    Route::get('/{any}', fn () => response()->view('admin.errors.404', [], 404))->where('any', '.*');
});
