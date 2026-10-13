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

## Tests

```bash
php artisan test
```
