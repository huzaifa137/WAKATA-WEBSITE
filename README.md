# WAKATA Stationers

Online shop and admin console for the Wakiso Kampala Teachers' Association (WAKATA): books, past papers,
exam sets and holiday packages. Laravel 12, plain Blade views, custom session sign-in (`LoggedAdmin`, `LoggedCustomer`).

## Run it locally

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite          # or point DB_* in .env at MySQL
php artisan migrate --seed
php artisan serve
```

Set `WAKATA_SEED_PASSWORD` in `.env` before seeding. It becomes the password of the seeded admin
(`grace@wakatastationers.com`) and, outside production, the demo customer. Without it, local installs fall back to
a throwaway password and production refuses to seed the admin.

## Where things live

| Area | Where |
| --- | --- |
| Public site | `routes/site.php`, `resources/views/site`, `public/assets/js/app.js` |
| Customer account | `routes/account.php`, `resources/views/account` |
| Admin console | `routes/admin.php`, `resources/views/admin` |
| Catalogue (books, papers, sets, packages, levels, subjects) | `app/Models`, `app/Support/Catalogue.php`, `database/seeders/data/catalogue.json` |
| Admin catalogue (module 7) | `app/Http/Controllers/Admin/*Controller.php`, `app/Support/AdminCatalogue.php`, `Inventory.php`, `Media.php`, `public/assets/admin/js/pages.js` |
| Engagement and site content (module 9) | `app/Http/Controllers/ContactController.php`, `NewsletterController.php`, `app/Http/Controllers/Admin/{Message,Subscriber,Testimonial,Faq,Content,Settings}Controller.php`, `app/Support/SiteContent.php`, `SiteSettings.php`, `AdminEngagement.php` |
| Cart and checkout | `app/Support/Cart.php`, `CartController`, `CheckoutController`, `Order` and `OrderItem` models, `config/wakata.php` (delivery fees, payment methods) |

## Catalogue

The catalogue lives in the database. `CatalogueSeeder` loads the starter data once (it never overwrites records you
have edited, so it is safe to run again). Prices in the starter data are sample values: review them before launch.

`App\Support\Catalogue` turns the database into the structure the page scripts read (`WK.catalogue`, merged into `WK`
by `public/assets/js/data.js`). It only includes published items and is cached until any catalogue record is saved or
deleted. Image paths in the database are relative to `public/assets/img`.

## Cart and checkout

The cart lives in the visitor's session (`App\Support\Cart`). The browser only says *what* was added (type, catalogue id,
format, quantity); titles, prices, delivery fees and totals are always worked out by Laravel from the database, so a
price can never be changed from the browser. Products that are unpublished after being added drop out of the cart.

Checkout (`POST /checkout`) creates an `orders` row plus `order_items` rows that keep the title and price of the day.
Order numbers are `WK-` plus 10000 plus the row id (the first order is `WK-10001`). Visitors can order without an account;
a signed-in customer's order is attached to their account. Orders start as `pending` with payment `pending`: no payment
gateway is connected yet, so staff confirm payment by phone or email. Delivery fees and payment methods are in
`config/wakata.php`. Set `WAKATA_ORDER_NOTIFY` in `.env` to email staff about every new order.

## Admin catalogue (module 7)

Books, past papers, exam sets, holiday packages, subjects and levels, media and inventory are managed under
`/admin`. Everyone signed in to the admin can look; saving, deleting, uploading and stock changes also need the role
super admin, manager or editor (`Admin::canManageCatalogue()`, middleware `admin.catalogue`).

- **Saving.** The forms are real Laravel forms. "Save as draft" hides the item from the website, "Save and publish" shows
  it. Saved changes appear on the website straight away (the catalogue cache is cleared on every save).
- **Past papers.** Printed and PDF can each be switched on or off with their own price. A paper with both off is not
  sold. Paper PDFs (and marking guides, exam set guides, package timetables) are stored on the **private** `local`
  disk, never under `public/`, and customers only get them through their library (download limit applies).
- **Bulk PDF upload.** Name files like `mathematics-paper1-2026.pdf`. If two levels share the name, start with the level:
  `uce-biology-paper1-2023.pdf`.
- **Images.** Covers and paper sheets are stored in `public/assets/img/books` and `public/assets/img/papers`
  (`config('wakata.image_dir')`). On a host where `public/` is read-only, point that setting at a writable folder.
- **Inventory.** Printed stock for books, printed papers, exam sets and packages. Every change is logged in
  `stock_adjustments` (add, remove or set; stock never goes below zero). Starting stock is 0 for everything: enter the
  real counts under Inventory. Selling does not reduce stock yet; that is wired to order confirmation in module 8.
- **Settings saved for module 8.** The form switches for bulk threshold, watermark, automatic email, "include marking
  guides" and "allow swapping a book" are saved (in each table's `options` column) but only take effect when orders,
  payments and deliveries are built.
- **Sales numbers** on these pages count paid orders that were not cancelled or refunded.

## Engagement and site content (module 9)

- **Contact form and newsletter.** The Contact page form saves a message (ref `M-1001` ...) and the "Get new papers first" form saves a
  subscriber. Both have a hidden field for robots and are rate limited. Every newsletter email carries that person's own unsubscribe link.
- **Messages.** One inbox for the Contact page and for questions customers ask from their account (`T-1001` ...). Staff reply by email
  (a reply is only marked as sent when the email went out), archive, mark unread, delete contact messages, and send a quotation: the
  server totals the lines and emails it. Customer questions are answered in the same box and appear in the customer's account.
- **Subscribers and newsletters.** Add, remove, unsubscribe, bulk actions, and send to all, schools only or teachers only (by the type of
  the matching customer account). One send reaches up to 1,000 people in a single request; "Send test to me" goes only to the signed-in admin.
- **Testimonials and Questions.** The home page row and the Contact / Holiday packages questions come from the database. The ten quotes and
  ten questions that were typed into the pages were copied in by the migration, so the site looks the same after the upgrade. A page whose
  testimonials are all hidden leaves that section out. Questions can be dragged into a new order.
- **Site content.** Every editable text has a built-in default equal to the old wording; saving a blank (or the default) goes back to it.
  Contact details also feed `config('wakata.*')`, so the top strip, WhatsApp button and emails follow them. Page titles and descriptions are
  editable. Delivery and payment choices are not edited here: they live on the Deliveries and Payments pages.
- **Settings.** Public email (the same as the Contact email), monthly sales goal (used by the dashboard in the next module), downloads allowed
  per paid PDF, team alert recipients and switches (new order, new message), Google Analytics ID, and CSV exports (orders, customers,
  subscribers, books; super admin or manager only). Invoice wording, security and backups are not part of this module.
- **Roles.** Messages and subscribers: super admin, manager, support. Website content, testimonials, questions: super admin, manager, editor.
  Settings: super admin, manager. Everyone signed in can look.

## Tests

```bash
php artisan test
```

## Module 8: admin sales

- **Orders**: real list, tabs, filters, CSV; bulk confirm / processing / email; "Create order" for phone and walk-in orders (priced by the server like the shop); order page with status, cancel, internal note, courier note, email customer, resend PDF files.
- **Payments**: verify and refund from the table or the order page; the four payment-method switches decide what the cart page shows and what checkout accepts; Reconcile lists payments still waiting.
- **Deliveries**: board moves change the order status; "Options and fees" saves the names, times and fees checkout uses (and can switch an option off); PDF emails tab with Resend.
- **Customers**: registered customers plus guest buyers (grouped by email), Add customer, internal note.
- **Rules** (`App\Support\OrderFlow`): cancelled/refunded orders are closed; a paid order is refunded, not cancelled; completing needs payment (cash is paid by collecting); confirming takes printed stock once, cancel/refund puts it back (stock history reasons `sale` and `return`).
- **Access**: orders, deliveries, customers need super admin, manager or support (`admin.can:sales`); payments, payment methods and delivery fees need super admin or manager (`admin.can:payments`). Everyone signed in can look.
- Run `php artisan migrate` (adds order/customer columns).
