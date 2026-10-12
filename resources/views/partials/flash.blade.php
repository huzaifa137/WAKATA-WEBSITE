{{-- Server messages (status, fail, validation errors) shown with the admin/account toast and inline field errors. --}}
@php
    $flashFields = [];
    foreach ($errors->getBag('default')->messages() as $flashKey => $flashMsgs) {
        $flashFields[$flashKey] = $flashMsgs[0];
    }
@endphp
@if (session('status') || session('fail') || $errors->any())
<script>
document.addEventListener('DOMContentLoaded', function () {
  var A = window.ADM, ok = @json(session('status')), bad = @json(session('fail')), fields = @json($flashFields), keys = Object.keys(fields);
  keys.forEach(function (k) {
    var el = document.querySelector('[name="' + k + '"]'), fl = el && el.closest('.field');
    if (fl) { fl.classList.add('bad'); var e = fl.querySelector('.err'); if (e) e.textContent = fields[k]; }
  });
  if (!A) return;
  if (ok) A.toast(A.esc(ok));
  if (bad) A.toast(A.esc(bad), 'err'); else if (keys.length) A.toast(A.esc(fields[keys[0]]), 'err');
});
</script>
@endif
