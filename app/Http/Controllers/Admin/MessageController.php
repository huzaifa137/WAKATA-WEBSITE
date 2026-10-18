<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\GeneralMail;
use App\Models\Admin;
use App\Models\ContactMessage;
use App\Models\ContactReply;
use App\Models\SupportMessage;
use App\Models\SupportThread;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

/**
 * Messages: what arrived from the Contact page (refs M-1001) and the questions customers ask from their account (refs T-1001).
 * Reading is open to every admin; answering and changing needs the inbox role (super admin, manager, support).
 */
class MessageController extends Controller
{
    public function index()
    {
        return view('admin.messages');
    }

    /** Answer by email; the answer is kept on the message. A mail problem is reported and nothing is marked as answered. */
    public function reply(Request $request, string $ref): RedirectResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'min:2', 'max:5000']], ['body.required' => 'Write your answer first.', 'body.min' => 'Write your answer first.']);

        if ($thread = $this->thread($ref)) {
            return $this->supportReply($request, $thread, $data['body']);
        }
        $m = $this->message($ref);

        try {
            Mail::to($m->email)->send(new GeneralMail('Re: ' . $m->topic, $data['body'], 'Hello ' . $m->name . ','));
        } catch (\Throwable $e) {
            report($e);

            return back()->withInput()->with('fail', 'The email could not be sent, so nothing was marked as answered. Check the mail settings and try again.');
        }

        DB::transaction(function () use ($m, $data, $request) {
            ContactReply::create(['contact_message_id' => $m->id, 'admin_id' => $this->admin($request)?->id, 'admin_name' => $this->admin($request)?->name, 'kind' => 'reply', 'subject' => 'Re: ' . $m->topic, 'body' => $data['body']]);
            $m->forceFill(['status' => 'replied', 'replied_at' => now(), 'read_at' => $m->read_at ?? now()])->save();
        });

        return back()->with('status', 'Your answer was emailed to ' . $m->email . '.');
    }

    private function supportReply(Request $request, SupportThread $t, string $body): RedirectResponse
    {
        DB::transaction(function () use ($t, $body, $request) {
            SupportMessage::create(['support_thread_id' => $t->id, 'sender' => 'staff', 'admin_id' => $this->admin($request)?->id, 'body' => $body]);
            // customer_read_at stays as it is: the customer sees the answer as new until they open it
            $t->forceFill(['status' => 'answered', 'last_message_at' => now()])->save();
        });

        $t->loadMissing('customer');
        try {
            Mail::to($t->customer->email)->send(new GeneralMail('Answer to your question: ' . $t->subject, $body, 'Hello ' . $t->customer->name . ',', null, null, 'You can also read and reply in your WAKATA account, under Support.'));
        } catch (\Throwable $e) {
            report($e);          // the answer is saved and shows in their account either way
        }

        return back()->with('status', 'Your answer was added to the conversation with ' . $t->customer->name . '.');
    }

    /** Mark as read, unread (new) or archived. Support conversations: archived closes, new opens again. */
    public function status(Request $request, string $ref): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['new', 'read', 'archived'])]]);

        if ($t = $this->thread($ref)) {
            if ($data['status'] !== 'read') {
                $t->update(['status' => $data['status'] === 'archived' ? 'closed' : 'open']);
            }
        } else {
            $m = $this->message($ref);
            $m->forceFill(['status' => $data['status'] === 'new' ? 'new' : $data['status'], 'read_at' => $data['status'] === 'new' ? null : ($m->read_at ?? now())])->save();
        }

        return back()->with('status', $data['status'] === 'archived' ? 'Message archived.' : ($data['status'] === 'new' ? 'Marked as unread.' : 'Marked as read.'));
    }

    /** Opening a message marks it as read without a page reload. */
    public function read(Request $request, string $ref)
    {
        if (! $this->thread($ref)) {
            $m = $this->message($ref);
            if ($m->status === 'new') {
                $m->forceFill(['status' => 'read', 'read_at' => now()])->save();
            }
        }

        return response()->json(['ok' => true]);
    }

    public function destroy(string $ref): RedirectResponse
    {
        if ($this->thread($ref)) {
            return back()->with('fail', 'A customer conversation cannot be deleted. Archive it instead.');
        }
        $this->message($ref)->delete();

        return back()->with('status', 'Message deleted.');
    }

    /** A priced quotation by email (a school asking for a bulk price). Lines are typed by staff, so any price can be quoted. */
    public function quote(Request $request, string $ref): RedirectResponse
    {
        $data = $request->validate([
            'prepared'      => ['nullable', 'string', 'max:160'],
            'valid_until'   => ['nullable', 'date', 'after_or_equal:today'],
            'lines'         => ['required', 'array', 'min:1', 'max:30'],
            'lines.*.label' => ['required', 'string', 'max:200'],
            'lines.*.qty'   => ['required', 'integer', 'min:1', 'max:100000'],
            'lines.*.unit'  => ['required', 'integer', 'min:0', 'max:100000000'],
            'delivery'      => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'terms'         => ['nullable', 'string', 'max:1000'],
        ], [
            'lines.required'       => 'Add at least one item to the quotation.',
            'lines.*.label.required' => 'Every line needs an item.',
            'lines.*.qty.min'      => 'Every quantity must be at least 1.',
            'valid_until.after_or_equal' => 'The quotation cannot be valid until a past date.',
        ]);

        if (str_starts_with($ref, 'T-')) {
            return back()->with('fail', 'Quotations are for messages from the Contact page.');
        }
        $m = $this->message($ref);

        $rows = [];
        $sub  = 0;
        foreach ($data['lines'] as $l) {
            $line   = (int) $l['qty'] * (int) $l['unit'];
            $sub   += $line;
            $rows[] = [$l['label'], number_format((int) $l['qty']), 'UGX ' . number_format((int) $l['unit']), 'UGX ' . number_format($line)];
        }
        $fee    = (int) ($data['delivery'] ?? 0);
        $totals = array_values(array_filter([
            ['Subtotal', 'UGX ' . number_format($sub)],
            $fee ? ['Delivery', 'UGX ' . number_format($fee)] : null,
            ['Total', 'UGX ' . number_format($sub + $fee)],
        ]));
        $body = 'Thank you for asking. Here is our quotation' . (filled($data['prepared'] ?? null) ? ' for ' . $data['prepared'] : '') . '.'
            . (filled($data['valid_until'] ?? null) ? "\n\nThis quotation is valid until " . \Carbon\Carbon::parse($data['valid_until'])->format('j F Y') . '.' : '');

        try {
            Mail::to($m->email)->send(new GeneralMail('Your WAKATA quotation', $body, 'Hello ' . $m->name . ',', $rows, $totals, $data['terms'] ?? null));
        } catch (\Throwable $e) {
            report($e);

            return back()->withInput()->with('fail', 'The quotation could not be emailed. Check the mail settings and try again.');
        }

        DB::transaction(function () use ($m, $data, $rows, $totals, $request, $sub, $fee, $body) {
            ContactReply::create([
                'contact_message_id' => $m->id, 'admin_id' => $this->admin($request)?->id, 'admin_name' => $this->admin($request)?->name, 'kind' => 'quotation',
                'subject' => 'Quotation', 'body' => $body, 'meta' => ['lines' => $data['lines'], 'total' => $sub + $fee, 'terms' => $data['terms'] ?? null],
            ]);
            $m->forceFill(['status' => 'quoted', 'replied_at' => now(), 'read_at' => $m->read_at ?? now()])->save();
        });

        return back()->with('status', 'The quotation (UGX ' . number_format($sub + $fee) . ') was emailed to ' . $m->email . '.');
    }

    private function message(string $ref): ContactMessage
    {
        return ContactMessage::where('ref', $ref)->firstOrFail();
    }

    private function thread(string $ref): ?SupportThread
    {
        return str_starts_with($ref, 'T-') ? SupportThread::where('ref', $ref)->firstOrFail() : null;
    }

    private function admin(Request $request): ?Admin
    {
        return $request->attributes->get('admin');
    }
}
