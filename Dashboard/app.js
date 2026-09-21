/* ==========================================================================
   JT DIGITAL — COMPLETION REPORT BUILDER
   Plain HTML/CSS/JS, no build step. State lives in localStorage.
   ========================================================================== */

/* ---------------------------------------------------------------------- */
/* 1. UTILITIES                                                            */
/* ---------------------------------------------------------------------- */
const $  = (sel, root = document) => root.querySelector(sel);
const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));
const uid = () => 'id' + Math.random().toString(36).slice(2, 10) + Date.now().toString(36);
const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const nl2li = (s) => String(s ?? '').split('\n').map(l => l.trim()).filter(Boolean).map(l => `<li>${esc(l)}</li>`).join('');
const fmtNum = (n) => Number(n || 0).toLocaleString();
const debounce = (fn, ms) => { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); }; };
function toast(msg) {
  const t = $('#toast'); t.textContent = msg; t.classList.add('show');
  clearTimeout(toast._h); toast._h = setTimeout(() => t.classList.remove('show'), 2400);
}
function getPath(obj, path) {
  return path.split('.').reduce((o, k) => (o == null ? o : o[k]), obj);
}
function setPath(obj, path, value) {
  const keys = path.split('.');
  let o = obj;
  for (let i = 0; i < keys.length - 1; i++) {
    const k = keys[i];
    if (o[k] == null) o[k] = /^\d+$/.test(keys[i + 1]) ? [] : {};
    o = o[k];
  }
  o[keys[keys.length - 1]] = value;
}
/** Resize + compress an image file client-side so localStorage doesn't blow up. */
function fileToDataURL(file, maxW = 1100, quality = 0.82) {
  return new Promise((resolve, reject) => {
    if (!file.type || !file.type.startsWith('image/')) return reject(new Error('Not an image'));
    const img = new Image();
    const reader = new FileReader();
    reader.onload = () => {
      img.onload = () => {
        const scale = Math.min(1, maxW / img.width);
        const w = Math.round(img.width * scale), h = Math.round(img.height * scale);
        const canvas = document.createElement('canvas');
        canvas.width = w; canvas.height = h;
        canvas.getContext('2d').drawImage(img, 0, 0, w, h);
        resolve(canvas.toDataURL('image/jpeg', quality));
      };
      img.onerror = reject;
      img.src = reader.result;
    };
    reader.onerror = reject;
    reader.readAsDataURL(file);
  });
}

/* ---------------------------------------------------------------------- */
/* 2. DATA MODEL                                                           */
/* ---------------------------------------------------------------------- */
const DEFAULT_BREAKDOWN = () => ([
  { status: 'Incomplete', title: 'Photographer/Videographer', bullets: ['3x on-site coverage for content shoot (maximum of 6 hours per shoot)', 'Photographer/Videographer with equipment already'], note: '' },
  { status: 'Incomplete', title: 'Creative Requests/Graphics Designer', bullets: [], note: '' },
  { status: 'Incomplete', title: 'Facebook Content Management', bullets: ['30–35 feed posts (a mix of static images, carousel posts, and reels)', '2 countdown campaigns per month for major events, each consisting of 3 supporting posts and reels', '1 event invitation post per featured event'], note: '' },
  { status: 'Incomplete', title: 'TikTok Content Management', bullets: ['3 to 5 videos per week, featuring events, promos, tenant highlights, and estate visuals'], note: '' },
  { status: 'Incomplete', title: 'Community Management Support', bullets: ['Responding to messages, comments, and inquiries'], note: '' },
  { status: 'Incomplete', title: 'Analytics & Performance Reporting', bullets: ['Facebook, Instagram, TikTok'], note: '' },
]);

function blankReport() {
  const now = new Date();
  return {
    id: uid(),
    createdAt: now.toISOString(),
    updatedAt: now.toISOString(),
    cover: { month: '', year: String(now.getFullYear()), clientName: '', preparedByName: '', preparedByCompany: '', coverImage: null },
    summary: { reportDate: '', contractorName: '', contractorAddress: '', projectNames: '', projectDescription: '', startDate: '', completionDate: '', closingNote: 'Thank you and we assure you our best services again.', preparerName: '', approvedClientName: '', approvedPosition: '', approvedCompany: '' },
    calendarImages: [],
    contentImages: [],
    postsImages: [],
    postsTable: [],
    creativesImages: [],
    shootLog: [{ date: '', description: '', link: '' }],
    breakdown: DEFAULT_BREAKDOWN(),
    contact: { viberName: '', viberNumber: '', email: '', website: '', location: '', officePhoto: null },
  };
}

const LS_KEYS = { reports: 'jt_reports_v1', defaults: 'jt_defaults_v1', current: 'jt_current_v1' };
function loadJSON(key, fallback) { try { const v = localStorage.getItem(key); return v ? JSON.parse(v) : fallback; } catch { return fallback; } }
function saveJSON(key, val) {
  try { localStorage.setItem(key, JSON.stringify(val)); return true; }
  catch (e) { console.warn('Storage write failed', e); toast('⚠ Storage is full — image(s) may not be saved locally'); return false; }
}

let state = {
  current: loadJSON(LS_KEYS.current, null) || blankReport(),
  defaults: loadJSON(LS_KEYS.defaults, { contractorName: '', contractorAddress: '', preparedByCompany: '', viberName: '', viberNumber: '', email: '', website: '', location: '' }),
  reports: loadJSON(LS_KEYS.reports, []),
  activeSection: 'cover',
  zoom: 0.7,
  activePasteSlot: null,
};

function persistCurrent() { state.current.updatedAt = new Date().toISOString(); saveJSON(LS_KEYS.current, state.current); }
const persistCurrentDebounced = debounce(persistCurrent, 400);

/* ---------------------------------------------------------------------- */
/* 3. SECTION DEFINITIONS                                                  */
/* ---------------------------------------------------------------------- */
const SECTIONS = [
  { id: 'cover',     label: '1. Cover Page' },
  { id: 'summary',   label: '2. Project Completion Report' },
  { id: 'calendar',  label: '3. Content Calendar' },
  { id: 'content',   label: '4. Content for the Month' },
  { id: 'posted',    label: '5. Content Posted: FB & IG' },
  { id: 'creatives', label: '6. Creatives/Layouts' },
  { id: 'shoots',    label: '7. Content Shoot Log' },
  { id: 'breakdown', label: '8. Breakdown' },
  { id: 'contact',   label: '9. Contact Us' },
];

