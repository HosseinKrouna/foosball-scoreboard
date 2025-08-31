// Base aus URL ableiten (funktioniert auch in Subfolder-Deployments)
(function () {
  const m = window.location.pathname.match(/^(.*)\/match\/\d+(?:\/.*)?$/);
  window.__BASE__ = m ? m[1] : '';
})();
const BASE  = window.__BASE__ || '';
const API   = (p) => BASE + p;
const CSRF  = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

// DOM refs
const app       = document.getElementById('matchApp');
const MID       = parseInt(app?.dataset.mid || '0', 10);
const statusStr = (app?.dataset.status || 'in_progress');
let   finishedApplied = (statusStr === 'finished');

const elA       = document.getElementById('scoreA');
const elB       = document.getElementById('scoreB');
const alertBox  = document.getElementById('appAlert');
const btnFinish = document.getElementById('btnFinish');
const btnUndo   = document.getElementById('btnUndo');
const statusEl  = document.getElementById('matchStatusText');

let poller = null;

// UI helpers
function showAlert(msg, type = 'warning') {
  if (!alertBox) return;
  alertBox.className = `alert alert-${type} py-2`;
  alertBox.textContent = msg;
  alertBox.classList.remove('d-none');
  clearTimeout(showAlert._t);
  showAlert._t = setTimeout(() => alertBox.classList.add('d-none'), 2200);
}
function bump(el) {
  if (!el || reduce) return;
  el.classList.add('bump');
  setTimeout(() => el.classList.remove('bump'), 240);
}
function pulseFinishCTA(on) {
  if (!btnFinish) return;
  if (on) {
    btnFinish.classList.remove('btn-primary');
    btnFinish.classList.add('btn-success');
    btnFinish.style.boxShadow = '0 0 0.8rem rgba(40,167,69,.55)';
  } else {
    btnFinish.classList.add('btn-primary');
    btnFinish.classList.remove('btn-success');
    btnFinish.style.boxShadow = '';
  }
}
function setFinishedUI() {
  if (finishedApplied) return;
  finishedApplied = true;
  document.querySelectorAll('.js-score').forEach(b => b.remove());
  if (btnUndo) btnUndo.remove();
  if (btnFinish) btnFinish.remove();
  if (!document.getElementById('finishedBadgeWrap')) {
    const badge = document.createElement('div');
    badge.id = 'finishedBadgeWrap';
    badge.className = 'text-center mt-3';
    badge.innerHTML = '<span class="badge bg-success">Finished</span>';
    document.querySelector('.card .card-body')?.appendChild(badge);
  }
  if (statusEl) {
    statusEl.innerHTML = statusEl.innerHTML.replace('In progress', 'Finished');
    if (!/Finished/.test(statusEl.textContent)) {
      statusEl.innerHTML = 'Finished · ' + statusEl.textContent.replace(/^.*·\s*/, '');
    }
  }
  pulseFinishCTA(false);
  if (poller) { clearInterval(poller); poller = null; }
}
function applyStatus(status) { if (status === 'finished') setFinishedUI(); }

// API helper (mit CSRF & Fehlerdetails)
async function api(path, opts = {}) {
  const method  = (opts.method || 'GET').toUpperCase();
  const headers = { ...(opts.headers || {}) };
  if (method !== 'GET' && CSRF) headers['X-CSRF-Token'] = CSRF;

  const res = await fetch(API(path), { ...opts, method, headers });
  let data = null, text = '';
  try { data = await res.json(); } catch { try { text = await res.text(); } catch {} }
  const notOk = !res.ok || (data && data.ok === false);
  if (notOk) {
    const base = (data && data.error) ? data.error : `HTTP ${res.status}`;
    const detail = (data && data.message) ? `: ${data.message}` : (text ? `: ${text}` : '');
    throw new Error(base + detail);
  }
  return data ?? {};
}

