# WAKATA website: front-end (UI) prototype

Open `index.html` in a browser. No build step, no install. Needs internet only for the Google Fonts
(Source Serif 4 + Public Sans); it falls back to system fonts offline.

## Pages
index, books, book (detail, `book.html?id=bio-uce-practical`), past-papers, exam-sets,
holiday-packages, about, contact, cart (cart + checkout UI).

## Structure
- `assets/css/style.css`   all styles. Design tokens are at the top (`:root`); logo blue is `--blue`.
- `assets/js/data.js`      sample catalogue (books, past papers, exam sets, holiday packages) and prices.
- `assets/js/app.js`       rendering, filters, cart (localStorage), form validation, toasts.
- `assets/js/enhance.js`   v2 polish layer: scroll progress, back to top, WhatsApp button, reveal, count-up, parallax, fly-to-cart, searchable dropdowns.
- `assets/img/`            logo (blue + white versions), your sample covers and papers.

## What changed in v2
- Hero: the lined exercise-book background is replaced by a deep logo-blue stage with the logo's open-book shape as large translucent planes, a dot grid and a soft glow. The paper collage drifts slightly with the mouse. The bottom edge is cut on a slant to echo the logo.
- Inner page headers use the same blue and book-plane treatment.
- Hover effects, each area with its own: nav underline, button sweep, level tiles flood with blue, book covers swing open, past-paper sheets zoom with a sheen, packages grow a top bar and pop their ticks, exam-set stacks fan out, steps spin, info/value/quote cards lift, footer links slide. All hover effects are limited to devices with a mouse.
- New: stats card (counts come from `data.js`), testimonials, scroll progress bar, condensing header, back-to-top button, WhatsApp chat button, scroll reveal, fly-to-cart animation with badge pop, card fade-in when filters change.
- Reduced-motion settings are respected: movement is switched off.

## Replace before launch
- WhatsApp number in `assets/js/enhance.js` (`wa.me/256700000000`) is a placeholder.
- The ten testimonials on the home page (the scrolling "What teachers say" row) are sample text. Replace them with real quotes (and permission) from schools.
- Phone, email and address (`+256 700 000 000`, `info@wakatastationers.com`) are placeholders.
- All prices, page counts and descriptions in `data.js` are sample values.
- Titles without a photo use a generated cover; swap in real covers via the `img` field.
- Past papers without a photo use a generated sheet; add images via `realPaper` in `data.js`.
- Checkout, forms and newsletter are UI only. Nothing is sent or charged.

## Moving to Laravel
- Header, footer and page shell repeat in every file: turn them into a Blade layout (`@extends`/`@yield`).
- Product cards are built in `app.js` (`bookCard`, `paperCard`, `setCard`, `packCard`). Port each to a Blade partial
  and loop over Eloquent results; the CSS classes can stay as they are.
- `data.js` maps one-to-one to future tables: books, past_papers (level, subject, paper, year, print_price, pdf_price),
  exam_sets, holiday_packages, orders, order_items.
- Cart: replace `localStorage` with a session or database cart behind the same add/remove/quantity actions.

## v6 additions
- **Testimonials marquee** (`index.html`, `.mq` in `style.css`, logic in `enhance.js`): ten cards flow right to left, pause on hover/touch, and have a Pause/Play button. With "reduce motion" on, the row becomes a normal swipeable strip. To add or remove a testimonial, edit the `<figure class="quote">` items inside `.mq-group`; the loop copies itself in JavaScript.
- **Mobile app bar** (phones, 760px and below): bottom tabs for Home, Books, Papers, Cart (with live count) and More. "More" opens a bottom sheet with Exam sets, Holiday packages, About, Contact, WhatsApp and Call. Built in `enhance.js`, styled under "v6" in `style.css`, so no page markup needs to change.