function isSectionComplete(id, d) {
  switch (id) {
    case 'cover': return !!(d.cover.month && d.cover.year && d.cover.clientName && d.cover.preparedByName);
    case 'summary': return !!(d.summary.reportDate && d.summary.contractorName && d.summary.projectNames && d.summary.projectDescription && d.summary.startDate && d.summary.completionDate && d.summary.preparerName);
    case 'calendar': return d.calendarImages.length > 0;
    case 'content': return d.contentImages.length > 0;
    case 'posted': return d.postsImages.length > 0 && d.postsTable.length > 0;
    case 'creatives': return d.creativesImages.length > 0;
    case 'shoots': return d.shootLog.some(r => r.date && r.description);
    case 'breakdown': return d.breakdown.some(r => r.title);
    case 'contact': return !!(d.contact.email && d.contact.website && d.contact.location && (d.contact.viberNumber));
    default: return false;
  }
}

/* ---------------------------------------------------------------------- */
/* 4. SIDE NAV                                                             */
/* ---------------------------------------------------------------------- */
function renderSidenav() {
  const list = $('#sectionList');
  list.innerHTML = SECTIONS.map((s, i) => {
    const done = isSectionComplete(s.id, state.current);
    const active = state.activeSection === s.id;
    return `<li class="section-item ${active ? 'active' : ''} ${done ? 'done' : ''}" data-section="${s.id}">
      <span class="section-dot">${done ? '✓' : i + 1}</span>
      <span class="section-name">${s.label}</span>
    </li>`;
  }).join('');
  $$('.section-item', list).forEach(el => el.addEventListener('click', () => {
    state.activeSection = el.dataset.section;
    renderAll();
  }));

  const doneCount = SECTIONS.filter(s => isSectionComplete(s.id, state.current)).length;
  $('#reportLabel').textContent = `${state.current.cover.month || 'Untitled'} ${state.current.cover.year || ''} · ${doneCount}/${SECTIONS.length} sections ready`.trim();

  renderReportList();
}

function renderReportList() {
  const ul = $('#reportList');
  if (!state.reports.length) { ul.innerHTML = `<li class="muted">No saved reports yet</li>`; return; }
  ul.innerHTML = state.reports.slice().sort((a, b) => b.updatedAt.localeCompare(a.updatedAt)).slice(0, 6).map(r => `
    <li data-id="${r.id}" title="Open ${esc(r.cover.month)} ${esc(r.cover.year)}">
      <div class="report-row-meta"><span>${esc(r.cover.month || 'Untitled')} ${esc(r.cover.year || '')}</span><small>${esc(r.cover.clientName || 'No client set')}</small></div>
    </li>`).join('');
  $$('li', ul).forEach(li => li.addEventListener('click', () => openReport(li.dataset.id)));
}

function renderReportListModal() {
  const ul = $('#reportListModal');
  if (!state.reports.length) { ul.innerHTML = `<li class="muted">No saved reports yet. Use "Save draft" to create one.</li>`; return; }
  ul.innerHTML = state.reports.slice().sort((a, b) => b.updatedAt.localeCompare(a.updatedAt)).map(r => `
    <li data-id="${r.id}">
      <div class="report-row-meta"><span><strong>${esc(r.cover.month || 'Untitled')} ${esc(r.cover.year || '')}</strong></span><small>${esc(r.cover.clientName || 'No client')} · saved ${new Date(r.updatedAt).toLocaleString()}</small></div>
      <span>
        <button class="btn btn-ghost btn-xs" data-open="${r.id}">Open</button>
        <button class="btn btn-danger btn-xs" data-del="${r.id}">Delete</button>
      </span>
    </li>`).join('');
  ul.querySelectorAll('[data-open]').forEach(b => b.addEventListener('click', (e) => { e.stopPropagation(); openReport(b.dataset.open); closeModal('#loadModal'); }));
  ul.querySelectorAll('[data-del]').forEach(b => b.addEventListener('click', (e) => {
    e.stopPropagation();
    if (!confirm('Delete this saved report? This cannot be undone.')) return;
    state.reports = state.reports.filter(r => r.id !== b.dataset.del);
    saveJSON(LS_KEYS.reports, state.reports);
    renderReportListModal(); renderReportList();
    toast('Report deleted');
  }));
}

/* ---------------------------------------------------------------------- */
/* 5. GENERIC BINDING (editor <-> state)                                   */
/* ---------------------------------------------------------------------- */
function bindValue(path, value) {
  setPath(state.current, path, value);
  persistCurrentDebounced();
  renderPreview();
  renderSidenavSoft();
}
function renderSidenavSoft() {
  // Cheap refresh of dots/label without rebuilding the whole nav (keeps scroll position)
  SECTIONS.forEach(s => {
    const el = $(`.section-item[data-section="${s.id}"]`);
    if (!el) return;
    const done = isSectionComplete(s.id, state.current);
    el.classList.toggle('done', done);
    $('.section-dot', el).textContent = done ? '✓' : (SECTIONS.findIndex(x => x.id === s.id) + 1);
  });
  const doneCount = SECTIONS.filter(s => isSectionComplete(s.id, state.current)).length;
  $('#reportLabel').textContent = `${state.current.cover.month || 'Untitled'} ${state.current.cover.year || ''} · ${doneCount}/${SECTIONS.length} sections ready`.trim();
}
function wireInputs(root) {
  $$('[data-bind]', root).forEach(el => {
    const path = el.dataset.bind;
    const val = getPath(state.current, path);
    if (el.type === 'checkbox') el.checked = !!val; else el.value = val ?? '';
    const evt = (el.tagName === 'SELECT' || el.type === 'date') ? 'change' : 'input';
    el.addEventListener(evt, () => bindValue(path, el.type === 'checkbox' ? el.checked : el.value));
  });
}

