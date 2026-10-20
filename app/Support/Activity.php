<?php

namespace App\Support;

use App\Models\Admin;
use App\Models\AdminActivity;
use App\Models\Paper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * The activity log (module 10).
 *
 * Nothing in the controllers has to remember to log: LoggedAdmin hands every finished admin request to record(), which looks
 * the route up in map() and writes one line when the change went through ("Brenda  Updated book  UCE Biology Workbook").
 * Requests that failed (validation, a rule, a 4xx or 5xx) are not logged, and neither is merely looking at a page. The only
 * pages that are logged are the downloads of customer and sales data (READS), because they carry personal details.
 */
class Activity
{
    /** Area key => label (the Area filter on the Activity log page). */
    public const AREAS = [
        'book'       => 'Books',
        'paper'      => 'Past papers',
        'set'        => 'Exam sets',
        'package'    => 'Holiday packages',
        'taxonomy'   => 'Subjects and levels',
        'media'      => 'Media',
        'stock'      => 'Stock',
        'order'      => 'Orders',
        'payment'    => 'Payments',
        'delivery'   => 'Deliveries',
        'customer'   => 'Customers',
        'msg'        => 'Messages',
        'subscriber' => 'Subscribers',
        'content'    => 'Site content',
        'setting'    => 'Settings',
        'report'     => 'Reports',
        'team'       => 'Team',
        'auth'       => 'Sign-ins',
    ];

    /** Pages (GET) that are logged because they hand out data. */
    private const READS = ['settings.export', 'reports.export'];

