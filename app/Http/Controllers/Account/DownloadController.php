<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Mail\LibraryFileMail;
use App\Models\FileDownload;
use App\Models\Order;
use App\Support\Library;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * PDF downloads from the library. Each file of an order can be downloaded a limited number of times (5 by default).
 * The count is used up in one atomic statement (FileDownload::take) BEFORE the file is sent, and given back if the
 * file could not be handed over. Both actions are POST so a link preview or browser prefetch cannot burn a download.
 */
class DownloadController extends Controller
{
    public function download(Request $request, string $number, string $file): Response
    {
        [$customer, $order, $f] = $this->resolve($request, $number, $file);

        // Is there anything to send? Never use up a download for nothing.
        $stored = Library::storedPath($f);
        $bytes  = $stored ? null : Library::placeholder($f, $customer);

        if (! $stored && $bytes === null) {
            return $this->preparing();
        }

        $row = FileDownload::take($order, $f['key'], $request->ip());
        if (! $row) {
            return $this->limitReached();
        }

        $headers = $this->headers($row);

        if ($stored) {
            return Storage::disk('local')->download($stored, $f['name'], $headers + ['Content-Type' => 'application/pdf']);
        }

        return response($bytes, 200, $headers + [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $f['name'] . '"',
        ]);
    }

    /** "Email me this file": counts as a download too. */
    public function email(Request $request, string $number, string $file): JsonResponse
    {
        [$customer, $order, $f] = $this->resolve($request, $number, $file);

        $stored = Library::storedPath($f);
        $bytes  = $stored ? Storage::disk('local')->get($stored) : Library::placeholder($f, $customer);

        if ($bytes === null) {
            return $this->preparing();
        }

        $row = FileDownload::take($order, $f['key'], $request->ip());
        if (! $row) {
            return $this->limitReached();
        }

        try {
            Mail::to($customer->email)->send(new LibraryFileMail($customer->name, $f['title'], $f['name'], $bytes, $row->max_downloads - $row->used));
        } catch (\Throwable $e) {
            report($e);
            $row->giveBack();

            return response()->json(['ok' => false, 'message' => 'We could not send the email just now. No download was used. Please try again.'], 502);
        }

        return response()->json([
            'ok'      => true,
            'message' => 'We emailed ' . $f['name'] . ' to ' . $customer->email . '.',
            'used'    => $row->used,
            'max'     => $row->max_downloads,
        ]);
    }

    /** @return array{0: \App\Models\Customer, 1: Order, 2: array} */
    private function resolve(Request $request, string $number, string $file): array
    {
        $customer = $request->attributes->get('customer');

        $order = Order::query()->where('number', $number)->where('customer_id', $customer->id)->first();
        abort_unless($order && $order->isCounted(), 404);

        $f = Library::find($order, $file);
        abort_unless($f, 404);

        return [$customer, $order, $f];
    }

    private function headers(FileDownload $row): array
    {
        return [
            'X-Downloads-Used'          => (string) $row->used,
            'X-Downloads-Max'           => (string) $row->max_downloads,
            'Access-Control-Expose-Headers' => 'X-Downloads-Used, X-Downloads-Max, Content-Disposition',
            'Cache-Control'             => 'private, no-store',
        ];
    }

    private function limitReached(): JsonResponse
    {
        return response()->json([
            'ok'      => false,
            'message' => 'You have used all your downloads for this file. Ask us for more from Help and support.',
            'limit'   => true,
        ], 429);
    }

    private function preparing(): JsonResponse
    {
        return response()->json([
            'ok'      => false,
            'message' => 'This file is still being prepared. We will email you as soon as it is ready. No download was used.',
        ], 409);
    }
}
