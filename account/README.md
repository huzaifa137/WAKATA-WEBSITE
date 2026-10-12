# WAKATA customer account (front end)

Open `account/login.html`. Preview mode: any valid email and a password of 6+ characters signs you in and shows sample orders.
Use **Create account** to see the empty state of a brand-new customer.

Pages: dashboard, downloads and library, orders, order detail, invoices, invoice (print), saved items, addresses, help and support, profile and settings, login, register, forgot and reset password.

- PDF downloads generate a small real PDF in the browser (limit of 5 per file) so the flow can be tested. The backend should serve the real watermarked file.
- "Buy again" and "Add to cart" write to the site's cart (`wakata_cart_v1`), so they work with `cart.html`.
- `assets/js/account-data.js` builds SAMPLE orders from the real catalogue. Replace with Laravel data.
- Sidebar and top bar repeat per page: move them into one Blade layout.