/* ---------------------------------------------------------------------- */
/* 6. IMAGE SLOTS (paste / drag-drop / upload / reorder / caption / delete)*/
/* ---------------------------------------------------------------------- */
function imageSlotHTML(key, opts = {}) {
  const multi = opts.multi !== false;
  const images = multi ? (getPath(state.current, key) || []) : null;
  const single = multi ? null : getPath(state.current, key);
  const label = opts.label || 'Paste, drop, or browse for a screenshot';
  return `
  <div class="dropzone" tabindex="0" data-slot="${key}" data-multi="${multi}">
    <strong>${label}</strong>
    <span>Click here, then press ⌘V / Ctrl+V — or drag & drop, or browse a file</span>
    <input type="file" accept="image/*" ${multi ? 'multiple' : ''} data-slot-input="${key}">
  </div>
  ${multi ? `<div class="image-grid" data-slot-grid="${key}">
      ${images.map((img, i) => imageTileHTML(key, img, i, images.length)).join('')}
    </div>`
    : (single ? `<div class="image-grid" data-slot-grid="${key}">${imageTileHTML(key, single, 0, 1, true)}</div>` : '')}
  `;
}
function imageTileHTML(key, img, index, count, isSingle = false) {
  return `<div class="image-tile" data-img-id="${img.id}">
    <img src="${img.dataUrl}" alt="">
    <div class="tile-actions">
      ${!isSingle && index > 0 ? `<button class="tile-btn move" data-move="up" title="Move earlier">↑</button>` : ''}
      ${!isSingle && index < count - 1 ? `<button class="tile-btn move" data-move="down" title="Move later">↓</button>` : ''}
      <button class="tile-btn" data-remove title="Remove">✕</button>
    </div>
    <input class="tile-caption" placeholder="Caption (optional)" value="${esc(img.caption || '')}" data-caption>
  </div>`;
}
function wireImageSlots(root) {
  $$('.dropzone', root).forEach(zone => {
    const key = zone.dataset.slot;
    const multi = zone.dataset.multi === 'true';
    const fileInput = $(`[data-slot-input="${key}"]`, zone);

    zone.addEventListener('click', (e) => { if (e.target.tagName !== 'INPUT') fileInput.click(); state.activePasteSlot = key; markActiveZone(zone); });
    zone.addEventListener('focus', () => { state.activePasteSlot = key; markActiveZone(zone); });
    zone.addEventListener('dragover', (e) => { e.preventDefault(); zone.classList.add('dragover'); });
    zone.addEventListener('dragleave', () => zone.classList.remove('dragover'));
    zone.addEventListener('drop', async (e) => {
      e.preventDefault(); zone.classList.remove('dragover');
      await addImagesFromFiles(key, multi, e.dataTransfer.files);
    });
    fileInput.addEventListener('change', async () => { await addImagesFromFiles(key, multi, fileInput.files); fileInput.value = ''; });
  });

  const grid = root.matches('[data-slot-grid]') ? null : root; // event delegation container
  root.addEventListener('click', (e) => {
    const tile = e.target.closest('.image-tile');
    if (!tile) return;
    const gridEl = tile.closest('[data-slot-grid]');
    if (!gridEl) return;
    const key = gridEl.dataset.slotGrid;
    const id = tile.dataset.imgId;
    if (e.target.matches('[data-remove]')) { removeImage(key, id); }
    if (e.target.matches('[data-move="up"]')) { moveImage(key, id, -1); }
    if (e.target.matches('[data-move="down"]')) { moveImage(key, id, 1); }
  });
  root.addEventListener('input', (e) => {
    if (!e.target.matches('[data-caption]')) return;
    const tile = e.target.closest('.image-tile');
    const gridEl = tile.closest('[data-slot-grid]');
    const key = gridEl.dataset.slotGrid;
    setImageField(key, tile.dataset.imgId, 'caption', e.target.value);
  });
}
function markActiveZone(zone) {
  $$('.dropzone').forEach(z => z.style.borderColor = '');
  zone.style.borderColor = 'var(--indigo)';
}
async function addImagesFromFiles(key, multi, fileList) {
  const files = Array.from(fileList || []).filter(f => f.type.startsWith('image/'));
  if (!files.length) return;
  for (const f of files) {
    try {
      const dataUrl = await fileToDataURL(f);
      addImageToSlot(key, multi, dataUrl);
    } catch (err) { console.warn(err); }
  }
  persistCurrentDebounced();
  renderEditor(); renderPreview(); renderSidenavSoft();
}
function addImageToSlot(key, multi, dataUrl) {
  if (multi) {
    const arr = getPath(state.current, key) || [];
    arr.push({ id: uid(), dataUrl, caption: '' });
    setPath(state.current, key, arr);
  } else {
    setPath(state.current, key, { id: uid(), dataUrl, caption: '' });
  }
}
function removeImage(key, id) {
  const val = getPath(state.current, key);
  if (Array.isArray(val)) setPath(state.current, key, val.filter(i => i.id !== id));
  else setPath(state.current, key, null);
  persistCurrentDebounced(); renderEditor(); renderPreview(); renderSidenavSoft();
}
function setImageField(key, id, field, value) {
  const val = getPath(state.current, key);
  if (Array.isArray(val)) { const item = val.find(i => i.id === id); if (item) item[field] = value; }
  else if (val && val.id === id) val[field] = value;
  persistCurrentDebounced(); renderPreview();
}
function moveImage(key, id, dir) {
  const arr = getPath(state.current, key);
  const i = arr.findIndex(x => x.id === id);
  const j = i + dir;
  if (j < 0 || j >= arr.length) return;
  [arr[i], arr[j]] = [arr[j], arr[i]];
  persistCurrentDebounced(); renderEditor(); renderPreview();
}
// Global paste handler
window.addEventListener('paste', async (e) => {
  const key = state.activePasteSlot;
  if (!key) return;
  const items = Array.from(e.clipboardData?.items || []).filter(i => i.kind === 'file' && i.type.startsWith('image/'));
  if (!items.length) return;
  e.preventDefault();
  const zone = $(`.dropzone[data-slot="${key}"]`);
  const multi = zone ? zone.dataset.multi === 'true' : true;
  for (const it of items) {
    const file = it.getAsFile();
    try { const dataUrl = await fileToDataURL(file); addImageToSlot(key, multi, dataUrl); } catch (err) { console.warn(err); }
  }
  persistCurrentDebounced();
  renderEditor(); renderPreview(); renderSidenavSoft();
  toast('Image pasted ✓');
});

