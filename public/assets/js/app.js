/* iERA App - vanilla JS enhancements (no dependencies) */
(function () {
  'use strict';
  const $ = (s, r) => (r || document).querySelector(s);
  const $$ = (s, r) => Array.from((r || document).querySelectorAll(s));
  const csrf = () => ($('meta[name=csrf-token]') || {}).content || '';

  // ---- sidebar ----------------------------------------------------------
  $$('[data-nav-toggle]').forEach(el => el.addEventListener('click', () => document.body.classList.toggle('nav-open')));

  // ---- modals -----------------------------------------------------------
  $$('[data-open]').forEach(btn => btn.addEventListener('click', () => {
    const closeId = btn.getAttribute('data-close');
    if (closeId) { const c = document.getElementById(closeId); c && c.close && c.close(); }
    const d = document.getElementById(btn.getAttribute('data-open'));
    if (d && d.showModal) { d.showModal(); const f = d.querySelector('input,select,textarea'); f && f.focus(); }
  }));
  $$('[data-close]').forEach(btn => btn.addEventListener('click', () => {
    if (btn.hasAttribute('data-open')) return;
    const d = document.getElementById(btn.getAttribute('data-close')); d && d.close && d.close();
  }));
  $$('dialog[data-autoopen]').forEach(d => d.showModal && d.showModal());
  $$('dialog.modal').forEach(d => d.addEventListener('click', e => { if (e.target === d) d.close(); }));
  // Open a modal and fill it with data-* values from the trigger (data-fill-xxx -> [name=xxx])
  $$('[data-modal]').forEach(btn => btn.addEventListener('click', () => {
    const d = document.getElementById(btn.getAttribute('data-modal'));
    if (!d) return;
    Object.keys(btn.dataset).forEach(k => {
      if (!k.startsWith('fill')) return;
      const name = k.slice(4).replace(/^[A-Z]/, c => c.toLowerCase()).replace(/[A-Z]/g, c => '_' + c.toLowerCase());
      $$('[name="' + name + '"], [data-fill-target="' + name + '"]', d).forEach(el => {
        if (el.tagName === 'INPUT' || el.tagName === 'SELECT' || el.tagName === 'TEXTAREA') el.value = btn.dataset[k]; else el.textContent = btn.dataset[k];
      });
      if (name === 'action') { const f = d.querySelector('form'); if (f) f.action = btn.dataset[k]; }
    });
    d.showModal();
  }));

  // ---- confirm ------------------------------------------------------------
  $$('form[data-confirm]').forEach(f => f.addEventListener('submit', e => { if (!confirm(f.getAttribute('data-confirm'))) e.preventDefault(); }));

  // ---- phone formatting ----------------------------------------------------
  $$('input[data-phone]').forEach(inp => inp.addEventListener('input', () => {
    let d = inp.value.replace(/\D/g, '');
    if (d.length === 11 && d[0] === '1') d = d.slice(1);
    d = d.slice(0, 10);
    let out = d;
    if (d.length > 6) out = '(' + d.slice(0, 3) + ') ' + d.slice(3, 6) + '-' + d.slice(6);
    else if (d.length > 3) out = '(' + d.slice(0, 3) + ') ' + d.slice(3);
    inp.value = out;
  }));

  // ---- city autocomplete ----------------------------------------------------
  $$('input[data-city-autocomplete]').forEach(inp => {
    let t;
    inp.addEventListener('input', () => {
      clearTimeout(t);
      const q = inp.value.trim();
      if (q.length < 2) return;
      t = setTimeout(() => fetch((window.APP_BASE || '') + '/api/cities?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } })
        .then(r => r.json()).then(j => {
          const list = document.getElementById(inp.getAttribute('list'));
          if (!list) return;
          list.innerHTML = '';
          (j.cities || []).forEach(c => { const o = document.createElement('option'); o.value = c; list.appendChild(o); });
        }).catch(() => {}), 180);
    });
  });

  // ---- conditional fields -------------------------------------------------------
  function fieldValue(form, name) {
    const els = $$('[name="' + name + '"], [name="' + name + '[]"]', form);
    if (!els.length) return null;
    if (els[0].type === 'checkbox' && els.length > 1 || els[0].name.endsWith('[]')) return els.filter(e => e.checked).map(e => e.value);
    if (els[0].type === 'checkbox') return els[0].checked ? '1' : '';
    return els[0].value;
  }
  function applyConditions(form) {
    $$('[data-condition]', form).forEach(wrap => {
      let c; try { c = JSON.parse(wrap.getAttribute('data-condition')); } catch (e) { return; }
      const v = fieldValue(form, c.name);
      let match = Array.isArray(v) ? v.indexOf(String(c.value)) !== -1 : String(v == null ? '' : v) === String(c.value);
      if (c.not) match = !match;
      wrap.hidden = !match;
      $$('input,select,textarea', wrap).forEach(el => { el.disabled = !match && !el.hasAttribute('data-keep-enabled'); });
    });
  }
  $$('form').forEach(form => {
    if (!$('[data-condition]', form)) return;
    applyConditions(form);
    form.addEventListener('change', () => applyConditions(form));
    form.addEventListener('input', () => applyConditions(form));
  });

  // ---- multi-step forms (stepper) ----------------------------------------------
  $$('[data-stepper]').forEach(st => {
    const steps = $$('.form-step', st);
    const tabs = $$('[data-step-tab]', st);
    const prev = $('[data-step-prev]', st), next = $('[data-step-next]', st), submit = $('[data-step-submit]', st);
    let i = 0;
    function validStep() {
      let ok = true;
      $$('.field', steps[i]).forEach(f => {
        if (f.hidden) return;
        const req = $('[data-required]', f);
        f.classList.remove('has-error');
        if (!req || req.disabled) return;
        let empty;
        if (req.type === 'checkbox') empty = !req.checked;
        else if (req.type === 'file') empty = !req.files.length && !$('.file-list', f);
        else empty = !String(req.value || '').trim();
        if (empty) { ok = false; f.classList.add('has-error'); }
      });
      $$('[data-required-group]', steps[i]).forEach(g => {
        const f = g.closest('.field'); if (!f || f.hidden) return;
        const any = $$('[name="' + g.name + '"]', f).some(x => x.checked);
        if (!any) { ok = false; f.classList.add('has-error'); }
      });
      if (!ok) { const first = $('.field.has-error', steps[i]); first && first.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
      return ok;
    }
    function show(n) {
      i = Math.max(0, Math.min(steps.length - 1, n));
      steps.forEach((s, k) => s.hidden = k !== i);
      tabs.forEach((t, k) => { t.classList.toggle('is-active', k === i); t.classList.toggle('is-done', k < i); });
      prev.hidden = i === 0; next.hidden = i === steps.length - 1; submit.hidden = i !== steps.length - 1;
      st.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
    prev.addEventListener('click', () => show(i - 1));
    next.addEventListener('click', () => { if (validStep()) show(i + 1); });
    tabs.forEach((t, k) => t.addEventListener('click', () => { if (k < i || validStep()) show(k); }));
    st.closest('form').addEventListener('submit', e => { if (!validStep()) e.preventDefault(); });
    // If the server returned errors, jump to the first step containing an error field
    const err = document.querySelector('.flash-error');
    if (err) { show(0); }
  });

  // ---- text arrays ---------------------------------------------------------------
  $$('[data-text-array]').forEach(box => box.addEventListener('input', e => {
    const inputs = $$('input', box);
    if (inputs[inputs.length - 1].value.trim() !== '') { const n = inputs[0].cloneNode(); n.value = ''; box.appendChild(n); }
  }));

  // ---- resend countdown ---------------------------------------------------------------
  $$('[data-resend]').forEach(btn => { let s = 30; btn.disabled = true; const t0 = btn.textContent; const iv = setInterval(() => { s--; btn.textContent = t0 + ' (' + s + ')'; if (s <= 0) { clearInterval(iv); btn.disabled = false; btn.textContent = t0; } }, 1000); btn.textContent = t0 + ' (' + s + ')'; });

  // ---- data tables: search, filters, sort, pagination, CSV ----------------------------
  $$('table[data-table]').forEach(table => {
    const tbody = table.tBodies[0]; if (!tbody) return;
    const rows = Array.from(tbody.rows);
    const headers = Array.from(table.tHead ? table.tHead.rows[0].cells : []);
    const pageSizeDefault = parseInt(table.getAttribute('data-page-size') || '10', 10);
    let pageSize = pageSizeDefault, page = 1, sortCol = -1, sortDir = 1, query = '';
    const filters = {};

    const toolbar = document.createElement('div'); toolbar.className = 'table-toolbar';
    const search = document.createElement('input'); search.type = 'search'; search.className = 'input'; search.placeholder = 'Search…';
    toolbar.appendChild(search);
    headers.forEach((th, ci) => {
      if (!th.hasAttribute('data-filter')) return;
      const vals = Array.from(new Set(rows.map(r => (r.cells[ci] ? r.cells[ci].textContent.trim() : '')).filter(Boolean))).sort();
      if (vals.length < 2) return;
      const sel = document.createElement('select');
      sel.innerHTML = '<option value="">' + th.textContent.trim() + ': All</option>' + vals.map(v => '<option>' + v.replace(/</g, '&lt;') + '</option>').join('');
      sel.addEventListener('change', () => { filters[ci] = sel.value; page = 1; render(); });
      toolbar.appendChild(sel);
    });
    const sizeSel = document.createElement('select');
    [5, 10, 15, 20, 25, 50, 100, 'All'].forEach(n => { const o = document.createElement('option'); o.value = n; o.textContent = n + ' rows'; if (n === pageSizeDefault) o.selected = true; sizeSel.appendChild(o); });
    sizeSel.addEventListener('change', () => { pageSize = sizeSel.value === 'All' ? Infinity : parseInt(sizeSel.value, 10); page = 1; render(); });
    toolbar.appendChild(sizeSel);
    const exportName = table.getAttribute('data-export');
    if (exportName) {
      const b = document.createElement('button'); b.type = 'button'; b.className = 'btn btn-sm'; b.textContent = 'CSV';
      b.addEventListener('click', () => {
        const esc = v => '"' + String(v).replace(/"/g, '""') + '"';
        const lines = [headers.map(h => esc(h.textContent.trim())).join(',')];
        visible().forEach(r => lines.push(Array.from(r.cells).map(c => esc(c.getAttribute('data-value') || c.textContent.trim().replace(/\s+/g, ' '))).join(',')));
        const blob = new Blob(['﻿' + lines.join('\n')], { type: 'text/csv;charset=utf-8' });
        const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = exportName; a.click();
      });
      toolbar.appendChild(b);
    }
    const wrap = table.closest('.table-wrap') || table;
    wrap.parentNode.insertBefore(toolbar, wrap);
    const foot = document.createElement('div'); foot.className = 'table-foot';
    wrap.parentNode.insertBefore(foot, wrap.nextSibling);
    const empty = document.createElement('div'); empty.className = 'empty'; empty.textContent = table.getAttribute('data-empty') || 'No records match.'; empty.hidden = true;
    wrap.parentNode.insertBefore(empty, foot);

    headers.forEach((th, ci) => {
      if (th.hasAttribute('data-nosort')) return;
      th.setAttribute('data-sort', '');
      th.addEventListener('click', () => { if (sortCol === ci) sortDir = -sortDir; else { sortCol = ci; sortDir = 1; } headers.forEach(h => h.classList.remove('sorted-asc', 'sorted-desc')); th.classList.add(sortDir === 1 ? 'sorted-asc' : 'sorted-desc'); render(); });
    });
    search.addEventListener('input', () => { query = search.value.toLowerCase(); page = 1; render(); });

    function cellVal(r, ci) { const c = r.cells[ci]; return c ? (c.getAttribute('data-value') || c.textContent.trim()) : ''; }
    function visible() {
      let v = rows.filter(r => (!query || r.textContent.toLowerCase().indexOf(query) !== -1) && Object.keys(filters).every(ci => !filters[ci] || cellVal(r, ci) === filters[ci]));
      if (sortCol >= 0) v.sort((a, b) => { const x = cellVal(a, sortCol), y = cellVal(b, sortCol); const nx = parseFloat(x), ny = parseFloat(y); const cmp = (!isNaN(nx) && !isNaN(ny) && /^-?[\d.,]+$/.test(x)) ? nx - ny : x.localeCompare(y, undefined, { numeric: true }); return cmp * sortDir; });
      return v;
    }
    function render() {
      const v = visible();
      const total = v.length, pages = pageSize === Infinity ? 1 : Math.max(1, Math.ceil(total / pageSize));
      page = Math.min(page, pages);
      const start = pageSize === Infinity ? 0 : (page - 1) * pageSize;
      const slice = pageSize === Infinity ? v : v.slice(start, start + pageSize);
      rows.forEach(r => r.remove());
      slice.forEach(r => tbody.appendChild(r));
      empty.hidden = total > 0; table.hidden = total === 0;
      foot.innerHTML = '<span>Showing ' + (total ? start + 1 : 0) + ' to ' + Math.min(start + slice.length, total) + ' of ' + total + ' entries</span>';
      if (pages > 1) {
        const pager = document.createElement('div'); pager.className = 'pager';
        const mk = (label, p, dis) => { const b = document.createElement('button'); b.type = 'button'; b.className = 'btn btn-sm' + (p === page ? ' btn-primary' : ''); b.textContent = label; b.disabled = !!dis; b.addEventListener('click', () => { page = p; render(); }); return b; };
        pager.appendChild(mk('<', page - 1, page === 1));
        let lo = Math.max(1, page - 2), hi = Math.min(pages, lo + 4); lo = Math.max(1, hi - 4);
        for (let p = lo; p <= hi; p++) pager.appendChild(mk(String(p), p));
        pager.appendChild(mk('>', page + 1, page === pages));
        foot.appendChild(pager);
      }
    }
    render();
  });

  // ---- clickable rows --------------------------------------------------------------
  document.addEventListener('click', e => {
    const row = e.target.closest('tr[data-href]');
    if (!row || e.target.closest('a,button,input,select,label,form')) return;
    window.location = row.getAttribute('data-href');
  });

  // ---- calendar -----------------------------------------------------------------------
  const cal = $('[data-calendar]');
  if (cal) {
    let cur = new Date(); cur.setDate(1);
    let scope = cal.getAttribute('data-scope') || 'all';
    const grid = $('[data-cal-grid]', cal), title = $('[data-cal-title]', cal), legend = $('[data-cal-legend]', cal), next = $('[data-cal-next]', cal);
    const pad = n => String(n).padStart(2, '0');
    const fmt = d => d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
    const tf = s => { const d = new Date(s.replace(' ', 'T')); return d.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' }); };
    function load() {
      const from = new Date(cur), to = new Date(cur.getFullYear(), cur.getMonth() + 1, 0);
      title.textContent = cur.toLocaleDateString([], { month: 'long', year: 'numeric' });
      fetch((window.APP_BASE || '') + '/api/calendar?from=' + fmt(from) + '&to=' + fmt(to) + '&scope=' + scope, { headers: { Accept: 'application/json' } }).then(r => r.json()).then(j => {
        const byDay = {};
        (j.events || []).forEach(ev => { const k = ev.start.slice(0, 10); (byDay[k] = byDay[k] || []).push(ev); });
        grid.innerHTML = '';
        ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].forEach(d => { const c = document.createElement('div'); c.className = 'cal-dow'; c.textContent = d; grid.appendChild(c); });
        const first = new Date(cur); const startDow = first.getDay();
        const start = new Date(first); start.setDate(1 - startDow);
        const today = fmt(new Date());
        for (let i = 0; i < 42; i++) {
          const d = new Date(start); d.setDate(start.getDate() + i);
          const key = fmt(d);
          const cell = document.createElement('div');
          cell.className = 'cal-day' + (d.getMonth() !== cur.getMonth() ? ' is-other' : '') + (key === today ? ' is-today' : '');
          cell.innerHTML = '<div class="cal-daynum">' + d.getDate() + '</div>';
          (byDay[key] || []).forEach(ev => {
            const a = document.createElement('a');
            a.className = 'cal-ev' + (ev.attending ? ' is-attending' : ''); a.style.background = ev.color;
            a.textContent = tf(ev.start) + ' ' + ev.team;
            a.title = ev.title + '\n' + ev.team + '\n' + tf(ev.start) + ' - ' + tf(ev.end) + '\n' + (ev.description || '') + (ev.city ? '\n' + ev.city : '') + '\nMin participants: ' + ev.min + (ev.attending ? '\nYou are registered' : '');
            a.href = (window.APP_BASE || '') + '/events/register/' + encodeURIComponent(ev.team_id);
            cell.appendChild(a);
          });
          grid.appendChild(cell);
        }
        legend.innerHTML = (j.teams || []).map(t => '<span style="--c:' + t.color + '">' + t.name.replace(/</g, '&lt;') + '</span>').join('');
        if (next) {
          const upcoming = (j.events || []).filter(ev => new Date(ev.start.replace(' ', 'T')) >= new Date()).slice(0, 5);
          next.innerHTML = upcoming.length ? upcoming.map(ev => '<li><strong>' + new Date(ev.start.replace(' ', 'T')).toLocaleDateString([], { weekday: 'short', month: 'short', day: 'numeric' }) + ' ' + tf(ev.start) + '</strong><br>' + ev.title.replace(/</g, '&lt;') + ' · ' + (ev.city || '').replace(/</g, '&lt;') + '</li>').join('') : '<li class="muted">No upcoming events this month.</li>';
        }
      });
    }
    $('[data-cal-prev]', cal).addEventListener('click', () => { cur.setMonth(cur.getMonth() - 1); load(); });
    $('[data-cal-next-month]', cal).addEventListener('click', () => { cur.setMonth(cur.getMonth() + 1); load(); });
    $$('[data-cal-scope]', cal).forEach(b => b.addEventListener('click', () => { scope = b.getAttribute('data-cal-scope'); $$('[data-cal-scope]', cal).forEach(x => x.classList.toggle('is-active', x === b)); load(); }));
    load();
  }

  // ---- auto-submit selects -------------------------------------------------------------
  $$('select[data-autosubmit]').forEach(s => s.addEventListener('change', () => s.form && s.form.submit()));
})();

// notification bell
(() => {
  const wrap = document.querySelector('[data-bell]'); if (!wrap) return;
  const toggle = wrap.querySelector('[data-bell-toggle]'), panel = wrap.querySelector('[data-bell-panel]'), list = wrap.querySelector('[data-bell-list]');
  let loaded = false;
  const esc = s => String(s == null ? '' : s).replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
  const base = (document.querySelector('meta[name="app-base"]') || {}).content || '';
  toggle.addEventListener('click', e => {
    if (window.innerWidth < 700) return; // phones go straight to the page
    e.preventDefault(); panel.hidden = !panel.hidden;
    if (!panel.hidden && !loaded) {
      fetch(toggle.getAttribute('href').replace(/\/notifications$/, '/notifications/feed'), { headers: { Accept: 'application/json' } }).then(r => r.json()).then(d => {
        loaded = true;
        list.innerHTML = d.items.length ? d.items.map(i => `<a class="bell-item ${i.read ? '' : 'is-unread'}" href="${i.link ? esc(i.link) : toggle.getAttribute('href')}"><b>${esc(i.title)}</b>${i.message ? esc(i.message).slice(0, 120) : ''}<br><small>${esc(i.when)}</small></a>`).join('') : '<div class="muted small" style="padding:14px">No notifications yet.</div>';
      }).catch(() => { list.innerHTML = '<div class="muted small" style="padding:14px">Could not load.</div>'; });
    }
  });
  document.addEventListener('click', e => { if (!wrap.contains(e.target)) panel.hidden = true; });
})();

// onboarding wizard (iERA Connect)
(() => {
  const $ = (s, c) => (c || document).querySelector(s), $$ = (s, c) => Array.from((c || document).querySelectorAll(s));
  const form = $('[data-wizard]');
  const csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
  // conditional fields: data-show-when="field=value", data-show-checked="name=Value|Other"
  function applyShow(root) {
    $$('[data-show-when]', root).forEach(el => {
      const [name, val] = el.getAttribute('data-show-when').split('=');
      const inp = root.querySelector(`[name="${name}"]:checked`) || root.querySelector(`select[name="${name}"]`);
      el.hidden = !(inp && inp.value === val);
    });
    $$('[data-show-checked]', root).forEach(el => {
      const [name, vals] = el.getAttribute('data-show-checked').split('=');
      const wanted = vals.split('|');
      el.hidden = !$$(`[name="${CSS.escape(name)}"]:checked`, root).some(i => wanted.includes(i.value));
    });
  }
  if (form) {
    applyShow(form);
    form.addEventListener('change', () => applyShow(form));
    // category change reloads the stage so category-specific questions appear
    $$('[data-reload-on-change]', form).forEach(r => r.addEventListener('change', () => { save().then(() => location.reload()); }));
    // social media toggle
    const noSocial = $('[data-no-social]', form);
    if (noSocial) { const sync = () => $$('[data-social-inputs] input', form).forEach(i => { i.disabled = noSocial.checked; if (noSocial.checked) i.value = ''; }); noSocial.addEventListener('change', sync); }
    // DOB warning
    const dob = $('[data-dob]', form);
    if (dob) { const w = $('[data-dob-warning]', form); const chk = () => { if (!dob.value) { w.hidden = true; return; } const d = new Date(dob.value), t = new Date(); let a = t.getFullYear() - d.getFullYear(); const m = t.getMonth() - d.getMonth(); if (m < 0 || (m === 0 && t.getDate() < d.getDate())) a--; w.hidden = a >= 18; }; dob.addEventListener('change', chk); chk(); }
    // tag input
    $$('[data-tags]', form).forEach(box => {
      const name = box.getAttribute('data-tags'), list = $('[data-tag-list]', box), field = $('[data-tag-field]', box);
      const has = v => $$('input[type=hidden]', list).some(i => i.value.toLowerCase() === v.toLowerCase());
      const add = v => { v = v.trim(); if (!v || has(v)) return; const t = document.createElement('span'); t.className = 'tag'; t.innerHTML = `<input type="hidden" name="${name}[]"><button type="button" class="tag-x" aria-label="Remove">&times;</button>`; t.insertBefore(document.createTextNode(v), t.firstChild); t.querySelector('input').value = v; list.appendChild(t); field.value = ''; form.dispatchEvent(new Event('change')); };
      field.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); add(field.value); } else if (e.key === 'Backspace' && !field.value && list.lastElementChild) { list.lastElementChild.remove(); form.dispatchEvent(new Event('change')); } });
      field.addEventListener('change', () => { if (field.value) add(field.value); });
      box.addEventListener('click', e => { if (e.target.classList.contains('tag-x')) { e.target.parentNode.remove(); form.dispatchEvent(new Event('change')); } });
    });
    // autosave (1.5 s debounce)
    let timer = null; const note = $('[data-autosave-text]'), wrap = $('.ob-autosave');
    function save() {
      const fd = new FormData(form);
      return fetch(form.getAttribute('data-autosave'), { method: 'POST', body: fd, headers: { 'X-Requested-With': 'fetch', 'X-CSRF-Token': csrf }, credentials: 'same-origin' }).then(r => r.ok ? r.json() : null).then(d => { if (d && note) { note.textContent = 'Saved ' + d.saved_at + ' — you can return anytime to continue.'; wrap.classList.add('is-saved'); } }).catch(() => {});
    }
    form.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(save, 1500); });
    form.addEventListener('change', () => { clearTimeout(timer); timer = setTimeout(save, 800); });
  }
  // agreement: scroll gate + signature match
  const ag = $('[data-agreement]');
  if (ag) {
    const doc = $('[data-agreement-doc]', ag), status = $('[data-agreement-status]', ag), gate = $('[data-agreement-gate]', ag), f = $('[data-agreement-form]', ag);
    const sig = $('[data-signature]', f), btn = $('[data-sign-btn]', f), err = $('[data-sig-error]', f), ok = $('[data-sig-ok]', f);
    const norm = s => s.trim().replace(/\s+/g, ' ').toLowerCase();
    let read = false;
    const check = () => { const match = sig.value.trim() !== '' && norm(sig.value) === norm(sig.getAttribute('data-fullname')); err.hidden = !(sig.value.trim() && !match); ok.hidden = !match; btn.disabled = !(match && read && $$('[data-pledge]', f).every(c => c.checked)); };
    const onScroll = () => { if (!read && doc.scrollTop + doc.clientHeight >= doc.scrollHeight - 20) { read = true; status.classList.add('is-read'); status.textContent = '✓ You have read the full agreement'; gate.hidden = true; f.hidden = false; check(); } };
    doc.addEventListener('scroll', onScroll); setTimeout(onScroll, 300);
    sig.addEventListener('input', check); $$('[data-pledge]', f).forEach(c => c.addEventListener('change', check));
  }
  // upload slots submit as soon as a file is chosen
  $$('[data-upload-slot]').forEach(slot => { const inp = $('[data-upload-input]', slot); inp && inp.addEventListener('change', () => { if (inp.files.length) slot.submit(); }); });
  // choice cards selection class
  $$('.choice-card input').forEach(i => i.addEventListener('change', () => $$('.choice-card').forEach(c => c.classList.toggle('is-selected', c.querySelector('input').checked))));
  // stage flash removal
  const fl = $('[data-ob-flash]'); if (fl) setTimeout(() => fl.remove(), 1700);
})();