// Sync
async function syncScores(force = false) {
  try {
    const d = await api(`/api/match/${MID}`);
    if (typeof d.score_a !== 'undefined' && elA) {
      const changedA = String(elA.textContent) !== String(d.score_a);
      elA.textContent = d.score_a;
      if (force || changedA) bump(elA);
    }
    if (typeof d.score_b !== 'undefined' && elB) {
      const changedB = String(elB.textContent) !== String(d.score_b);
      elB.textContent = d.score_b;
      if (force || changedB) bump(elB);
    }
    applyStatus(d.status);
    pulseFinishCTA(d.reached === true);
  } catch (e) { showAlert('Sync failed: ' + e.message); }
}

// Score ändern
async function changeScore(team, delta) {
  const body = new URLSearchParams({ team, delta: String(delta) });
  const d = await api(`/api/match/${MID}/score`, {
    method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body
  });
  if (typeof d.score_a !== 'undefined' && elA) elA.textContent = d.score_a;
  if (typeof d.score_b !== 'undefined' && elB) elB.textContent = d.score_b;
  bump(team === 'A' ? elA : elB);
  if (d.reached === true) showAlert('Target reached — press "Finish match" to finalize.', 'success');
  pulseFinishCTA(d.reached === true);
  applyStatus(d.status);
  return d;
}

// Events
window.addEventListener('load', () => {
  syncScores(true);
  poller = setInterval(syncScores, 2000);

  // Buttons
  document.querySelectorAll('.js-score').forEach(btn => {
    btn.addEventListener('click', async () => {
      const team = btn.dataset.team;
      const delta = parseInt(btn.dataset.delta || '0', 10);
      btn.disabled = true;
      try { await changeScore(team, delta); }
      catch (e) { showAlert('Could not change score: ' + e.message, 'danger'); }
      finally { btn.disabled = false; }
    });
  });

  // Undo
  if (btnUndo) {
    btnUndo.addEventListener('click', async () => {
      btnUndo.disabled = true;
      try {
        const d = await api(`/api/match/${MID}/undo`, { method: 'POST' });
        if (elA) { elA.textContent = d.score_a; bump(elA); }
        if (elB) { elB.textContent = d.score_b; bump(elB); }
        pulseFinishCTA(d.reached === true);
        showAlert('Last action undone.', 'info');
      } catch (e) {
        showAlert(e.message === 'no_events' ? 'Nothing to undo.' : 'Undo failed: ' + e.message, 'danger');
      } finally { btnUndo.disabled = false; }
    });
  }

  // Finish
  if (btnFinish) {
    btnFinish.addEventListener('click', async () => {
      if (!confirm('Finish this match and update stats/Elo?')) return;
      btnFinish.disabled = true;
      try {
        const d = await api(`/api/match/${MID}/finish`, { method: 'POST' });
        if (elA && typeof d.score_a !== 'undefined') elA.textContent = d.score_a;
        if (elB && typeof d.score_b !== 'undefined') elB.textContent = d.score_b;
        setFinishedUI();
        showAlert('Match finished.', 'success');
      } catch (e) {
        showAlert('Could not finish: ' + e.message, 'danger');
        btnFinish.disabled = false;
      }
    });
  }
});

// Re-sync bei Fokus/Visibility
document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible') syncScores(); });
window.addEventListener('focus', syncScores);
window.addEventListener('pageshow', (e) => { if (e.persisted) syncScores(true); });

// Keyboard (A/Z/K/M/U/Enter)
const INPUT_TAGS = new Set(['INPUT','TEXTAREA','SELECT','BUTTON']);
window.addEventListener('keydown', async (e) => {
  const tag = (e.target && e.target.tagName) ? e.target.tagName.toUpperCase() : '';
  if (INPUT_TAGS.has(tag) && tag !== 'BUTTON') return;

  const k = e.key.toLowerCase();
  try {
    if (k === 'a') { e.preventDefault(); await changeScore('A', +1); }
    else if (k === 'z') { e.preventDefault(); await changeScore('A', -1); }
    else if (k === 'k') { e.preventDefault(); await changeScore('B', +1); }
    else if (k === 'm') { e.preventDefault(); await changeScore('B', -1); }
    else if (k === 'u') { e.preventDefault(); btnUndo?.click(); }
    else if (k === 'enter') { e.preventDefault(); btnFinish?.click(); }
  } catch { /* Fehler werden im UI angezeigt */ }
});