/* ---------------------------------------------------------------------- */
/* 7. EDITABLE TABLES (posts / shoot log / breakdown)                      */
/* ---------------------------------------------------------------------- */
const POST_COLS = [
  ['title', 'Title'], ['date', 'Date Published'], ['reach', 'Reach'], ['views', 'Views'],
  ['visits', 'Visits'], ['interactions', 'Interactions'], ['likes', 'Likes & Reactions'], ['comments', 'Comments'],
];
function postStats(rows) {
  if (!rows.length) return { total: 0, avgReach: 0, top: null };
  const total = rows.length;
  const avgReach = Math.round(rows.reduce((s, r) => s + (Number(r.reach) || 0), 0) / total);
  const top = rows.reduce((best, r) => (Number(r.reach) || 0) > (Number(best?.reach) || -1) ? r : best, null);
  return { total, avgReach, top };
}
function postsTableEditorHTML() {
  const rows = state.current.postsTable;
  const st = postStats(rows);
  return `
  <div class="stat-strip">
    <div class="stat-chip"><span class="n">${st.total}</span><span class="l">Total posts</span></div>
    <div class="stat-chip"><span class="n">${fmtNum(st.avgReach)}</span><span class="l">Average reach</span></div>
    <div class="stat-chip"><span class="n">${st.top ? esc(st.top.title || 'Untitled') : '—'}</span><span class="l">Top performing post</span></div>
  </div>
  <div class="card-row">
    <span class="small muted">${rows.length} row(s)</span>
    <span>
      <button class="btn btn-ghost btn-sm" id="btnImportCSV">Import CSV</button>
      <button class="btn btn-primary btn-sm" id="btnAddPostRow">+ Add row</button>
    </span>
  </div>
  <input type="file" id="csvFile" accept=".csv" style="display:none">
  <div class="tbl-wrap"><table class="edit-table"><thead><tr>${POST_COLS.map(c => `<th>${c[1]}</th>`).join('')}<th></th></tr></thead>
  <tbody>
  ${rows.map((r, i) => `<tr data-row="${i}">
    ${POST_COLS.map(([k]) => `<td><input type="text" data-post-field="${k}" value="${esc(r[k])}"></td>`).join('')}
    <td><button class="row-del" data-del-post title="Remove row">✕</button></td>
  </tr>`).join('') || `<tr><td colspan="9" class="muted small" style="padding:14px;">No posts yet — add a row or import a CSV.</td></tr>`}
  </tbody></table></div>`;
}
function shootLogEditorHTML() {
  const rows = state.current.shootLog;
  return `
  <div class="card-row"><span class="small muted">${rows.length} row(s)</span><button class="btn btn-primary btn-sm" id="btnAddShootRow">+ Add row</button></div>
  <div class="tbl-wrap"><table class="edit-table"><thead><tr><th style="width:110px">Date</th><th>Content Shoot Description</th><th style="width:200px">GDrive Link</th><th></th></tr></thead>
  <tbody>
  ${rows.map((r, i) => `<tr data-row="${i}">
    <td><input type="date" data-shoot-field="date" value="${esc(r.date)}"></td>
    <td><textarea data-shoot-field="description">${esc(r.description)}</textarea></td>
    <td><input type="text" placeholder="https://drive.google.com/…" data-shoot-field="link" value="${esc(r.link)}"></td>
    <td><button class="row-del" data-del-shoot title="Remove row">✕</button></td>
  </tr>`).join('')}
  </tbody></table></div>`;
}
function breakdownEditorHTML() {
  const rows = state.current.breakdown;
  return `
  <div class="card-row"><span class="small muted">${rows.length} row(s) · pre-loaded with common recurring scope items — edit or remove freely</span><button class="btn btn-primary btn-sm" id="btnAddBreakdownRow">+ Add row</button></div>
  <div class="tbl-wrap"><table class="edit-table"><thead><tr><th style="width:110px">Status</th><th style="width:230px">Scope description</th><th>Bullet points (one per line)</th><th>Note</th><th></th></tr></thead>
  <tbody>
  ${rows.map((r, i) => `<tr data-row="${i}">
    <td>
      <select data-bd-field="status">
        <option ${r.status==='Complete'?'selected':''}>Complete</option>
        <option ${r.status==='Incomplete'?'selected':''}>Incomplete</option>
        <option ${r.status==='In Progress'?'selected':''}>In Progress</option>
      </select>
    </td>
    <td><input type="text" data-bd-field="title" value="${esc(r.title)}" placeholder="e.g. Facebook Content Management"></td>
    <td><textarea data-bd-field="bullets" placeholder="One bullet per line">${esc((r.bullets || []).join('\n'))}</textarea></td>
    <td><textarea data-bd-field="note" placeholder="Free text — or paste a link">${esc(r.note)}</textarea></td>
    <td><button class="row-del" data-del-bd title="Remove row">✕</button></td>
  </tr>`).join('')}
  </tbody></table></div>`;
}
function wireTables(root) {
  // Posts table
  $('#btnAddPostRow', root)?.addEventListener('click', () => {
    state.current.postsTable.push({ title: '', date: '', reach: '', views: '', visits: '', interactions: '', likes: '', comments: '' });
    persistCurrentDebounced(); renderEditor(); renderPreview(); renderSidenavSoft();
  });
  $$('[data-del-post]', root).forEach(b => b.addEventListener('click', (e) => {
    const i = Number(e.target.closest('tr').dataset.row);
    state.current.postsTable.splice(i, 1);
    persistCurrentDebounced(); renderEditor(); renderPreview(); renderSidenavSoft();
  }));
  $$('[data-post-field]', root).forEach(inp => inp.addEventListener('input', () => {
    const i = Number(inp.closest('tr').dataset.row);
    state.current.postsTable[i][inp.dataset.postField] = inp.value;
    persistCurrentDebounced(); renderPreview(); renderStatsInline();
  }));
  $('#btnImportCSV', root)?.addEventListener('click', () => $('#csvFile', root).click());
  $('#csvFile', root)?.addEventListener('change', async (e) => {
    const file = e.target.files[0]; if (!file) return;
    const text = await file.text();
    const rows = parseCSV(text);
    if (!rows.length) { toast('No rows found in that file'); return; }
    let start = 0;
    if (/title/i.test(rows[0][0] || '')) start = 1; // skip header row
    for (let i = start; i < rows.length; i++) {
      const [title, date, reach, views, visits, interactions, likes, comments] = rows[i];
      if (!title) continue;
      state.current.postsTable.push({ title, date: date || '', reach: reach || '', views: views || '', visits: visits || '', interactions: interactions || '', likes: likes || '', comments: comments || '' });
    }
    persistCurrentDebounced(); renderEditor(); renderPreview(); renderSidenavSoft();
    toast('CSV imported ✓');
    e.target.value = '';
  });

  // Shoot log
  $('#btnAddShootRow', root)?.addEventListener('click', () => {
    state.current.shootLog.push({ date: '', description: '', link: '' });
    persistCurrentDebounced(); renderEditor(); renderPreview(); renderSidenavSoft();
  });
  $$('[data-del-shoot]', root).forEach(b => b.addEventListener('click', (e) => {
    const i = Number(e.target.closest('tr').dataset.row);
    state.current.shootLog.splice(i, 1);
    persistCurrentDebounced(); renderEditor(); renderPreview(); renderSidenavSoft();
  }));
  $$('[data-shoot-field]', root).forEach(inp => inp.addEventListener('input', () => {
    const i = Number(inp.closest('tr').dataset.row);
    state.current.shootLog[i][inp.dataset.shootField] = inp.value;
    persistCurrentDebounced(); renderPreview();
  }));

  // Breakdown
  $('#btnAddBreakdownRow', root)?.addEventListener('click', () => {
    state.current.breakdown.push({ status: 'Incomplete', title: '', bullets: [], note: '' });
    persistCurrentDebounced(); renderEditor(); renderPreview(); renderSidenavSoft();
  });
  $$('[data-del-bd]', root).forEach(b => b.addEventListener('click', (e) => {
    const i = Number(e.target.closest('tr').dataset.row);
    state.current.breakdown.splice(i, 1);
    persistCurrentDebounced(); renderEditor(); renderPreview(); renderSidenavSoft();
  }));
  $$('[data-bd-field]', root).forEach(inp => inp.addEventListener('input', () => {
    const i = Number(inp.closest('tr').dataset.row);
    const field = inp.dataset.bdField;
    state.current.breakdown[i][field] = field === 'bullets' ? inp.value.split('\n').map(s=>s.trim()).filter(Boolean) : inp.value;
    persistCurrentDebounced(); renderPreview();
  }));
}
function renderStatsInline() {
  const strip = $('.stat-strip');
  if (!strip) return;
  const st = postStats(state.current.postsTable);
  strip.innerHTML = `
    <div class="stat-chip"><span class="n">${st.total}</span><span class="l">Total posts</span></div>
    <div class="stat-chip"><span class="n">${fmtNum(st.avgReach)}</span><span class="l">Average reach</span></div>
    <div class="stat-chip"><span class="n">${st.top ? esc(st.top.title || 'Untitled') : '—'}</span><span class="l">Top performing post</span></div>`;
}
function parseCSV(text) {
  return text.split(/\r?\n/).filter(l => l.trim().length).map(line => {
    const out = []; let cur = ''; let inQ = false;
    for (let i = 0; i < line.length; i++) {
      const c = line[i];
      if (c === '"') { inQ = !inQ; continue; }
      if (c === ',' && !inQ) { out.push(cur.trim()); cur = ''; continue; }
      cur += c;
    }
    out.push(cur.trim());
    return out;
  });
}