// hub helpers: toggles, feedback context, file counts
(() => {
  document.querySelectorAll('[data-toggle-target]').forEach(b => b.addEventListener('click', () => { const t = document.getElementById(b.getAttribute('data-toggle-target')); if (t) { t.hidden = !t.hidden; if (!t.hidden) { const f = t.querySelector('input,textarea,select'); f && f.focus(); } } }));
  const fb = document.querySelector('[data-feedback-form]');
  if (fb) {
    const ua = navigator.userAgent, browser = /Edg\//.test(ua) ? 'Edge' : /Chrome\//.test(ua) ? 'Chrome' : /Firefox\//.test(ua) ? 'Firefox' : /Safari\//.test(ua) ? 'Safari' : 'Other';
    const w = window.innerWidth, device = w < 768 ? 'Mobile' : w < 1024 ? 'Tablet' : 'Desktop';
    fb.querySelector('[data-fb-url]').value = location.href; fb.querySelector('[data-fb-path]').value = location.pathname; fb.querySelector('[data-fb-browser]').value = browser; fb.querySelector('[data-fb-device]').value = device; fb.querySelector('[data-fb-screen]').value = w + 'x' + window.innerHeight;
    const ctx = fb.querySelector('[data-feedback-context]'); if (ctx) ctx.textContent = 'Page: ' + location.pathname + ' · Browser: ' + browser + ' · Device: ' + device + ' · Screen: ' + w + '×' + window.innerHeight;
  }
  document.querySelectorAll('[data-count-files]').forEach(i => i.addEventListener('change', () => { const c = document.querySelector('[data-file-count]'); if (c) c.textContent = i.files.length + '/6 photos selected'; }));
})();
