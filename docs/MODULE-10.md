# Module 10: Insights and system

Dashboard KPIs, reports, team and roles, activity log. Everything on these pages now comes from the database.

## Set up

    php artisan migrate          # admin_activities table, invite columns on admins

Add the usual cron line on the server (needed only for the emailed report):

    * * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1

## What changed

- **Activity log** (`/admin/activity`): every change a team member makes, plus sign-ins and sign-outs, is written
  automatically by `LoggedAdmin` -> `App\Support\Activity::record()`. Failed or refused requests are not logged.
  Report and data-export downloads are logged too. To log a new action, add its route name to `Activity::map()`.
- **Team and roles** (`/admin/team`): super admin invites (7-day email link that sets the password and activates the
  account), changes role or suspends, resends invitations, removes members. Nobody can change or remove themselves.
  The role table is computed from the `Admin::can...()` methods, so it always matches what the console allows.
- **Dashboard** (`/admin/dashboard`): KPIs, charts, top products and recent orders from real orders (`WK.sales`);
  "Needs your attention" counts, the monthly goal (Settings) and the latest activity from `WK.ins`.
- **Reports** (`/admin/reports`): charts from real orders. Ready-made reports download as CSV or PDF (sales, orders,
  products, customers, payments, stock) for super admin and manager. The "Schedule" button emails a weekly (Monday) or
  monthly (1st) summary: `php artisan wakata:send-report --force` sends one now.
- **Console shell** (every admin page): the notification bell, sidebar badges and Ctrl K search use real data (`WK.shell`).

## Notes

- Visits and product views are not tracked, so the funnel on Reports is an order funnel (placed, accepted, paid, completed).
- Looking at dashboard, reports, team and activity is open to every signed-in team member; downloading reports needs
  `admin.can:reports` (super admin, manager); managing the team needs `admin.can:team` (super admin).
- Tests: `php artisan test --filter=AdminInsightsTest`