/* ---------------------------------------------------------------------- */
/* 8. EDITOR: per-section forms                                            */
/* ---------------------------------------------------------------------- */
function renderEditor() {
  const root = $('#editor');
  const id = state.activeSection;
  const meta = SECTIONS.find(s => s.id === id);
  let body = '';

  if (id === 'cover') {
    body = `
    <div class="card">
      <h3>Cover details</h3>
      <div class="form-grid">
        <label>Month <input type="text" data-bind="cover.month" placeholder="e.g. June"></label>
        <label>Year <input type="text" data-bind="cover.year" placeholder="2026"></label>
        <label>Prepared for (client) <input type="text" data-bind="cover.clientName" placeholder="The Outlets @LIMA Estate"></label>
        <label>Prepared by — name <input type="text" data-bind="cover.preparedByName" placeholder="Jeah Tradio"></label>
        <label class="span2">Prepared by — company <input type="text" data-bind="cover.preparedByCompany" placeholder="JT Digital Marketing Services"></label>
      </div>
    </div>
    <div class="card">
      <h3>Cover photo <span class="small muted">(optional)</span></h3>
      ${imageSlotHTML('cover.coverImage', { multi: false, label: 'Paste or drop a hero photo' })}
    </div>`;
  }

  else if (id === 'summary') {
    body = `
    <div class="card">
      <h3>Contractor information</h3>
      <div class="form-grid">
        <label>Report date <input type="date" data-bind="summary.reportDate"></label>
        <label>Contractor name <input type="text" data-bind="summary.contractorName" placeholder="JT Digital Marketing Services"></label>
        <label class="span2">Contractor address <input type="text" data-bind="summary.contractorAddress" placeholder="Joel Casipong Apt 3, Brgy. Looc, Lapu-Lapu City, Cebu, Philippines 6015"></label>
      </div>
    </div>
    <div class="card">
      <h3>Project</h3>
      <div class="form-grid">
        <label class="span2">Project name(s) <input type="text" data-bind="summary.projectNames" placeholder="The Outlets at LIMA Estate Social Media Marketing Services"></label>
        <label class="span2">Project description <textarea data-bind="summary.projectDescription" placeholder="Monthly social media management and layouts…"></textarea></label>
        <label>Start date <input type="date" data-bind="summary.startDate"></label>
        <label>Completion date <input type="date" data-bind="summary.completionDate"></label>
      </div>
    </div>
    <div class="card">
      <h3>Closing &amp; sign-off</h3>
      <div class="form-grid">
        <label class="span2">Closing note <textarea data-bind="summary.closingNote"></textarea></label>
        <label>Preparer name <input type="text" data-bind="summary.preparerName" placeholder="Jeah Tradio"></label>
        <span></span>
        <label>Approved by — client name <input type="text" data-bind="summary.approvedClientName"></label>
        <label>Position / designation <input type="text" data-bind="summary.approvedPosition"></label>
        <label>Company name <input type="text" data-bind="summary.approvedCompany"></label>
      </div>
    </div>`;
  }

  else if (id === 'calendar') {
    body = `<div class="card"><h3>Content Calendar screenshot(s)</h3><p class="muted small" style="margin-bottom:10px;">Screenshot the monthly content calendar and paste it in. Multiple images are supported and can be reordered.</p>
      ${imageSlotHTML('calendarImages', { label: 'Paste the content calendar screenshot' })}</div>`;
  }
  else if (id === 'content') {
    body = `<div class="card"><h3>Content created/edited this month</h3><p class="muted small" style="margin-bottom:10px;">Paste screenshots of all content created or edited.</p>
      ${imageSlotHTML('contentImages', { label: 'Paste content screenshots' })}</div>`;
  }
  else if (id === 'creatives') {
    body = `<div class="card"><h3>Creatives / layouts</h3><p class="muted small" style="margin-bottom:10px;">Design screenshots — grid layout, captions optional.</p>
      ${imageSlotHTML('creativesImages', { label: 'Paste design screenshots' })}</div>`;
  }

  else if (id === 'posted') {
    body = `
    <div class="card"><h3>Meta Business Suite screenshot(s)</h3>
      ${imageSlotHTML('postsImages', { label: 'Paste the Meta Business Suite screenshot' })}
    </div>
    <div class="card"><h3>Post performance table</h3>${postsTableEditorHTML()}</div>`;
  }

  else if (id === 'shoots') {
    body = `<div class="card"><h3>Content shoot log</h3>${shootLogEditorHTML()}</div>`;
  }
  else if (id === 'breakdown') {
    body = `<div class="card"><h3>Scope of work breakdown</h3>${breakdownEditorHTML()}</div>`;
  }

  else if (id === 'contact') {
    body = `
    <div class="card">
      <h3>Contact details</h3>
      <div class="form-grid">
        <label>Viber name <input type="text" data-bind="contact.viberName"></label>
        <label>Viber number <input type="text" data-bind="contact.viberNumber"></label>
        <label>Email <input type="email" data-bind="contact.email"></label>
        <label>Website <input type="text" data-bind="contact.website"></label>
        <label class="span2">Location <input type="text" data-bind="contact.location"></label>
      </div>
      <button class="btn btn-ghost btn-sm" id="btnApplyDefaultsContact" style="margin-top:10px;">Fill from agency defaults</button>
    </div>
    <div class="card"><h3>Office / building photo <span class="small muted">(optional)</span></h3>
      ${imageSlotHTML('contact.officePhoto', { multi: false, label: 'Paste or drop an office photo' })}
    </div>`;
  }

  $('#editor').innerHTML = `
    <div class="editor-head"><h2>${meta.label}</h2><p>Fill in the fields below — the preview on the right updates live.</p></div>
    ${body}
  `;
  wireInputs(root);
  wireImageSlots(root);
  wireTables(root);
  $('#btnApplyDefaultsContact')?.addEventListener('click', () => { applyDefaultsToContact(); renderEditor(); renderPreview(); toast('Applied agency defaults'); });
}

