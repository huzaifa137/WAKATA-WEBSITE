# Module 11: Hardening and the admin profile

## After applying the patch

    php artisan migrate                       # newsletter status, admin photo and alert choices
    php artisan test                          # 342 tests

Optional `.env` lines (all have safe defaults):

    SECURITY_CSP=enforce      # enforce | report | off   (browser protections, see config/security.php)
    NEWSLETTER_QUEUE=false    # true = big newsletters use `php artisan queue:work`
    ACTIVITY_KEEP_DAYS=365    # the admin activity log is pruned weekly after this many days (minimum 30)

## What changed

1. **My profile (admin)** is real: name, phone, photo (JPG/PNG/WebP, 1 MB, checked by content), password change (needs the
   current password, rate limited, signs out "keep me signed in" devices) and personal email alerts for new orders and
   messages. The email address stays read-only (it is the sign-in name; a super admin changes it under Team and roles).
2. **Stock cannot be deducted twice.** Order status, payment confirmation and refund lock the order row and re-read it, so
   two people (or a double click) on the same order cannot take the stock twice.
3. **Stock rule (Settings, General).** "Stop selling printed items that are out of stock" is OFF by default because most
   products still have stock 0. Turn it on once stock numbers are entered. When on, a cart never holds more than the shelf,
   and checkout answers "low on stock" with the corrected cart. PDFs and staff phone orders are not limited.
4. **Customer details are private.** A team member without sales rights (the content editor) gets customer names shortened
   ("Green H."), and no email, phone, address, notes or payment references, on every page, in the search box and the bell.
   Support, manager and super admin see everything as before.
5. **Email never slows a customer down.** Order received / order update emails are sent after the page has been sent to the
   browser (no queue worker needed). A newsletter to more than 25 people is sent in the background and its history line
   shows "sending" until the counts are final.
6. **Security headers and error pages.** Anti-framing, nosniff, referrer and permissions policies, HSTS on https and a
   Content-Security-Policy (config/security.php). Admin and account pages are never cached by the browser. Branded 403, 419,
   429, 500 and 503 pages.
7. **Passwords.** Team: 10+ characters with a letter and a number. Customers: 8+. On the live site both are also checked
   against the public list of leaked passwords.
8. **Checkout double click** makes one order (the second press gets the same order back; a lock stops two at once).
9. **Activity log**: failed and blocked sign-ins are recorded (capped at 30 an hour per address), profile changes are
   logged, and lines older than a year are deleted weekly (`php artisan wakata:prune-activity`).

## When you add a payment gateway (Pesapal, Marz Pay ...)

- If the customer is sent to the gateway's page by a form post or shown its frame, add that address under `form` or `frame`
  in `config/security.php`, otherwise the Content-Security-Policy will block it.
- Confirm payments through `OrderFlow::markPaid()` from a webhook (verify the gateway's signature first). It is already safe to call twice.
- Keep `php artisan schedule:run` on cron (emailed report and log pruning).
