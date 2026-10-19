<?php

namespace App\Support;

use App\Models\Admin;
use App\Models\AdminActivity;
use App\Models\Book;
use App\Models\ContactMessage;
use App\Models\Customer;
use App\Models\ExamSet;
use App\Models\HolidayPackage;
use App\Models\Order;
use App\Models\Paper;
use App\Models\SupportThread;

/**
 * Module 10: the real numbers behind the console shell, the dashboard, reports, team and activity pages.
 *  - shell():     the notification bell, the sidebar badges and the Ctrl K search, on every signed-in page (WK.shell)
 *  - dashboard(): "Needs your attention" and the monthly goal (WK.ins)
 *  - team():      members and the role table; activity(): the log lines
 * The charts and KPIs themselves are drawn in the browser from WK.sales (AdminSales), exactly as on the Orders page.
 */
class AdminInsights
{
    /** The log page shows this many of the newest lines (export and search work on these). */
    public const ACTIVITY_LIMIT = 1000;

    // -------------------------------------------------------------- the shell

    public static function shell(?Admin $admin): array
    {
        $pending   = Order::where('status', 'pending')->count();
        $unpaid    = self::unpaidCount();
        $messages  = self::newMessages();
        $low       = self::lowStock();
        $latest    = Order::orderByDesc('created_at')->orderByDesc('id')->first();

        $notes = [];
        if ($latest) {
            $notes[] = ['icon' => 'cart', 't' => 'New order ' . $latest->number, 's' => 'UGX ' . number_format($latest->total) . ' from ' . $latest->customer_name, 'ts' => $latest->created_at->getTimestampMs()];
        }
        if ($unpaid) {
            $notes[] = ['icon' => 'card', 't' => 'Payments waiting to be confirmed', 's' => $unpaid . ($unpaid === 1 ? ' order is' : ' orders are') . ' not paid yet', 'ts' => now()->getTimestampMs()];
        }
        if ($low) {
            $notes[] = ['icon' => 'box', 't' => 'Low stock warning', 's' => $low . ($low === 1 ? ' item is' : ' items are') . ' running low', 'ts' => now()->getTimestampMs()];
        }
        if ($messages) {
            $notes[] = ['icon' => 'mail', 't' => 'Messages waiting for an answer', 's' => $messages . ($messages === 1 ? ' message needs' : ' messages need') . ' a reply', 'ts' => now()->getTimestampMs()];
        }

        return [
            'badges'        => ['orders' => $pending, 'messages' => $messages],
            'notifications' => $notes,
            'search'        => self::search(),
        ];
    }

    /** What the Ctrl K box can find beyond the page names: titles, the newest orders and customers. */
    private static function search(): array
    {
        return [
            'books' => Book::with('subject:id,name')->orderBy('title')->limit(300)->get(['id', 'slug', 'title', 'subject_id'])
                ->map(fn (Book $b) => ['t' => $b->title, 's' => (string) ($b->subject->name ?? ''), 'href' => route('admin.book-form', ['id' => $b->slug])])->all(),
            'orders' => Order::orderByDesc('created_at')->orderByDesc('id')->limit(60)->get(['number', 'customer_name', 'total'])
                ->map(fn (Order $o) => ['t' => $o->number, 's' => $o->customer_name . ' · UGX ' . number_format($o->total), 'href' => route('admin.order', ['id' => $o->number])])->all(),
            'customers' => Customer::orderBy('name')->limit(200)->get(['name', 'type'])
                ->map(fn (Customer $c) => ['t' => $c->name, 's' => (string) $c->type, 'href' => route('admin.customers', ['q' => $c->name])])->all(),
        ];
    }

    // ---------------------------------------------------- shared small counts

    /** Orders still open that nobody has paid for. */
    public static function unpaidCount(): int
    {
        return Order::where('payment_status', 'pending')->whereNotIn('status', ['cancelled', 'refunded'])->count();
    }