/* ---------------------------------------------------------------------- */
/* 9. LIVE PREVIEW — must visually match the brand template                */
/* ---------------------------------------------------------------------- */
function bandHTML(title) {
  return `<div class="band">
    <div class="decor"><div class="lime-circle"></div><div class="white-wedge"></div></div>
    <div class="band-logo"><div class="logo-icon">JT</div><div class="logo-text">DIGITAL<br><small>MARKETING SERVICES</small></div></div>
  </div>
  <div class="page-title">${esc(title)}</div>`;
}
function footerHTML() { return `<div class="page-footer">JT Digital Marketing Services</div>`; }

function imageSlotPreview(images, emptyText) {
  if (!images || !images.length) return `<div class="slot-empty">${esc(emptyText)}</div>`;
  const n = images.length;
  return `<div class="slot-image-grid ${n === 1 ? 'n1' : ''}">
    ${images.map(img => `<div><img src="${img.dataUrl}">${img.caption ? `<div class="cap">${esc(img.caption)}</div>` : ''}</div>`).join('')}
  </div>`;
}

function pageCover(d) {
  const img = d.cover.coverImage;
  return `<div class="page page-cover">
    <div class="cover-top"><div class="logo-icon">JT</div><div class="logo-text">DIGITAL<br>MARKETING<br>SERVICES</div></div>
    <div class="cover-hr"></div>
    <div class="cover-headline">
      <div class="cover-swoosh"><div class="ring"></div></div>
      <h1>Completion<br>Report<br><span class="lime">${esc(d.cover.month || '[Month]')}<br>${esc(d.cover.year || '2026')}</span></h1>
    </div>
    <div class="cover-photo">${img ? `<img src="${img.dataUrl}">` : `<span class="ph">Cover photo will appear here</span>`}</div>
    <div class="cover-bottom">
      <div>Prepared For.<b>${esc(d.cover.clientName || '—')}</b></div>
      <div>Prepared By.<b>${esc(d.cover.preparedByName || '—')}</b>${esc(d.cover.preparedByCompany || '')}</div>
    </div>
  </div>`;
}

function pageSummary(d) {
  const s = d.summary;
  return `<div class="page page-summary">
    ${bandHTML('').replace('<div class="page-title"></div>', '')}
    <div class="page-body">
      <p class="summary-date">${s.reportDate ? new Date(s.reportDate).toLocaleDateString(undefined,{year:'numeric',month:'long',day:'numeric'}).toUpperCase() : 'REPORT DATE'}</p>
      <p class="summary-title">PROJECT COMPLETION REPORT</p>
      <h4>Contractor information</h4>
      <p><b>${esc(s.contractorName || '—')}</b><br>${esc(s.contractorAddress || '')}</p>
      <h4>Project name</h4>
      <p>${esc(s.projectNames || '—')}</p>
      <h4>Project description</h4>
      <p>${esc(s.projectDescription || '—')}</p>
      <p style="margin-top:10px;">Project start date: ${s.startDate ? new Date(s.startDate).toLocaleDateString() : '—'}<br>Completion date: ${s.completionDate ? new Date(s.completionDate).toLocaleDateString() : '—'}</p>
      <p style="margin-top:14px;">${esc(s.closingNote || '')}</p>
      <div class="summary-sign">
        <b>${esc(s.preparerName || '—')}</b>${esc(d.cover.preparedByCompany || 'JT Digital Marketing Services')}
        <b>Approved by:</b>
        <b>${esc(s.approvedClientName || 'CLIENT NAME')}</b>${esc(s.approvedPosition || 'Position/Designation')}<br>${esc(s.approvedCompany || 'Company Name')}
      </div>
    </div>
    ${footerHTML()}
  </div>`;
}

function pageImages(title, images, emptyText) {
  return `<div class="page">
    ${bandHTML(title)}
    <div class="page-body">${imageSlotPreview(images, emptyText)}</div>
    ${footerHTML()}
  </div>`;
}

function pagePosted(d) {
  const rows = d.postsTable;
  const st = postStats(rows);
  return `<div class="page">
    ${bandHTML('CONTENT POSTED: FB & IG (WITH TENANT REQUESTS)')}
    <div class="page-body">
      ${imageSlotPreview(d.postsImages, '[Insert the screenshot of all the content posted from Meta Business Suite]')}
      ${rows.length ? `
      <div class="posted-stats">
        <div class="chip"><b>${st.total}</b>Total posts</div>
        <div class="chip"><b>${fmtNum(st.avgReach)}</b>Avg. reach</div>
        <div class="chip"><b>${esc(st.top?.title || '—')}</b>Top performer</div>
      </div>
      <div class="posted-table-wrap"><table class="posted-table"><thead><tr>${POST_COLS.map(c=>`<th>${c[1]}</th>`).join('')}</tr></thead>
      <tbody>${rows.map(r => `<tr>${POST_COLS.map(([k]) => `<td>${esc(r[k])}</td>`).join('')}</tr>`).join('')}</tbody></table></div>` : ''}
    </div>
    ${footerHTML()}
  </div>`;
}

function pageShoots(d) {
  const rows = d.shootLog.filter(r => r.date || r.description || r.link);
  return `<div class="page">
    ${bandHTML('CONTENT SHOOT')}
    <div class="page-body">
      <p class="muted small">[Insert all the content shoot]</p>
      <table class="doc-table"><thead><tr><th style="width:90px">Date</th><th>Content Shoot Description</th><th style="width:160px">GDrive Link</th></tr></thead>
      <tbody>${(rows.length ? rows : [{date:'',description:'',link:''}]).map(r => `<tr>
        <td>${r.date ? new Date(r.date).toLocaleDateString(undefined,{month:'short',day:'2-digit',year:'numeric'}).toUpperCase() : ''}</td>
        <td>${esc(r.description)}</td>
        <td>${r.link ? `<a href="${esc(r.link)}" target="_blank">${esc(r.link)}</a>` : ''}</td>
      </tr>`).join('')}</tbody></table>
    </div>
    ${footerHTML()}
  </div>`;
}

