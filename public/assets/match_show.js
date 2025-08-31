/* public/assets/match_show.js */
(() => {
  'use strict';

  /* ---------- Config & DOM ---------- */
  const BASE = (() => {
    const m = window.location.pathname.match(/^(.*)\/match\/\d+(?:\/.*)?$/);
    return m ? m[1] : '';
  })();
  const CSRF   = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  const REDUCE = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  const $ = (sel, root = document) => root.querySelector(sel);
  const app       = $('#matchApp');
  const MID       = parseInt(app?.dataset.mid || '0', 10);
  const statusStr = (app?.dataset.status || 'in_progress');
  let   finishedApplied = (statusStr === 'finished');

  const elA       = $('#scoreA');
  const elB       = $('#scoreB');
  const alertBox  = $('#appAlert');
  const btnFinish = $('#btnFinish');
  const btnUndo   = $('#btnUndo');
  const statusEl  = $('#matchStatusText');

  if (!MID || !app) return; // nichts zu tun

  const API = (p) => BASE + p;

  /* ---------- UI helpers ---------- */
  function showAlert(msg, type = 'warning', ttl = 2200) {
    if (!alertBox) return;
    alertBox.className = `alert alert-${type} py-2`;
    alertBox.textContent = msg;
    alertBox.classList.remove('d-none');
    clearTimeout(showAlert._t);
    showAlert._t = setTimeout(() => alertBox.classList.add('d-none'), ttl);
  }

  function bump(el) {
    if (!el || REDUCE) return;
    el.classList.add('bump');
    setTimeout(() => el.classList.remove('bump'), 240);
  }

  function pulseFinishCTA(on) {
    if (!btnFinish) return;
    btnFinish.classList.toggle('btn-success', !!on);
    btnFinish.classList.toggle('btn-primary', !on);
    btnFinish.style.boxShadow = on ? '0 0 0.8rem rgba(40,167,69,.55)' : '';
  }

  function setFinishedUI() {
    if (finishedApplied) return;
    finishedApplied = true;
    document.querySelectorAll('.js-score').forEach(b => b.remove());
    btnUndo?.remove();
    btnFinish?.remove();
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
    stopPoll();
  }

  function applyStatus(status) {
    if (status === 'finished') setFinishedUI();
  }

  /* ---------- API helper (CSRF + Fehlerdetails) ---------- */
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

  /* ---------- Polling (mit Backoff) ---------- */
  let pollTimer = null;
  const POLL_MIN = 2000;      // 2s
  const POLL_MAX = 15000;     // 15s
  let   pollDelay = POLL_MIN;
  let   pollFail  = 0;

  function stopPoll() { if (pollTimer) { clearTimeout(pollTimer); pollTimer = null; } }
  function schedulePoll(ms) { stopPoll(); pollTimer = setTimeout(runPoll, ms); }

  async function runPoll() {
    try {
      const d = await api(`/api/match/${MID}`);
      if (typeof d.score_a !== 'undefined' && elA) {
        const changedA = String(elA.textContent) !== String(d.score_a);
        elA.textContent = d.score_a;
        if (changedA) bump(elA);
      }
      if (typeof d.score_b !== 'undefined' && elB) {
        const changedB = String(elB.textContent) !== String(d.score_b);
        elB.textContent = d.score_b;
        if (changedB) bump(elB);
      }
      applyStatus(d.status);
      pulseFinishCTA(d.reached === true);

      // success -> reset backoff
      pollFail = 0;
      pollDelay = POLL_MIN;
      schedulePoll(pollDelay);
    } catch (e) {
      showAlert('Sync failed: ' + e.message);
      pollFail++;
      pollDelay = Math.min(POLL_MAX, POLL_MIN * Math.pow(2, Math.min(pollFail, 3))); // bis 16s
      schedulePoll(pollDelay);
    }
  }

  /* ---------- Actions ---------- */
  async function changeScore(team, delta) {
    const body = new URLSearchParams({ team, delta: String(delta) });
    const d = await api(`/api/match/${MID}/score`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body
    });
    if (typeof d.score_a !== 'undefined' && elA) elA.textContent = d.score_a;
    if (typeof d.score_b !== 'undefined' && elB) elB.textContent = d.score_b;
    bump(team === 'A' ? elA : elB);
    if (d.reached === true) showAlert('Target reached — press "Finish match" to finalize.', 'success');
    pulseFinishCTA(d.reached === true);
    applyStatus(d.status);
    return d;
  }

  /* ---------- Event Delegation ---------- */
  document.addEventListener('click', async (ev) => {
    const btn = ev.target.closest('.js-score');
    if (btn) {
      const team  = btn.dataset.team;
      const delta = parseInt(btn.dataset.delta || '0', 10);
      btn.disabled = true;
      try { await changeScore(team, delta); }
      catch (e) { showAlert('Could not change score: ' + e.message, 'danger'); }
      finally { btn.disabled = false; }
      return;
    }
    if (ev.target === btnUndo) {
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
      return;
    }
    if (ev.target === btnFinish) {
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
      return;
    }
  });

  /* ---------- Keyboard ---------- */
  const INPUT_TAGS = new Set(['INPUT','TEXTAREA','SELECT','BUTTON']);
  window.addEventListener('keydown', async (e) => {
    const tag = (e.target && e.target.tagName) ? e.target.tagName.toUpperCase() : '';
    if (INPUT_TAGS.has(tag) && tag !== 'BUTTON') return;

    const k = e.key.toLowerCase();
    try {
      if (k === 'a')      { e.preventDefault(); await changeScore('A', +1); }
      else if (k === 'z') { e.preventDefault(); await changeScore('A', -1); }
      else if (k === 'k') { e.preventDefault(); await changeScore('B', +1); }
      else if (k === 'm') { e.preventDefault(); await changeScore('B', -1); }
      else if (k === 'u') { e.preventDefault(); btnUndo?.click(); }
      else if (k === 'enter') { e.preventDefault(); btnFinish?.click(); }
    } catch { /* Alerts kommen aus den Actions */ }
  });

  /* ---------- Lifecycle ---------- */
  window.addEventListener('load', () => {
    // initialer Sync + Startpoll
    runPoll();
  });
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') runPoll();
  });
  window.addEventListener('focus', runPoll);
  window.addEventListener('pageshow', (e) => { if (e.persisted) runPoll(); });
})();
