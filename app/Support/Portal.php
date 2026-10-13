<?php

namespace App\Support;

use App\Models\Customer;
use App\Models\Order;
use App\Models\SupportThread;
use Illuminate\Support\Collection;

/**
 * Everything the customer portal pages need, read from the database for ONE signed-in customer and handed to the
 * page scripts as WK.portal (see partials/account/auth-state). The shapes are the ones the prototype's sample data
 * layer used (orders, files, messages, addresses ...), so the pages draw exactly what they drew before, now from
 * real rows.
 */
class Portal
{
    /** The orders list shows the latest orders; older ones stay in the database (and in the library). */
    private const ORDER_LIMIT = 300;

    public static function for(Customer $customer, ?string $focus = null): array
    {
        $orders   = self::orders($customer);
        $files    = Library::forCustomer($customer);
        $messages = self::messages($customer);

        return [
            'orders'        => $orders->all(),
            'files'         => $files,
            'messages'      => $messages->all(),
            'addresses'     => $customer->addresses()->orderByDesc('is_default')->orderBy('id')->get()->map->toPortal()->all(),
            'saved'         => $customer->savedItems()->orderByDesc('id')->get()->map->toPortal()->all(),
            'notifications' => self::notifications($orders, $files, $messages),
            'prefs'         => $customer->allPreferences(),
            'billing'       => [
                'name'    => $customer->billingName(),
                'person'  => $customer->name,
                'email'   => $customer->email,
                'phone'   => $customer->phone,
                'address' => $customer->org_address,
                'stamp'   => (bool) $customer->pref('show_stamp'),
            ],
            'limit'         => \App\Models\FileDownload::limit(),
            'delivery'      => collect(config('wakata.delivery'))->map(fn ($d) => ['label' => $d['label'], 'fee' => $d['fee'], 'eta' => $d['eta']])->all(),
            'payments'      => config('wakata.payments'),
            'focus'         => $focus,
            'routes'        => self::routes(),
        ];
    }

    /** URL templates: `__ID__` is replaced in the browser (an order number, a file key ...). */
    private static function routes(): array
    {
        return [
            'order'         => route('account.orders') . '/__ID__',
            'invoice'       => route('account.invoices') . '/__ID__',
            'download'      => url('/account/library') . '/__ORDER__/__FILE__/download',
            'email'         => url('/account/library') . '/__ORDER__/__FILE__/email',
            'orders'        => route('account.orders'),
            'claim'         => route('account.orders.claim'),
            'claimVerify'   => route('account.orders.claim.verify'),
            'saved'         => route('account.saved'),
            'addresses'     => route('account.addresses'),
            'addressPrefs'  => route('account.addresses.preferences'),
            'support'       => route('account.support'),
            'dashboard'     => route('account.dashboard'),
            'library'       => route('account.library'),
            'invoices'      => route('account.invoices'),
            'profile'       => route('account.profile'),
            'papers'        => route('site.papers'),
            'books'         => route('site.books'),
            'sets'          => route('site.sets'),
            'packages'      => route('site.packages'),
            'cart'          => route('site.cart'),
            'book'          => route('site.book'),
        ];
    }

    /** @return Collection<int, array> */
    private static function orders(Customer $customer): Collection
    {
        return Order::query()
            ->where('customer_id', $customer->id)
            ->with(['items', 'events'])
            ->latest('id')->limit(self::ORDER_LIMIT)->get()
            ->map(function (Order $o) {
                $events = $o->events->map(fn ($e) => ['ts' => $e->created_at->getTimestampMs(), 'label' => $e->label, 'note' => (string) $e->note])->all();

                return [
                    'id'       => $o->number,
                    'ts'       => $o->created_at->getTimestampMs(),
                    'status'   => $o->status,
                    'pay'      => $o->payment_status,
                    'method'   => $o->payment_method,
                    'delivery' => $o->delivery_method,
                    'items'    => $o->items->map(fn ($i) => [
                        'iid'     => $i->id,
                        'type'    => $i->item_type,
                        'id'      => $i->item_slug,
                        'fmt'     => $i->format,
                        'qty'     => $i->quantity,
                        'price'   => $i->unit_price,
                        'title'   => $i->title,
                        'level'   => strtoupper((string) $i->level),
                        'subject' => $i->subject,
                        'year'    => $i->year,
                        'paperNo' => $i->paper_no,
                    ])->all(),
                    'sub'      => $o->subtotal,
                    'fee'      => $o->delivery_fee,
                    'total'    => $o->total,
                    'txn'      => (string) $o->transaction_ref,
                    'address'  => (string) $o->delivery_address,
                    // Orders placed before the history table existed still get their first line
                    'events'   => $events ?: [['ts' => $o->created_at->getTimestampMs(), 'label' => 'Order placed', 'note' => 'Online checkout']],
                ];
            });
    }

    /** @return Collection<int, array> */
    public static function messages(Customer $customer): Collection
    {
        return SupportThread::query()
            ->where('customer_id', $customer->id)
            ->with(['messages', 'order:id,number'])
            ->orderByDesc('last_message_at')->orderByDesc('id')->get()
            ->map(function (SupportThread $t) {
                $last = $t->messages->last();

                $status = match (true) {
                    $t->status === 'closed'        => 'closed',
                    $t->hasUnreadReply()           => 'new',
                    $last?->sender === 'staff'     => 'answered',
                    default                        => 'open',
                };

                return [
                    'id'      => $t->ref,
                    'subject' => $t->subject,
                    'topic'   => $t->topic,
                    'status'  => $status,
                    'order'   => $t->order?->number ?? '',
                    'ts'      => ($t->last_message_at ?? $t->created_at)->getTimestampMs(),
                    'msgs'    => $t->messages->map(fn ($m) => [
                        'from' => $m->sender === 'customer' ? 'you' : 'wakata',
                        'text' => $m->body,
                        'ts'   => $m->created_at->getTimestampMs(),
                    ])->all(),
                ];
            });
    }

    /**
     * The bell in the top bar. Nothing is stored: it is worked out from what is already there.
     *
     * @return array<int, array{icon:string,t:string,s:string,ts:int}>
     */
    private static function notifications(Collection $orders, array $files, Collection $messages): array
    {
        $list = [];

        foreach ($orders->whereIn('status', ['shipped', 'ready'])->take(2) as $o) {
            $list[] = $o['status'] === 'ready'
                ? ['icon' => 'box', 't' => 'Order ' . $o['id'] . ' is ready for pickup', 's' => 'Collect it from the WAKATA shop', 'ts' => $o['ts']]
                : ['icon' => 'truck', 't' => 'Order ' . $o['id'] . ' is out for delivery', 's' => 'Expected today or tomorrow', 'ts' => $o['ts']];
        }

        $fresh = collect($files)->where('kind', 'paper')->where('used', 0)->take(1)->first();
        if ($fresh) {
            $list[] = ['icon' => 'download', 't' => 'PDFs ready to download', 's' => $fresh['title'], 'ts' => $fresh['ts']];
        }

        foreach ($messages->where('status', 'new')->take(2) as $m) {
            $list[] = ['icon' => 'mail', 't' => 'WAKATA replied to your question', 's' => $m['subject'], 'ts' => $m['ts']];
        }

        if (! $list) {
            $list[] = ['icon' => 'gift', 't' => 'Welcome to WAKATA', 's' => 'Browse papers, books and holiday packages', 'ts' => now()->getTimestampMs()];
        }

        usort($list, fn ($a, $b) => $b['ts'] <=> $a['ts']);

        return array_slice($list, 0, 6);
    }
}
