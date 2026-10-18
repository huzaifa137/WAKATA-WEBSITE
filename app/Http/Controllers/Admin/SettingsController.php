<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Subscriber;
use App\Support\SiteContent;
use App\Support\SiteSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Settings: general, PDF downloads, team alerts, analytics, and data exports. Changing needs super admin or manager. */
class SettingsController extends Controller
{
    public function index()
    {
        return view('admin.settings', ['s' => SiteSettings::all(), 'email' => SiteContent::get('contact.email')]);
    }

    public function general(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:190'],
            'goal'  => ['required', 'integer', 'min:0', 'max:100000000000'],
        ], ['email.*' => 'Enter a valid email address.', 'goal.*' => 'Enter the goal as a whole number.']);

        SiteContent::save(['contact.email' => $data['email']]);          // the one public email: the Contact email of Site content
        SiteSettings::save(['goal' => (int) $data['goal']]);

        return back()->with('status', 'General settings saved.');
    }

    public function downloads(Request $request): RedirectResponse
    {
        $data = $request->validate(['downloads' => ['required', 'integer', 'in:1,3,5,10,0']], ['downloads.*' => 'Choose how many downloads are allowed.']);
        SiteSettings::save(['downloads' => (int) $data['downloads']]);

        return back()->with('status', 'Download limit saved. It applies to every paid PDF from now on.');
    }

    public function notifications(Request $request): RedirectResponse
    {
        $data = $request->validate(['recipients' => ['nullable', 'string', 'max:500']]);
        $raw  = trim((string) ($data['recipients'] ?? ''));

        $valid = SiteSettings::emails($raw);
        $bad   = array_filter(preg_split('/[\s,;]+/', $raw, -1, PREG_SPLIT_NO_EMPTY), fn ($e) => ! in_array($e, $valid, true));
        if ($bad) {
            throw ValidationException::withMessages(['recipients' => 'One of these is not a valid email address. Separate addresses with commas.']);
        }

        SiteSettings::save([
            'alert_recipients' => implode(', ', SiteSettings::emails($raw)),
            'alert_orders'     => $request->boolean('alert_orders'),
            'alert_messages'   => $request->boolean('alert_messages'),
        ]);

        return back()->with('status', 'Alerts saved.');
    }

    public function integrations(Request $request): RedirectResponse
    {
        $data = $request->validate(['ga4' => ['nullable', 'regex:/^G-[A-Z0-9]{4,14}$/']], ['ga4.regex' => 'A Google Analytics 4 ID looks like G-ABC123XYZ.']);
        SiteSettings::save(['ga4' => strtoupper(trim((string) ($data['ga4'] ?? '')))]);

        return back()->with('status', 'Saved.');
    }

    /** Orders, customers, subscribers or books as a CSV file for a spreadsheet. */
    public function export(string $what): StreamedResponse
    {
        [$header, $rows] = match ($what) {
            'orders' => [['Order', 'Date', 'Customer', 'Email', 'Phone', 'Delivery', 'Payment method', 'Payment', 'Status', 'Total (UGX)'],
                fn () => Order::orderBy('id')->cursor()->map(fn ($o) => [$o->number, $o->created_at->format('Y-m-d H:i'), $o->customer_name, $o->customer_email, $o->customer_phone, $o->delivery_label, $o->payment_label, $o->payment_status, $o->status, $o->total])],
            'customers' => [['Name', 'Type', 'Email', 'Phone', 'School', 'District', 'Status', 'Joined'],
                fn () => Customer::orderBy('id')->cursor()->map(fn ($c) => [$c->name, $c->type, $c->email, $c->phone, $c->school, $c->district, $c->status, $c->created_at->format('Y-m-d')])],
            'subscribers' => [['Email', 'Source', 'Status', 'Signed up'],
                fn () => Subscriber::orderBy('id')->cursor()->map(fn ($s) => [$s->email, $s->source, $s->status, $s->subscribed_at->format('Y-m-d')])],
            'books' => [['SKU', 'Title', 'Subject', 'Level', 'Price (UGX)', 'Stock', 'Status'],
                fn () => Book::with(['subject', 'level'])->orderBy('id')->cursor()->map(fn ($b) => [$b->sku, $b->title, $b->subject->name, $b->level->label, $b->price, $b->stock, $b->status])],
            default => abort(404),
        };

        // A cell that starts with = + - @ could run as a formula when the file is opened in a spreadsheet: make it plain text
        $safe = fn ($v) => is_string($v) && $v !== '' && in_array($v[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'" . $v : $v;

        return response()->streamDownload(function () use ($header, $rows, $safe) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");                                     // so Excel reads the names correctly
            fputcsv($out, $header);
            foreach ($rows() as $r) {
                fputcsv($out, array_map($safe, $r));
            }
            fclose($out);
        }, 'wakata-' . $what . '-' . now()->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
