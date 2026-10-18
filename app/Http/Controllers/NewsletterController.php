<?php

namespace App\Http\Controllers;

use App\Models\Subscriber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** "Get new papers first": sign up from the website, and the unsubscribe link at the bottom of every newsletter. */
class NewsletterController extends Controller
{
    public function subscribe(Request $request): JsonResponse
    {
        if (filled($request->input('website'))) {
            return response()->json(['ok' => true, 'message' => 'Thank you. You are on the list for new papers and offers.']);
        }

        $data = $request->validate(['email' => ['required', 'email', 'max:190'], 'source' => ['nullable', 'string', 'max:30']], [
            'email.required' => 'Enter a valid email address.',
            'email.email'    => 'Enter a valid email address.',
        ]);

        $source = in_array($data['source'] ?? '', Subscriber::SOURCES, true) ? $data['source'] : 'Footer';
        $email  = strtolower(trim($data['email']));
        $sub    = Subscriber::where('email', $email)->first();

        if ($sub && $sub->isActive()) {
            return response()->json(['ok' => true, 'message' => 'You are already on the list. Thank you.']);
        }
        if ($sub) {                                       // they left before and came back
            $sub->update(['status' => 'active', 'unsubscribed_at' => null, 'subscribed_at' => now(), 'source' => $source]);
        } else {
            Subscriber::create(['email' => $email, 'source' => $source]);
        }

        return response()->json(['ok' => true, 'message' => 'Thank you. You are on the list for new papers and offers.']);
    }

    public function unsubscribe(string $token)
    {
        $sub = Subscriber::where('token', $token)->first();
        if ($sub && $sub->isActive()) {
            $sub->update(['status' => 'unsubscribed', 'unsubscribed_at' => now()]);
        }

        return view('site.unsubscribed', ['found' => (bool) $sub]);
    }
}
