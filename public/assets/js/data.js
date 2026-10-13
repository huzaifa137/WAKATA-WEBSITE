/* ==========================================================
   WAKATA catalogue helpers.
   The catalogue itself (levels, subjects, books, past papers, exam sets, holiday packages) now comes from the
   database: Laravel puts it in WK.catalogue (partials/catalogue.blade.php, built by App\Support\Catalogue).
   This file merges it into the WK globals Laravel already set (WK.base, WK.r, WK.u, WK.contact) and adds
   the small helpers every page uses. Prices are in UGX.
   ========================================================== */
window.WK = Object.assign(window.WK || {}, (function () {
  const C = (window.WK && window.WK.catalogue) || {};

  const LEVELS = C.LEVELS || {};              // { ple: { label, short, book, full }, uce: ..., uace: ... }
  const BOOK_LEVELS = C.BOOK_LEVELS || {};    // { primary: 'Primary', o: 'O-Level', a: 'A-Level' }
  const SUBJECTS = C.SUBJECTS || {};          // subject -> [c1, c2, c3] cover colours
  const books = C.books || [];
  const papers = C.papers || [];
  const sets = C.sets || [];
  const packages = C.packages || [];
  const featured = C.featured || { books: [], papers: [], packages: [] };   // home page picks, in order
  const PAPER_IMG = C.PAPER_IMG || '';

  /* ---------------- helpers ---------------- */
  const money = n => 'UGX ' + Number(n).toLocaleString('en-UG');
  const byId = (arr, id) => arr.find(x => x.id === id);

  return { LEVELS, BOOK_LEVELS, SUBJECTS, books, papers, sets, packages, featured, money, byId, PAPER_IMG };
})());