    /** Contact messages not yet read plus customer questions still waiting (what the Messages page calls "new"). */
    public static function newMessages(): int
    {
        return ContactMessage::where('status', 'new')->count() + SupportThread::where('status', 'open')->count();
    }

    /** Products at or under their low-stock alert level. Printed papers only count while printed copies are on sale. */
    public static function lowStock(): int
    {
        return Book::published()->whereColumn('stock', '<=', 'low_stock_at')->count()
            + ExamSet::published()->whereColumn('stock', '<=', 'low_stock_at')->count()
            + HolidayPackage::published()->whereColumn('stock', '<=', 'low_stock_at')->count()
            + Paper::published()->where('print_on', true)->whereColumn('print_stock', '<=', 'low_stock_at')->count();
    }

    // ------------------------------------------------------------- dashboard

    public static function dashboard(): array
    {
        $noFile = Paper::published()->where('pdf_on', true)->where(fn ($q) => $q->whereNull('pdf_path')->orWhere('pdf_path', ''))->count();

        return [
            'goal'      => (int) SiteSettings::get('goal'),
            'attention' => [
                'pending'  => Order::where('status', 'pending')->count(),
                'unpaid'   => self::unpaidCount(),
                'low'      => self::lowStock(),
                'messages' => self::newMessages(),
                'noFile'   => $noFile,
            ],
            'routes'    => [
                'orders' => route('admin.orders'), 'payments' => route('admin.payments'), 'inventory' => route('admin.inventory'),
                'messages' => route('admin.messages'), 'papers' => route('admin.past-papers'), 'order' => route('admin.order'), 'activity' => route('admin.activity'),
            ],
            'activity'  => self::activity(6),
        ];
    }

    // --------------------------------------------------------------- reports

    public static function reports(?Admin $admin): array
    {
        return [
            'can'      => ['export' => (bool) $admin?->canExportReports()],
            'schedule' => ReportExport::schedule(),
            'routes'   => [
                'export'   => url('/admin/reports/export'),
                'schedule' => route('admin.reports.schedule'),
            ],
        ];
    }

    // ------------------------------------------------------------------ team

    public static function team(?Admin $admin): array
    {
        $members = Admin::orderBy('id')->get()->map(fn (Admin $a) => [
            'id'        => $a->id,
            'name'      => $a->name,
            'email'     => $a->email,
            'phone'     => (string) $a->phone,
            'role'      => $a->role,
            'roleLabel' => $a->role_label,
            'status'    => $a->status,
            'last'      => $a->last_login_at?->getTimestampMs() ?? 0,
            'invited'   => $a->invited_at?->getTimestampMs() ?? 0,
            'twoFactor' => (bool) $a->two_factor_enabled,
            'color'     => 'av-' . ($a->id % 6 + 1),
            'self'      => $admin && $admin->id === $a->id,
        ])->all();

        return [
            'members' => $members,
            'roles'   => Admin::ROLES,
            'matrix'  => Admin::permissionMatrix(),
            'can'     => ['manage' => (bool) $admin?->canManageTeam()],
            'routes'  => ['base' => url('/admin/team')],
        ];
    }

    // -------------------------------------------------------------- activity

    /** @return array<int, array> newest first, in the shape the page scripts use */
    public static function activity(int $limit = self::ACTIVITY_LIMIT): array
    {
        return AdminActivity::orderByDesc('id')->limit($limit)->get()->map(fn (AdminActivity $a) => [
            'id'     => $a->id,
            'ts'     => $a->created_at->getTimestampMs(),
            'user'   => ['name' => $a->admin_name, 'color' => 'av-' . (($a->admin_id ?? 0) % 6 + 1)],
            'verb'   => $a->verb,
            'obj'    => (string) $a->object,
            'module' => $a->area,
            'icon'   => $a->icon,
            'ip'     => (string) $a->ip,
        ])->all();
    }

    public static function activityPayload(): array
    {
        return ['rows' => self::activity(), 'areas' => Activity::AREAS];
    }
}
