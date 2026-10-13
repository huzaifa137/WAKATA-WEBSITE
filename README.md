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

## Catalogue

The catalogue lives in the database. `CatalogueSeeder` loads the starter data once (it never overwrites records you
have edited, so it is safe to run again). Prices in the starter data are sample values: review them before launch.

`App\Support\Catalogue` turns the database into the structure the page scripts read (`WK.catalogue`, merged into `WK`
by `public/assets/js/data.js`). It only includes published items and is cached until any catalogue record is saved or
deleted. Image paths in the database are relative to `public/assets/img`.

## Tests

```bash
php artisan test
```
