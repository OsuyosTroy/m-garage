document.addEventListener('DOMContentLoaded', () => {
  // Mobile/tablet sidebar toggle
  const sb = document.getElementById('sidebar'), tg = document.getElementById('menuToggle');
  if (tg && sb) tg.addEventListener('click', () => sb.classList.toggle('open'));

  // Dismissible alerts, with success messages auto-hiding
  document.querySelectorAll('.alert-close').forEach(b => b.addEventListener('click', () => b.parentElement.remove()));
  document.querySelectorAll('.alert-success').forEach(a => setTimeout(() => a.remove(), 6000));

  // Confirmation dialog for sensitive actions: add data-confirm="message" to a link, button or form
  const backdrop = document.createElement('div');
  backdrop.className = 'modal-backdrop';
  backdrop.innerHTML = '<div class="modal"><h3>Please confirm</h3><p id="cfMsg"></p><div class="modal-actions"><button class="btn btn-outline" id="cfNo" type="button">Cancel</button><button class="btn btn-danger" id="cfYes" type="button">Confirm</button></div></div>';
  document.body.appendChild(backdrop);
  let pending = null;
  const ask = (msg, run) => { document.getElementById('cfMsg').textContent = msg; pending = run; backdrop.classList.add('open'); };
  document.getElementById('cfNo').onclick = () => { backdrop.classList.remove('open'); pending = null; };
  document.getElementById('cfYes').onclick = () => { backdrop.classList.remove('open'); if (pending) pending(); pending = null; };
  document.querySelectorAll('a[data-confirm]').forEach(a => a.addEventListener('click', ev => {
    ev.preventDefault(); ask(a.dataset.confirm, () => { window.location = a.href; });
  }));
  document.querySelectorAll('form[data-confirm]').forEach(f => f.addEventListener('submit', ev => {
    if (f.dataset.ok) return; ev.preventDefault(); ask(f.dataset.confirm, () => { f.dataset.ok = '1'; f.submit(); });
  }));

  // Prevent double-submit: disable submit buttons after first submit
  document.querySelectorAll('form').forEach(f => f.addEventListener('submit', () => {
    setTimeout(() => f.querySelectorAll('button[type=submit]').forEach(b => { b.disabled = true; }), 0);
  }));

  // Live table filter: <input data-filter-table="#tableId">
  document.querySelectorAll('[data-filter-table]').forEach(inp => inp.addEventListener('input', () => {
    const q = inp.value.toLowerCase();
    document.querySelectorAll(inp.dataset.filterTable + ' tbody tr').forEach(tr => { tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none'; });
  }));
});
