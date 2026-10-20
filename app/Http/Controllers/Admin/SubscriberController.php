<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendNewsletter;
use App\Mail\GeneralMail;
use App\Models\Admin;
use App\Models\Customer;
use App\Models\Newsletter;
use App\Models\Subscriber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

/** Subscribers and the newsletter: add or remove people, and email everyone who signed up. */
class SubscriberController extends Controller
{
    /** One send goes to at most this many people, so it finishes inside a normal web request. */
    public const SEND_LIMIT = 1000;

    /** More recipients than this and the newsletter is sent after the page has loaded instead of while it waits. */
    public const BACKGROUND_OVER = 25;

    public function index()
    {
        return view('admin.subscribers');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:190']], ['email.*' => 'Enter a valid email address.']);
        $email = strtolower(trim($data['email']));

        $sub = Subscriber::where('email', $email)->first();
        if ($sub?->isActive()) {
            return back()->with('status', $email . ' is already on the list.');
        }
        $sub ? $sub->update(['status' => 'active', 'unsubscribed_at' => null, 'subscribed_at' => now(), 'source' => 'Added by staff'])
             : Subscriber::create(['email' => $email, 'source' => 'Added by staff']);

        return back()->with('status', $email . ' was added.');
    }

    public function destroy(Subscriber $subscriber): RedirectResponse
    {
        $subscriber->delete();

        return back()->with('status', 'Subscriber removed.');
    }

    /** Switch one person between subscribed and unsubscribed. */
    public function status(Request $request, Subscriber $subscriber): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['active', 'unsubscribed'])]]);
        $subscriber->update(['status' => $data['status'], 'unsubscribed_at' => $data['status'] === 'active' ? null : now()]);

        return back()->with('status', $subscriber->email . ($data['status'] === 'active' ? ' is subscribed again.' : ' was unsubscribed.'));
    }

    public function bulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['delete', 'unsubscribe'])],
            'ids'    => ['required', 'array', 'min:1', 'max:1000'],
            'ids.*'  => ['integer'],
        ]);

        $q = Subscriber::whereIn('id', $data['ids']);
        $n = (clone $q)->count();
        $data['action'] === 'delete' ? $q->delete() : $q->update(['status' => 'unsubscribed', 'unsubscribed_at' => now()]);

        return back()->with('status', $n . ($n === 1 ? ' subscriber was ' : ' subscribers were ') . ($data['action'] === 'delete' ? 'removed.' : 'unsubscribed.'));
    }

    /** Send a newsletter to the active subscribers (all, schools only, teachers only), or a test to the signed-in admin. */
    public function send(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'subject'  => ['required', 'string', 'max:190'],
            'body'     => ['required', 'string', 'min:5', 'max:10000'],
            'audience' => ['required', Rule::in(['all', 'schools', 'teachers'])],
            'test'     => ['nullable'],
        ], ['subject.required' => 'Enter a subject.', 'body.required' => 'Write the message.', 'body.min' => 'Write the message.']);

        /** @var Admin $admin */
        $admin = $request->attributes->get('admin');

        if ($request->boolean('test')) {
            try {
                Mail::to($admin->email)->send(new GeneralMail('[Test] ' . $data['subject'], $data['body'], null, null, null, null, route('newsletter.unsubscribe', str_repeat('0', 40))));
            } catch (\Throwable $e) {
                report($e);

                return back()->withInput()->with('fail', 'The test email could not be sent. Check the mail settings.');
            }

            return back()->with('status', 'A test email was sent to ' . $admin->email . '.');
        }

        $subs = Subscriber::where('status', 'active')->orderBy('id')->get();
        if ($data['audience'] !== 'all') {
            $type = $data['audience'] === 'schools' ? 'School' : 'Teacher';
            $emails = Customer::where('type', $type)->pluck('email')->map(fn ($e) => strtolower($e))->all();
            $subs = $subs->filter(fn (Subscriber $s) => in_array($s->email, $emails, true));
        }
        if ($subs->isEmpty()) {
            return back()->withInput()->with('fail', 'There is nobody to send this to yet.');
        }
        if ($subs->count() > self::SEND_LIMIT) {
            return back()->withInput()->with('fail', 'This audience has ' . $subs->count() . ' people. Send to a smaller audience: one send reaches up to ' . self::SEND_LIMIT . '.');
        }

        // A short list goes out now and the page reports how many arrived. A long one is sent after this page has loaded,
        // so the team is not left waiting (and the web server does not time out): the history shows "sending" until it is done.
        $background = $subs->count() > self::BACKGROUND_OVER;

        $letter = Newsletter::create([
            'subject' => $data['subject'], 'body' => $data['body'], 'audience' => $data['audience'], 'status' => $background ? 'sending' : 'sent',
            'total' => $subs->count(), 'sent_count' => 0, 'failed_count' => 0, 'admin_id' => $admin->id, 'admin_name' => $admin->name, 'sent_at' => now(),
        ]);

        $job = new SendNewsletter($letter->id, $subs->pluck('id')->all(), $data['subject'], $data['body']);

        if ($background) {
            config('wakata.newsletter_queue') ? dispatch($job) : dispatch($job)->afterResponse();

            return back()->with('status', 'The newsletter is being sent to ' . $subs->count() . ' people. The history shows the final count in a few minutes.');
        }

        $job->handle();
        $letter->refresh();
        $ok     = $letter->sent_count;
        $failed = $letter->failed_count;

        return back()->with($ok ? 'status' : 'fail', $ok ? 'Newsletter sent to ' . $ok . ($ok === 1 ? ' person' : ' people') . ($failed ? ' (' . $failed . ' could not be reached).' : '.') : 'No email could be sent. Check the mail settings.');
    }
}
