<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdminInsights;
use App\Support\ReportExport;
use App\Support\ReportPdf;
use App\Support\SiteSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Module 10: Reports. The charts are drawn in the browser from WK.sales; this controller serves the downloads
 * (CSV and PDF) and saves the schedule of the emailed report. Looking is open to every team member, downloading
 * and scheduling need admin.can:reports (super admin or manager) because the files carry customer details.
 */
class ReportController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.reports', ['ins' => AdminInsights::reports($request->attributes->get('admin'))]);
    }

    public function export(Request $request, string $report, string $format): Response|StreamedResponse
    {
        $days  = ReportExport::range($request->query('days'));
        $table = ReportExport::build($report, $days);
        $file  = 'wakata-' . $report . '-' . $days . 'd-' . now()->format('Ymd');

        if ($format === 'pdf') {
            $pdf = ReportPdf::table(
                config('wakata.name'), ReportExport::title($report),
                'Last ' . $days . ' days, made ' . now()->format('j M Y H:i') . ', ' . count($table['rows']) . ' rows',
                $table['head'], $table['rows'], $table['numeric'],
            );

            return response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="' . $file . '.pdf"']);
        }

        return response()->streamDownload(function () use ($table) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $table['head']);
            foreach ($table['rows'] as $row) {
                fputcsv($out, array_map([ReportExport::class, 'cell'], $row));
            }
            fclose($out);
        }, $file . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function schedule(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'frequency'  => ['required', 'in:off,weekly,monthly'],
            'recipients' => ['nullable', 'string', 'max:500'],
        ]);

        $emails = SiteSettings::emails((string) ($data['recipients'] ?? ''));

        if ($data['frequency'] !== 'off' && ! $emails) {
            return back()->withInput()->withErrors(['recipients' => 'Enter at least one valid email address to send the report to.']);
        }

        ReportExport::saveSchedule($data['frequency'], implode(', ', $emails));

        return back()->with('status', $data['frequency'] === 'off'
            ? 'The emailed report is switched off.'
            : 'The report will be emailed ' . ($data['frequency'] === 'weekly' ? 'every Monday' : 'on the 1st of every month') . ' to ' . implode(', ', $emails) . '.');
    }
}
