# WAKATA Admin Dashboard (front end)

Plain HTML, CSS and JS, built to match the website. Open `admin/login.html` in a browser.
Preview mode: any valid email and a password of 6+ characters signs you in.
(Set `AUTH_GUARD = false` at the top of `assets/js/admin.js` to open pages without signing in.)

## What is in here
- Auth: login, forgot-password, reset-password, verify (two-step), locked, 404
- Dashboard: KPIs, revenue line, category/payment/delivery donuts, status/level/weekday/subject bars, goal ring, best sellers, attention list, recent orders, activity
- Catalogue: books (+ form), past papers (Printed and PDF tabs, per-level default prices, PDF file status, + form), exam sets (+ form), holiday packages (+ form), subjects and levels, media library, inventory
- Sales: orders (+ order detail), payments, deliveries board, customers
- Engagement: messages and quotations, subscribers, testimonials, FAQs
- Website: site content (home, about, contact, cart and checkout, header and footer, SEO)
- Insights and system: reports, team and roles, activity log, settings, profile

## Files
- `assets/css/admin.css` design system (light and dark theme)
- `assets/js/charts.js` dependency-free SVG charts
- `assets/js/admin-data.js` SAMPLE data generated from the site catalogue (orders, customers, etc.)
- `assets/js/admin.js` shell, tables (search, sort, filter, paging, CSV export), forms, modals
- `assets/js/pages.js` per-page logic; `assets/js/admin-content.js` nav, icons, testimonials, FAQs

## Moving to Laravel
Sidebar/topbar markup repeats per page: move it into one Blade layout. Table configs in `pages.js`
and form field names (title, price, old, badge, print, pdf, ...) map directly to model columns.
Every save currently shows a toast; replace with real routes.