    /** Write one line. Never throws: a broken log must not break the change that was just saved. */
    public static function log(?Admin $admin, string $area, string $icon, string $verb, ?string $object = null, ?string $ip = null, ?string $name = null): void
    {
        try {
            AdminActivity::create([
                'admin_id'   => $admin?->id,
                'admin_name' => $admin?->name ?? $name ?? 'System',
                'area'       => $area,
                'icon'       => $icon,
                'verb'       => Str::limit($verb, 120, ''),
                'object'     => $object !== null && $object !== '' ? Str::limit($object, 250, '') : null,
                'ip'         => $ip ?? request()->ip(),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** Called by LoggedAdmin after every signed-in admin request. */
    public static function record(Request $request, Response $response): void
    {
        try {
            $admin = $request->attributes->get('admin');
            $route = $request->route();
            $name  = $route?->getName();

            if (! $admin || ! $name) {
                return;
            }

            $name  = Str::after($name, 'admin.');
            $entry = self::map()[$name] ?? null;
            $read  = in_array($request->method(), ['GET', 'HEAD'], true);

            if (! $entry || $read !== in_array($name, self::READS, true)) {
                return;
            }

            if ($response->getStatusCode() >= 400) {
                return;
            }

            // A redirect that carries an error or a "fail" message means the change was refused
            if ($response instanceof RedirectResponse && $request->hasSession()) {
                $flashed = (array) $request->session()->get('_flash.new', []);
                if (array_intersect(['errors', 'fail'], $flashed)) {
                    return;
                }
            }

            [$area, $icon, $verb, $object] = $entry + [3 => null];
            $text = $object ? $object($request) : null;

            self::log($admin, $area, $icon, $verb, $text, $request->ip());
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Route name (without "admin.") => [area, icon, verb, how to name the thing that changed].
     *
     * @return array<string, array{0:string,1:string,2:string,3:?\Closure}>
     */
    private static function map(): array
    {
        $param = fn (string $key) => fn (Request $r) => self::label($r->route($key));
        $input = fn (string ...$keys) => function (Request $r) use ($keys) {
            foreach ($keys as $k) {
                $v = $r->input($k);
                if (is_scalar($v) && trim((string) $v) !== '') {
                    return trim((string) $v);
                }
            }

            return null;
        };
        $bulk = fn (string $noun) => function (Request $r) use ($noun) {
            $n = count((array) $r->input('ids', []));

            return trim(($r->input('action') ? ucfirst((string) $r->input('action')) . ': ' : '') . $n . ' ' . $noun . ($n === 1 ? '' : 's'));
        };

        return [
            // Catalogue (module 7)
            'books.store'            => ['book', 'plus', 'Added book', $input('title')],
            'books.update'           => ['book', 'edit', 'Updated book', $param('book')],
            'books.destroy'          => ['book', 'trash', 'Deleted book', $param('book')],
            'books.duplicate'        => ['book', 'copy', 'Duplicated book', $param('book')],
            'books.bulk'             => ['book', 'edit', 'Bulk change on books', $bulk('book')],
            'papers.store'           => ['paper', 'plus', 'Added past paper', $input('title', 'subject')],
            'papers.update'          => ['paper', 'edit', 'Updated past paper', $param('paper')],
            'papers.quick'           => ['paper', 'edit', 'Quick-edited past paper', $param('paper')],
            'papers.upload'          => ['paper', 'upload', 'Uploaded the PDF for', $param('paper')],
            'papers.destroy'         => ['paper', 'trash', 'Deleted past paper', $param('paper')],
            'papers.bulk'            => ['paper', 'edit', 'Bulk change on past papers', $bulk('paper')],
            'papers.bulk-upload'     => ['paper', 'upload', 'Bulk uploaded paper files', null],
            'papers.defaults'        => ['paper', 'settings', 'Changed the default paper prices', null],
            'exam-sets.store'        => ['set', 'plus', 'Added exam set', $input('title')],
            'exam-sets.update'       => ['set', 'edit', 'Updated exam set', $param('set')],
            'exam-sets.destroy'      => ['set', 'trash', 'Deleted exam set', $param('set')],
            'packages.store'         => ['package', 'plus', 'Added holiday package', $input('name', 'title')],
            'packages.update'        => ['package', 'edit', 'Updated holiday package', $param('package')],
            'packages.destroy'       => ['package', 'trash', 'Deleted holiday package', $param('package')],
            'taxonomy.levels'        => ['taxonomy', 'edit', 'Updated the levels', null],
            'taxonomy.lists'         => ['taxonomy', 'edit', 'Updated book types and badges', null],
            'taxonomy.subjects.store'   => ['taxonomy', 'plus', 'Added subject', $input('name')],
            'taxonomy.subjects.update'  => ['taxonomy', 'edit', 'Updated subject', $param('subject')],
            'taxonomy.subjects.destroy' => ['taxonomy', 'trash', 'Deleted subject', $param('subject')],
            'media.store'            => ['media', 'upload', 'Uploaded an image', null],
            'media.replace'          => ['media', 'image', 'Replaced an image', null],
            'media.destroy'          => ['media', 'trash', 'Deleted an image', null],
            'inventory.adjust'       => ['stock', 'box', 'Adjusted stock', $input('name', 'slug', 'item')],
            'inventory.alert'        => ['stock', 'box', 'Changed a low-stock alert level', $input('name', 'slug', 'item')],

            // Sales (module 8)
            'orders.store'           => ['order', 'cart', 'Created an order for', $input('name')],
            'orders.bulk'            => ['order', 'cart', 'Bulk change on orders', $bulk('order')],
            'orders.status'          => ['order', 'check', 'Changed the status of order', fn (Request $r) => trim(self::label($r->route('order')) . ' to ' . $r->input('status'))],
            'orders.note'            => ['order', 'edit', 'Added a note to order', $param('order')],
            'orders.courier'         => ['order', 'truck', 'Saved delivery details for order', $param('order')],
            'orders.email'           => ['order', 'mail', 'Emailed the customer of order', $param('order')],
            'orders.files'           => ['order', 'mail', 'Emailed the PDF files of order', $param('order')],
            'payments.verify'        => ['payment', 'check', 'Confirmed payment for order', $param('order')],
            'payments.refund'        => ['payment', 'refresh', 'Refunded order', $param('order')],
            'payments.methods'       => ['payment', 'card', 'Changed the payment methods', null],
            'payments.reconcile'     => ['payment', 'check', 'Reconciled payments', null],
            'deliveries.options'     => ['delivery', 'truck', 'Changed the delivery options and fees', null],
            'customers.store'        => ['customer', 'plus', 'Added customer', $input('name')],
            'customers.note'         => ['customer', 'edit', 'Added a note to customer', $param('customer')],

            // Engagement and site content (module 9)
            'messages.reply'         => ['msg', 'mail', 'Replied to message', $param('ref')],
            'messages.quote'         => ['msg', 'mail', 'Sent a quotation for message', $param('ref')],
            'messages.status'        => ['msg', 'edit', 'Changed the status of message', $param('ref')],
            'messages.destroy'       => ['msg', 'trash', 'Deleted message', $param('ref')],
            'subscribers.store'      => ['subscriber', 'plus', 'Added subscriber', $input('email')],
            'subscribers.bulk'       => ['subscriber', 'edit', 'Bulk change on subscribers', $bulk('subscriber')],
            'subscribers.send'       => ['subscriber', 'mail', 'Sent the newsletter', $input('subject')],
            'subscribers.status'     => ['subscriber', 'edit', 'Changed subscriber', $param('subscriber')],
            'subscribers.destroy'    => ['subscriber', 'trash', 'Deleted subscriber', $param('subscriber')],
            'content.save'           => ['content', 'globe', 'Edited the website content', null],
            'testimonials.store'     => ['content', 'quote', 'Added a testimonial from', $input('initials', 'role')],
            'testimonials.update'    => ['content', 'quote', 'Updated testimonial', $param('testimonial')],
            'testimonials.toggle'    => ['content', 'quote', 'Showed or hid testimonial', $param('testimonial')],
            'testimonials.destroy'   => ['content', 'trash', 'Deleted testimonial', $param('testimonial')],
            'faqs.store'             => ['content', 'plus', 'Added question', $input('question', 'q')],
            'faqs.update'            => ['content', 'edit', 'Updated question', $param('faq')],
            'faqs.toggle'            => ['content', 'edit', 'Showed or hid question', $param('faq')],
            'faqs.reorder'           => ['content', 'edit', 'Reordered the questions', null],
            'faqs.destroy'           => ['content', 'trash', 'Deleted question', $param('faq')],
            'settings.general'       => ['setting', 'settings', 'Changed the general settings', null],
            'settings.downloads'     => ['setting', 'settings', 'Changed the PDF download limit', null],
            'settings.notifications' => ['setting', 'settings', 'Changed the email alerts', null],
            'settings.integrations'  => ['setting', 'settings', 'Changed the integrations', null],
            'settings.export'        => ['setting', 'download', 'Downloaded an export of', fn (Request $r) => (string) $r->route('what')],

            // My profile (module 11)
            'profile.update'         => ['auth', 'user', 'Updated their profile', null],
            'profile.photo'          => ['auth', 'image', 'Changed their profile photo', null],
            'profile.photo.remove'   => ['auth', 'image', 'Removed their profile photo', null],
            'profile.password'       => ['auth', 'key', 'Changed their password', null],
            'profile.notifications'  => ['auth', 'mail', 'Changed their email alerts', null],

            // Insights and system (module 10)
            'reports.export'         => ['report', 'download', 'Downloaded report', fn (Request $r) => trim($r->route('report') . ' (' . $r->route('format') . ')')],
            'reports.schedule'       => ['report', 'calendar', 'Changed the emailed report', null],
            'team.store'             => ['team', 'plus', 'Invited team member', $input('name')],
            'team.update'            => ['team', 'edit', 'Changed team member', $param('member')],
            'team.invite'            => ['team', 'mail', 'Sent the invitation again to', $param('member')],
            'team.destroy'           => ['team', 'trash', 'Removed team member', $param('member')],
            'two-factor'             => ['auth', 'shield', 'Changed two-step verification', fn (Request $r) => $r->boolean('enabled') ? 'on' : 'off'],
        ];
    }

    /** A short name for a route parameter: a model's title, name, number ... or the plain value. */
    private static function label(mixed $value): ?string
    {
        if ($value instanceof Model) {
            foreach (['title', 'name', 'number', 'question', 'email', 'ref', 'initials'] as $field) {
                $text = $value->getAttribute($field);
                if (is_scalar($text) && trim((string) $text) !== '') {
                    return trim((string) $text) . ($value instanceof Paper && $value->year ? ' ' . $value->year : '');
                }
            }

            return class_basename($value) . ' #' . $value->getKey();
        }

        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }
}
