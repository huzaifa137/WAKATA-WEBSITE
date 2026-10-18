<?php

namespace App\Http\Controllers;

use App\Mail\GeneralMail;
use App\Models\ContactMessage;
use App\Models\Customer;
use App\Support\SiteSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

/** The Contact page form: saved in the Messages inbox, and the team is alerted by email when an address is set. */
class ContactController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        // A hidden field real visitors never fill in: robots do
        if (filled($request->input('website'))) {
            return response()->json(['ok' => true]);
        }

        $data = $request->validate([
            'name'    => ['required', 'string', 'max:120'],
            'phone'   => ['required', 'string', 'max:30', function ($attr, $value, $fail) {
                if (strlen(preg_replace('/\D/', '', $value)) < 9) {
                    $fail('Enter a valid phone number.');
                }
            }],
            'email'   => ['required', 'email', 'max:190'],
            'topic'   => ['required', Rule::in(ContactMessage::TOPICS)],
            'message' => ['required', 'string', 'min:3', 'max:5000'],
        ], [
            'name.required'    => 'Enter your name.',
            'email.required'   => 'Enter a valid email address.',
            'email.email'      => 'Enter a valid email address.',
            'phone.required'   => 'Enter a valid phone number.',
            'message.required' => 'Write a short message.',
            'message.min'      => 'Write a short message.',
        ]);

        $email = strtolower(trim($data['email']));
        $m = ContactMessage::create([
            'customer_id' => Customer::where('email', $email)->value('id'),
            'name'        => trim($data['name']),
            'email'       => $email,
            'phone'       => trim($data['phone']),
            'topic'       => $data['topic'],
            'body'        => trim($data['message']),
            'status'      => 'new',
            'ip_address'  => $request->ip(),
        ]);
        $m->forceFill(['ref' => 'M-' . (ContactMessage::REF_BASE + $m->id)])->save();

        foreach (SiteSettings::alertRecipients('messages') as $to) {
            try {
                Mail::to($to)->send(new GeneralMail(
                    'New message from ' . $m->name . ' (' . $m->topic . ')',
                    $m->body . "\n\nFrom: " . $m->name . ', ' . $m->email . ', ' . $m->phone,
                    'A message was sent from the Contact page.',
                ));
            } catch (\Throwable $e) {
                report($e);       // an email problem never loses the message
            }
        }

        return response()->json(['ok' => true, 'ref' => $m->ref]);
    }
}
