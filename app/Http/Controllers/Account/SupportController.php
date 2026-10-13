<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Mail\SupportMessageStaffMail;
use App\Models\Customer;
use App\Models\Order;
use App\Models\SupportMessage;
use App\Models\SupportThread;
use App\Support\Portal;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Help and support: conversations between a customer and WAKATA. Customers start threads and reply here;
 * WAKATA's replies are written from the admin console (module 9: Engagement and site content).
 */
class SupportController extends Controller
{
    public function index(): View
    {
        return view('account.support');
    }

    /** New conversation: { topic, order?, subject, body }. */
    public function store(Request $request): JsonResponse
    {
        $customer = $this->customer($request);

        $data = $request->validate([
            'topic'   => ['required', Rule::in(SupportThread::TOPICS)],
            'order'   => ['nullable', 'string', 'max:20'],
            'subject' => ['required', 'string', 'max:160'],
            'body'    => ['required', 'string', 'max:5000'],
        ], [
            'subject.required' => 'Enter a subject.',
            'body.required'    => 'Write your message.',
        ]);

        $orderId = null;
        if (filled($data['order'] ?? null)) {
            $orderId = Order::where('customer_id', $customer->id)->where('number', Str::upper(trim($data['order'])))->value('id');
            if (! $orderId) {
                return response()->json([
                    'ok'      => false,
                    'message' => 'We could not find that order in your account. Check the number, or leave it blank.',
                    'errors'  => ['order' => ['We could not find that order in your account.']],
                ], 422);
            }
        }

        [$thread, $message] = DB::transaction(function () use ($customer, $data, $orderId) {
            $thread = SupportThread::create([
                'customer_id'      => $customer->id,
                'order_id'         => $orderId,
                'topic'            => $data['topic'],
                'subject'          => trim($data['subject']),
                'status'           => 'open',
                'customer_read_at' => now(),
                'last_message_at'  => now(),
            ]);
            $thread->forceFill(['ref' => 'T-' . (SupportThread::NUMBER_BASE + $thread->id)])->save();

            return [$thread, $thread->messages()->create(['sender' => 'customer', 'body' => trim($data['body'])])];
        });

        $this->tellStaff($thread, $message);

        return $this->state($customer, 'Message sent. We reply within one working day.', 201, $thread->ref);
    }

    /** Reply in a conversation: { body }. Replying to a closed conversation opens it again. */
    public function reply(Request $request, string $ref): JsonResponse
    {
        $customer = $this->customer($request);
        $thread   = $this->thread($customer, $ref);

        $data = $request->validate(['body' => ['required', 'string', 'max:5000']], ['body.required' => 'Write a reply first.']);

        $message = DB::transaction(function () use ($thread, $data) {
            $message = $thread->messages()->create(['sender' => 'customer', 'body' => trim($data['body'])]);
            $thread->forceFill(['status' => 'open', 'customer_read_at' => now(), 'last_message_at' => now()])->save();

            return $message;
        });

        $this->tellStaff($thread, $message);

        return $this->state($customer, 'Message sent. We reply within one working day.', 200, $thread->ref);
    }

    /** The customer opened the conversation: its "New reply" mark goes away. */
    public function read(Request $request, string $ref): JsonResponse
    {
        $customer = $this->customer($request);
        $thread   = $this->thread($customer, $ref);

        $thread->forceFill(['customer_read_at' => now()])->save();

        return $this->state($customer, '', 200, $thread->ref);
    }

    private function thread(Customer $customer, string $ref): SupportThread
    {
        return SupportThread::where('customer_id', $customer->id)->where('ref', $ref)->firstOrFail();
    }

    private function state(Customer $customer, string $message, int $status, ?string $focus = null): JsonResponse
    {
        return response()->json(['ok' => true, 'message' => $message, 'focus' => $focus, 'messages' => Portal::messages($customer)->all()], $status);
    }

    /** A mail problem never loses the message: it is already saved. */
    private function tellStaff(SupportThread $thread, SupportMessage $message): void
    {
        if (! ($to = config('wakata.support_notify'))) {
            return;
        }

        try {
            Mail::to($to)->send(new SupportMessageStaffMail($thread->load(['customer', 'order']), $message));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function customer(Request $request): Customer
    {
        return $request->attributes->get('customer');
    }
}
