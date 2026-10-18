<?php

namespace App\Support;

use App\Models\ContactMessage;
use App\Models\Customer;
use App\Models\Faq;
use App\Models\Newsletter;
use App\Models\Order;
use App\Models\Subscriber;
use App\Models\SupportThread;
use App\Models\Testimonial;

/**
 * The data of the Messages, Subscribers, Testimonials and Questions pages, handed to the page scripts as WK.eng.
 * Messages are what arrived from the Contact page plus the questions customers ask from their account (support threads),
 * so staff answer both from one inbox. Shapes follow what the page scripts always used (id, name, email, topic, body, status, ts).
 */
class AdminEngagement
{
    public static function payload(): array
    {
        $customers = Customer::get()->keyBy(fn (Customer $c) => strtolower($c->email));

        return [
            'messages'     => self::messages($customers),
            'subscribers'  => Subscriber::orderByDesc('subscribed_at')->get()->map(fn (Subscriber $s) => [
                'id' => $s->id, 'email' => $s->email, 'source' => $s->source, 'status' => $s->status, 'ts' => $s->subscribed_at->getTimestampMs(),
                'type' => optional($customers[$s->email] ?? null)->type,
            ])->values()->all(),
            'newsletters'  => Newsletter::orderByDesc('sent_at')->limit(20)->get()->map(fn (Newsletter $n) => [
                'id' => $n->id, 'subject' => $n->subject, 'audience' => $n->audience, 'sent' => $n->sent_count, 'failed' => $n->failed_count, 'ts' => $n->sent_at->getTimestampMs(),
            ])->all(),
            'testimonials' => Testimonial::orderBy('sort_order')->orderBy('id')->get()->map(fn (Testimonial $t) => [
                'id' => $t->id, 'stars' => $t->stars, 'quote' => $t->quote, 'initials' => $t->initials, 'role' => (string) $t->role, 'place' => (string) $t->place,
                'visible' => $t->visible, 'permission' => $t->permission,
            ])->all(),
            'faqs'         => Faq::orderBy('page')->orderBy('sort_order')->orderBy('id')->get()->map(fn (Faq $f) => [
                'id' => $f->id, 'page' => $f->page, 'q' => $f->question, 'a' => $f->answer, 'icon' => $f->icon, 'visible' => $f->visible,
            ])->all(),
            'faqPages'     => Faq::PAGES,
            'faqIcons'     => array_keys(config('faq_icons')),
            'catalogue'    => self::quoteItems(),
            'delivery'     => collect(config('wakata.delivery'))->except('email')->map(fn ($d, $k) => ['label' => $d['label'], 'fee' => $d['fee']])->values()->all(),
            'routes'       => ['base' => url('/admin'), 'order' => route('admin.order'), 'contact' => route('site.contact')],
        ];
    }

    /** @return array<int, array> */
    private static function messages($customers): array
    {
        $orders = Order::orderByDesc('created_at')->get(['number', 'customer_email', 'total'])->groupBy(fn ($o) => strtolower($o->customer_email));

        $who = function (string $email, string $name, ?string $phone) use ($customers) {
            $c = $customers[strtolower($email)] ?? null;

            return ['name' => $name, 'type' => $c->type ?? 'Guest', 'district' => (string) ($c->district ?? ''), 'phone' => (string) ($phone ?: ($c->phone ?? ''))];
        };
        $ordersOf = fn (string $email) => ($orders[strtolower($email)] ?? collect())->take(3)->map(fn ($o) => ['id' => $o->number, 'total' => $o->total])->values()->all();

        $rows = [];
        foreach (ContactMessage::with('replies')->orderByDesc('created_at')->get() as $m) {
            $rows[] = [
                'id' => $m->ref, 'kind' => 'contact', 'name' => $m->name, 'email' => $m->email, 'phone' => (string) $m->phone, 'topic' => $m->topic,
                'subject' => $m->topic, 'body' => $m->body, 'status' => $m->status, 'ts' => $m->created_at->getTimestampMs(),
                'thread' => $m->replies->map(fn ($r) => ['from' => 'staff', 'who' => (string) $r->admin_name, 'kind' => $r->kind, 'body' => $r->body, 'ts' => $r->created_at->getTimestampMs()])->all(),
                'cust' => $who($m->email, $m->name, $m->phone), 'orders' => $ordersOf($m->email),
            ];
        }

        foreach (SupportThread::with(['customer', 'messages'])->orderByDesc('last_message_at')->get() as $t) {
            $first = $t->messages->first();
            $c     = $t->customer;
            $rows[] = [
                'id' => $t->ref, 'kind' => 'support', 'name' => $c->name, 'email' => $c->email, 'phone' => (string) $c->phone, 'topic' => $t->topic,
                'subject' => $t->subject, 'body' => $first->body ?? $t->subject,
                // open = the customer is waiting for us, answered = we replied, closed = done
                'status' => ['open' => 'new', 'answered' => 'replied', 'closed' => 'archived'][$t->status] ?? 'read',
                'ts' => ($t->last_message_at ?? $t->created_at)->getTimestampMs(),
                'thread' => $t->messages->skip(1)->map(fn ($m) => ['from' => $m->sender, 'who' => $m->sender === 'staff' ? 'WAKATA' : $c->name, 'kind' => 'reply', 'body' => $m->body, 'ts' => $m->created_at->getTimestampMs()])->values()->all(),
                'cust' => $who($c->email, $c->name, $c->phone), 'orders' => $ordersOf($c->email),
            ];
        }

        usort($rows, fn ($a, $b) => $b['ts'] <=> $a['ts']);

        return $rows;
    }

    /** Products staff can put on a quotation, with today's price (they may type another price). */
    private static function quoteItems(): array
    {
        $c = Catalogue::frontend();
        $out = [];
        foreach ($c['books'] as $b) {
            $out[] = ['label' => $b['title'], 'price' => $b['price']];
        }
        foreach ($c['sets'] as $s) {
            $out[] = ['label' => $s['title'] . ' (exam set)', 'price' => $s['price']];
        }
        foreach ($c['packages'] as $p) {
            $out[] = ['label' => $p['name'] . ' (holiday package)', 'price' => $p['price']];
        }
        foreach ($c['papers'] as $p) {
            if (empty($p['noPrint'])) {
                $out[] = ['label' => $p['title'] . ' ' . $p['year'] . ' (printed)', 'price' => $p['print']];
            }
        }

        return $out;
    }
}