function pageBreakdown(d) {
  const rows = d.breakdown.filter(r => r.title);
  const badgeClass = s => s === 'Complete' ? 'badge-complete' : s === 'In Progress' ? 'badge-progress' : 'badge-incomplete';
  return `<div class="page">
    ${bandHTML('BREAKDOWN')}
    <div class="page-body">
      <table class="doc-table"><thead><tr><th style="width:90px">Status</th><th style="width:220px">Scope Description</th><th>Note</th></tr></thead>
      <tbody>${rows.map(r => `<tr>
        <td><span class="badge ${badgeClass(r.status)}">${esc(r.status)}</span></td>
        <td><b>${esc(r.title)}</b>${r.bullets?.length ? `<ul>${nl2li(r.bullets.join('\n'))}</ul>` : ''}</td>
        <td>${esc(r.note)}</td>
      </tr>`).join('') || `<tr><td colspan="3" class="muted">No scope items yet</td></tr>`}</tbody></table>
    </div>
    ${footerHTML()}
  </div>`;
}

function pageContact(d) {
  const c = d.contact;
  return `<div class="page page-contact">
    <div class="contact-ring"><i></i></div>
    <div class="contact-head"><h1>Contact Us</h1><p>If you have any questions or need clarification about the report, please contact us.</p></div>
    <div class="contact-body">
      <div class="contact-list">
        <div><div class="lbl">📞 Viber</div>${esc(c.viberName || '—')}<br>${esc(c.viberNumber || '—')}</div>
        <div><div class="lbl">✉️ Email</div>${esc(c.email || '—')}</div>
        <div><div class="lbl">🌐 Website</div>${esc(c.website || '—')}</div>
        <div><div class="lbl">📍 Location</div>${esc(c.location || '—')}</div>
      </div>
      <div class="contact-photo"><div class="accent"></div>${c.officePhoto ? `<img src="${c.officePhoto.dataUrl}">` : ''}</div>
    </div>
    <div class="contact-bottom-line"></div>
  </div>`;
}

function pagesHTML(d) {
  return [
    pageCover(d),
    pageSummary(d),
    pageImages('CONTENT CALENDAR: ' + (d.cover.month ? esc(d.cover.month).toUpperCase() : '[MONTH]'), d.calendarImages, '[Insert the screenshot of the monthly reporting]'),
    pageImages('CONTENT FOR ' + (d.cover.month ? esc(d.cover.month).toUpperCase() : '[MONTH]'), d.contentImages, '[Insert the screenshot of all the content created/edited]'),
    pagePosted(d),
    pageImages('CREATIVES/LAYOUTS', d.creativesImages, '[Insert the screenshot/copy of all the designs]'),
    pageShoots(d),
    pageBreakdown(d),
    pageContact(d),
  ].join('\n');
}

function renderPreview() {
  const wrap = $('#previewPages');
  wrap.innerHTML = pagesHTML(state.current);
  applyZoom();
}
function applyZoom() {
  $('#zoomLevel').textContent = Math.round(state.zoom * 100) + '%';
  $$('#previewPages > .page').forEach(page => {
    if (!page.parentElement.classList.contains('preview-page-frame')) {
      const frame = document.createElement('div');
      frame.className = 'preview-page-frame';
      page.parentNode.insertBefore(frame, page);
      frame.appendChild(page);
    }
    const frame = page.parentElement;
    frame.style.width = (794 * state.zoom) + 'px';
    frame.style.height = (1123 * state.zoom) + 'px';
    frame.style.overflow = 'hidden';
    page.style.transform = `scale(${state.zoom})`;
    page.style.transformOrigin = 'top left';
  });
}

/* ---------------------------------------------------------------------- */
/* 10. AGENCY DEFAULTS                                                     */
/* ---------------------------------------------------------------------- */
function openDefaultsModal() {
  const f = state.defaults;
  $('#defContractorName').value = f.contractorName;
  $('#defContractorAddress').value = f.contractorAddress;
  $('#defPreparedByCompany').value = f.preparedByCompany;
  $('#defViberName').value = f.viberName;
  $('#defViberNumber').value = f.viberNumber;
  $('#defEmail').value = f.email;
  $('#defWebsite').value = f.website;
  $('#defLocation').value = f.location;
  openModal('#defaultsModal');
}
function applyDefaultsToNewReport(r) {
  const f = state.defaults;
  r.summary.contractorName = f.contractorName || r.summary.contractorName;
  r.summary.contractorAddress = f.contractorAddress || r.summary.contractorAddress;
  r.cover.preparedByCompany = f.preparedByCompany || r.cover.preparedByCompany;
  r.contact.viberName = f.viberName || r.contact.viberName;
  r.contact.viberNumber = f.viberNumber || r.contact.viberNumber;
  r.contact.email = f.email || r.contact.email;
  r.contact.website = f.website || r.contact.website;
  r.contact.location = f.location || r.contact.location;
  return r;
}
function applyDefaultsToContact() {
  const f = state.defaults;
  Object.assign(state.current.contact, {
    viberName: f.viberName || state.current.contact.viberName,
    viberNumber: f.viberNumber || state.current.contact.viberNumber,
    email: f.email || state.current.contact.email,
    website: f.website || state.current.contact.website,
    location: f.location || state.current.contact.location,
  });
  persistCurrentDebounced();
}

/* ---------------------------------------------------------------------- */
/* 11. REPORT LIFECYCLE: new / duplicate / save / open                     */
/* ---------------------------------------------------------------------- */
function newReport() {
  if (!confirm('Start a new blank report? Unsaved changes to the current report will be kept in this browser until overwritten, but consider saving a draft first.')) return;
  const r = applyDefaultsToNewReport(blankReport());
  state.current = r;
  state.activeSection = 'cover';
  persistCurrent();
  renderAll();
  toast('New report started');
}
function duplicateLast() {
  if (!state.reports.length) { toast('No saved reports to duplicate yet'); return; }
  const last = state.reports.slice().sort((a, b) => b.updatedAt.localeCompare(a.updatedAt))[0];
  const clone = JSON.parse(JSON.stringify(last));
  clone.id = uid();
  clone.createdAt = new Date().toISOString();
  clone.cover.month = ''; // month/year cleared for re-entry, everything else carried over
  clone.calendarImages = []; clone.contentImages = []; clone.postsImages = []; clone.postsTable = [];
  clone.creativesImages = []; clone.shootLog = [{ date: '', description: '', link: '' }];
  clone.summary.reportDate = ''; clone.summary.startDate = ''; clone.summary.completionDate = '';
  state.current = clone;
  state.activeSection = 'cover';
  persistCurrent();
  renderAll();
  toast('Duplicated last report — month-specific content cleared');
}
function saveDraft() {
  const idx = state.reports.findIndex(r => r.id === state.current.id);
  const snapshot = JSON.parse(JSON.stringify(state.current));
  snapshot.updatedAt = new Date().toISOString();
  if (idx >= 0) state.reports[idx] = snapshot; else state.reports.push(snapshot);
  const ok = saveJSON(LS_KEYS.reports, state.reports);
  renderSidenav();
  if (ok) toast('Draft saved ✓');
}
function openReport(id) {
  const r = state.reports.find(x => x.id === id);
  if (!r) return;
  state.current = JSON.parse(JSON.stringify(r));
  state.activeSection = 'cover';
  persistCurrent();
  renderAll();
  toast('Report opened');
}

/* ---------------------------------------------------------------------- */
/* 12. EXPORT: PDF & Word (.doc)                                           */
/* ---------------------------------------------------------------------- */
function buildExportContainer() {
  const el = document.createElement('div');
  el.style.position = 'fixed';
  el.style.left = '-99999px';
  el.style.top = '0';
  el.innerHTML = pagesHTML(state.current);
  document.body.appendChild(el);
  return el;
}
async function exportPDF() {
  if (!window.jspdf || !window.html2canvas) { toast('PDF library failed to load — check your connection'); return; }
  toast('Generating PDF…');
  const container = buildExportContainer();
  const pages = $$('.page', container);
  const { jsPDF } = window.jspdf;
  const pdf = new jsPDF({ unit: 'px', format: [794, 1123], orientation: 'portrait' });
  for (let i = 0; i < pages.length; i++) {
    const canvas = await html2canvas(pages[i], { scale: 2, useCORS: true, backgroundColor: '#ffffff' });
    const imgData = canvas.toDataURL('image/jpeg', 0.92);
    if (i > 0) pdf.addPage([794, 1123], 'portrait');
    pdf.addImage(imgData, 'JPEG', 0, 0, 794, 1123);
  }
  document.body.removeChild(container);
  const name = `Completion Report ${state.current.cover.month || ''} ${state.current.cover.year || ''}`.trim().replace(/\s+/g, ' ') + '.pdf';
  pdf.save(name);
  toast('PDF downloaded ✓');
}
function exportDOC() {
  toast('Generating Word document…');
  const container = buildExportContainer();
  const bodyHTML = container.innerHTML;
  document.body.removeChild(container);
  const css = $('link[rel=stylesheet]') ? Array.from(document.styleSheets).map(ss => { try { return Array.from(ss.cssRules).map(r => r.cssText).join('\n'); } catch { return ''; } }).join('\n') : '';
  const html = `<!DOCTYPE html><html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40">
  <head><meta charset="utf-8"><title>Completion Report</title>
  <style>
    @page { size: 794px 1123px; margin: 0; }
    body{ margin:0; }
    .page{ page-break-after: always; }
    ${css}
  </style></head>
  <body>${bodyHTML}</body></html>`;
  const blob = new Blob(['\ufeff', html], { type: 'application/msword' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = `Completion Report ${state.current.cover.month || ''} ${state.current.cover.year || ''}`.trim().replace(/\s+/g, ' ') + '.doc';
  document.body.appendChild(a); a.click(); document.body.removeChild(a);
  URL.revokeObjectURL(url);
  toast('Word document downloaded ✓');
}

/* ---------------------------------------------------------------------- */
/* 13. MODALS                                                               */
/* ---------------------------------------------------------------------- */
function openModal(sel) { $(sel).classList.add('open'); }
function closeModal(sel) { $(sel).classList.remove('open'); }

/* ---------------------------------------------------------------------- */
/* 14. WIRE TOP-LEVEL CONTROLS                                             */
/* ---------------------------------------------------------------------- */
function wireChrome() {
  $('#btnNewReport').addEventListener('click', newReport);
  $('#btnDuplicate').addEventListener('click', duplicateLast);
  $('#btnSaveDraft').addEventListener('click', saveDraft);
  $('#btnLoad').addEventListener('click', () => { renderReportListModal(); openModal('#loadModal'); });
  $('#closeLoad').addEventListener('click', () => closeModal('#loadModal'));
  $('#cancelLoad').addEventListener('click', () => closeModal('#loadModal'));

  $('#btnDefaults').addEventListener('click', openDefaultsModal);
  $('#closeDefaults').addEventListener('click', () => closeModal('#defaultsModal'));
  $('#cancelDefaults').addEventListener('click', () => closeModal('#defaultsModal'));
  $('#saveDefaults').addEventListener('click', () => {
    state.defaults = {
      contractorName: $('#defContractorName').value, contractorAddress: $('#defContractorAddress').value,
      preparedByCompany: $('#defPreparedByCompany').value, viberName: $('#defViberName').value,
      viberNumber: $('#defViberNumber').value, email: $('#defEmail').value, website: $('#defWebsite').value,
      location: $('#defLocation').value,
    };
    saveJSON(LS_KEYS.defaults, state.defaults);
    closeModal('#defaultsModal');
    toast('Agency defaults saved');
  });

  const genBtn = $('#btnGenerate'), genMenu = $('#generateMenu');
  genBtn.addEventListener('click', (e) => { e.stopPropagation(); genMenu.classList.toggle('open'); });
  document.addEventListener('click', () => genMenu.classList.remove('open'));
  genMenu.addEventListener('click', (e) => {
    const action = e.target.dataset.action;
    if (action === 'pdf') exportPDF();
    if (action === 'doc') exportDOC();
    genMenu.classList.remove('open');
  });

  $('#zoomIn').addEventListener('click', () => { state.zoom = Math.min(1.2, state.zoom + 0.1); applyZoom(); });
  $('#zoomOut').addEventListener('click', () => { state.zoom = Math.max(0.3, state.zoom - 0.1); applyZoom(); });

  [$('#defaultsModal'), $('#loadModal')].forEach(m => m.addEventListener('click', (e) => { if (e.target === m) m.classList.remove('open'); }));
}

/* ---------------------------------------------------------------------- */
/* 15. BOOT                                                                 */
/* ---------------------------------------------------------------------- */
function renderAll() { renderSidenav(); renderEditor(); renderPreview(); }
function init() {
  if (!state.reports.some(r => r.id === state.current.id)) {
    // first run: seed agency defaults onto the blank current report if empty
    if (!state.current.summary.contractorName && !state.current.contact.email) {
      applyDefaultsToNewReport(state.current);
    }
  }
  wireChrome();
  renderAll();
}
document.addEventListener('DOMContentLoaded', init);
