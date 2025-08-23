<?php ob_start(); ?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h3 mb-0">Match #<?= (int)$match['id'] ?></h1>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-info btn-sm" href="/leaderboard">Leaderboard</a>
        <a class="btn btn-outline-secondary btn-sm" href="/matches">History</a>
        <button id="btnMute" class="btn btn-outline-secondary btn-sm" type="button" title="Toggle sound">🔊</button>
        <a class="btn btn-primary btn-sm" href="/match/new">+ New Match</a>
    </div>
</div>

<?php if (!empty($created)): ?>
<div class="alert alert-success py-2">Match created — adjust scores on this TV view.</div>
<?php endif; ?>
<?php if (!empty($finishedMsg)): ?>
<div class="alert alert-info py-2">Match finished. Stats and Elo updated.</div>
<?php endif; ?>

<div id="appAlert" class="alert alert-warning py-2 d-none"></div>

<div class="card bg-transparent border-0 shadow-soft mb-3">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span class="badge badge-mode"><?= htmlspecialchars($match['mode']) ?></span>
            <span class="badge bg-secondary">to <?= (int)$match['target_score'] ?></span>
            <span id="matchStatusText" class="muted">
                <?= (($match['status'] ?? 'in_progress') !== 'finished') ? 'In progress' : 'Finished' ?>
                · <?= htmlspecialchars(date('Y-m-d H:i', strtotime($match['played_at']))) ?>
            </span>
            <?php if (!empty($match['notes'])): ?>
            <span class="muted">Notes: <?= htmlspecialchars($match['notes']) ?></span>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="card bg-transparent border-0 shadow-soft">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="team-name text-light">
                <?= htmlspecialchars($match['team_a_name']) ?> <span class="muted">·
                    <?= (int)$match['rating_a'] ?></span>
            </div>
            <div class="team-name text-end text-light">
                <?= htmlspecialchars($match['team_b_name']) ?> <span class="muted">·
                    <?= (int)$match['rating_b'] ?></span>
            </div>
        </div>

        <?php $inProg = (($match['status'] ?? 'in_progress') !== 'finished'); ?>
        <div class="d-flex align-items-center justify-content-center gap-3">
            <?php if ($inProg): ?>
            <button class="btn btn-outline-secondary btn-sm js-score" data-team="A" data-delta="-1">−</button>
            <?php endif; ?>

            <div id="scoreA" class="score text-light" style="min-width:2ch; text-align:right;">
                <?= isset($match['score_a']) ? (int)$match['score_a'] : 0 ?>
            </div>
            <div class="score text-light" aria-hidden="true">:</div>
            <div id="scoreB" class="score text-light" style="min-width:2ch; text-align:left;">
                <?= isset($match['score_b']) ? (int)$match['score_b'] : 0 ?>
            </div>

            <?php if ($inProg): ?>
            <button class="btn btn-outline-secondary btn-sm js-score" data-team="B" data-delta="1">+</button>
            <?php endif; ?>
        </div>

        <?php if ($inProg): ?>
        <div class="d-flex justify-content-center gap-3 mt-3 flex-wrap">
            <button class="btn btn-outline-secondary btn-sm js-score" data-team="A" data-delta="1">+ A</button>
            <button class="btn btn-outline-secondary btn-sm js-score" data-team="B" data-delta="-1">− B</button>
            <button id="btnUndo" class="btn btn-outline-warning btn-sm" type="button">Undo last</button>
        </div>
        <div class="d-flex justify-content-center mt-4">
            <button id="btnFinish" class="btn btn-primary btn-lg" type="button">Finish match</button>
        </div>
        <?php else: ?>
        <div class="text-center mt-3" id="finishedBadgeWrap">
            <span class="badge bg-success">Finished</span>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
// BASE robust aus URL ziehen
(function() {
    const m = window.location.pathname.match(/^(.*)\/match\/\d+(?:\/.*)?$/);
    window.__BASE__ = m ? m[1] : '';
})();
const BASE = window.__BASE__;
const MID = <?= (int)$match['id'] ?>;
const API = (p) => BASE + p;
const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

// DOM
const elA = document.getElementById('scoreA');
const elB = document.getElementById('scoreB');
const alertBox = document.getElementById('appAlert');
const btnFinish = document.getElementById('btnFinish');
const btnUndo = document.getElementById('btnUndo');
const statusTextEl = document.getElementById('matchStatusText');

let finishedApplied = <?= (($match['status'] ?? 'in_progress') !== 'finished') ? 'false' : 'true' ?>;
let poller = null;

// Helpers
function showAlert(msg, type = 'warning') {
    alertBox.className = `alert alert-${type} py-2`;
    alertBox.textContent = msg;
    alertBox.classList.remove('d-none');
    clearTimeout(showAlert._t);
    showAlert._t = setTimeout(() => alertBox.classList.add('d-none'), 2200);
}

function bump(el) {
    if (reduce) return;
    el.classList.add('bump');
    setTimeout(() => el.classList.remove('bump'), 240);
}
async function api(path, opts) {
    const res = await fetch(API(path), opts);
    const data = await res.json().catch(() => ({}));
    if (!res.ok || data.ok === false) {
        throw new Error(data.error || `HTTP ${res.status}`);
    }
    return data;
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
        document.querySelector('.card .card-body').appendChild(badge);
    }
    if (statusTextEl) {
        statusTextEl.innerHTML = statusTextEl.innerHTML.replace('In progress', 'Finished');
        if (!/Finished/.test(statusTextEl.textContent)) {
            statusTextEl.innerHTML = 'Finished · ' + statusTextEl.textContent.replace(/^.*·\s*/, '');
        }
    }
    pulseFinishCTA(false);
    if (poller) {
        clearInterval(poller);
        poller = null;
    }
}

function applyStatus(status) {
    if (status === 'finished') setFinishedUI();
}

// Sync
async function syncScores(force = false) {
    try {
        const d = await api(`/api/match/${MID}`);
        if (typeof d.score_a !== 'undefined') {
            const changedA = String(elA.textContent) !== String(d.score_a);
            elA.textContent = d.score_a;
            if (force || changedA) bump(elA);
        }
        if (typeof d.score_b !== 'undefined') {
            const changedB = String(elB.textContent) !== String(d.score_b);
            elB.textContent = d.score_b;
            if (force || changedB) bump(elB);
        }
        applyStatus(d.status);
        pulseFinishCTA(d.reached === true);
    } catch (e) {
        showAlert('Sync failed: ' + e.message);
    }
}

window.addEventListener('load', () => {
    syncScores(true);
    poller = setInterval(syncScores, 2000);
});
document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') syncScores();
});
window.addEventListener('focus', syncScores);
window.addEventListener('pageshow', (e) => {
    if (e.persisted) syncScores(true);
});

// Score Buttons
document.querySelectorAll('.js-score').forEach(btn => {
    btn.addEventListener('click', async () => {
        const team = btn.dataset.team;
        const delta = parseInt(btn.dataset.delta || '0', 10);
        btn.disabled = true;
        try {
            const body = new URLSearchParams({
                team,
                delta: String(delta)
            });
            const d = await api(`/api/match/${MID}/score`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body
            });
            elA.textContent = d.score_a;
            elB.textContent = d.score_b;
            bump(team === 'A' ? elA : elB);
            if (d.reached === true) showAlert('Target reached — press "Finish match" to finalize.',
                'success');
            pulseFinishCTA(d.reached === true);
            applyStatus(d.status);
        } catch (e) {
            showAlert('Could not change score: ' + e.message, 'danger');
        } finally {
            btn.disabled = false;
        }
    });
});

// Undo
if (btnUndo) {
    btnUndo.addEventListener('click', async () => {
        btnUndo.disabled = true;
        try {
            const d = await api(`/api/match/${MID}/undo`, {
                method: 'POST'
            });
            elA.textContent = d.score_a;
            elB.textContent = d.score_b;
            bump(elA);
            bump(elB);
            pulseFinishCTA(d.reached === true);
            showAlert('Last action undone.', 'info');
        } catch (e) {
            showAlert(e.message === 'no_events' ? 'Nothing to undo.' : 'Undo failed: ' + e.message,
                'danger');
        } finally {
            btnUndo.disabled = false;
        }
    });
}

// Finish
if (btnFinish) {
    btnFinish.addEventListener('click', async () => {
        if (!confirm('Finish this match and update stats/Elo?')) return;
        btnFinish.disabled = true;
        try {
            const d = await api(`/api/match/${MID}/finish`, {
                method: 'POST'
            });
            elA.textContent = d.score_a ?? elA.textContent;
            elB.textContent = d.score_b ?? elB.textContent;
            applyStatus('finished');
            showAlert('Match finished.', 'success');
        } catch (e) {
            showAlert('Could not finish: ' + e.message, 'danger');
            btnFinish.disabled = false;
        }
    });
}


// ====== Keyboard-Shortcuts & einfache Sounds ======

// Mute-Status merken
let muted = (localStorage.getItem('tv_muted') === '1');

// Mute-Button referenzieren (falls vorhanden)
const btnMute = document.getElementById('btnMute');

function renderMute() {
    if (!btnMute) return;
    btnMute.textContent = muted ? '🔇' : '🔊';
    btnMute.setAttribute('aria-pressed', muted ? 'true' : 'false');
}
renderMute();

if (btnMute) {
    btnMute.addEventListener('click', () => {
        muted = !muted;
        localStorage.setItem('tv_muted', muted ? '1' : '0');
        renderMute();
    });
}

// Simpler Ton über WebAudio
let audioCtx = null;

function playTone(freq = 660, durMs = 80, gain = 0.04) {
    if (muted || reduce) return;
    try {
        audioCtx = audioCtx || new(window.AudioContext || window.webkitAudioContext)();
        const osc = audioCtx.createOscillator();
        const g = audioCtx.createGain();
        osc.frequency.value = freq;
        osc.type = 'sine';
        g.gain.value = gain;
        osc.connect(g);
        g.connect(audioCtx.destination);
        osc.start();
        setTimeout(() => {
            osc.stop();
            osc.disconnect();
            g.disconnect();
        }, durMs);
    } catch (_) {
        /* ignore */ }
}

// Helper: Score ändern (gleiche API wie Buttons)
async function changeScore(team, delta) {
    const body = new URLSearchParams({
        team,
        delta: String(delta)
    });
    const d = await api(`/api/match/${MID}/score`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body
    });
    if (typeof d.score_a !== 'undefined') elA.textContent = d.score_a;
    if (typeof d.score_b !== 'undefined') elB.textContent = d.score_b;
    bump(team === 'A' ? elA : elB);
    if (delta > 0) playTone(740, 90, 0.05);
    else playTone(520, 90, 0.05);
    // Falls Ziel erreicht: visueller Hinweis, kein Auto-Finish
    if (d.reached === true) {
        showAlert('Target reached — press "Finish match" to finalize.', 'success');
        const btnFinish = document.getElementById('btnFinish');
        if (btnFinish) {
            btnFinish.classList.remove('btn-primary');
            btnFinish.classList.add('btn-success');
            btnFinish.style.boxShadow = '0 0 0.8rem rgba(40,167,69,.55)';
        }
    }
    return d;
}

// Undo auslösen
async function triggerUndo() {
    const btnUndo = document.getElementById('btnUndo');
    if (btnUndo) btnUndo.disabled = true;
    try {
        const d = await api(`/api/match/${MID}/undo`, {
            method: 'POST'
        });
        elA.textContent = d.score_a;
        elB.textContent = d.score_b;
        bump(elA);
        bump(elB);
        playTone(440, 120, 0.05);
        showAlert('Last action undone.', 'info');
    } catch (e) {
        showAlert('Undo failed: ' + e.message, 'danger');
    } finally {
        if (btnUndo) btnUndo.disabled = false;
    }
}

// Finish auslösen
async function triggerFinish() {
    const btnFinish = document.getElementById('btnFinish');
    if (!btnFinish) return;
    if (!confirm('Finish this match and update stats/Elo?')) return;
    btnFinish.disabled = true;
    try {
        await api(`/api/match/${MID}/finish`, {
            method: 'POST'
        });
        playTone(880, 180, 0.06);
        showAlert('Match finished.', 'success');
        // UI abbauen
        document.querySelectorAll('.js-score').forEach(b => b.remove());
        const badge = document.createElement('div');
        badge.className = 'text-center mt-3';
        badge.innerHTML = '<span class="badge bg-success">Finished</span>';
        document.querySelector('.card .card-body').appendChild(badge);
        if (typeof poller !== 'undefined') clearInterval(poller);
        await syncScores();
    } catch (e) {
        showAlert('Could not finish: ' + e.message, 'danger');
        btnFinish.disabled = false;
    }
}

// Tastatur-Shortcuts
// A=+A, Z=-A, K=+B, M=-B, U=Undo, Enter=Finish, Space=Mute
const INPUT_TAGS = new Set(['INPUT', 'TEXTAREA', 'SELECT', 'BUTTON']);
window.addEventListener('keydown', async (e) => {
    // Eingabefelder nicht stören
    const tag = (e.target && e.target.tagName) ? e.target.tagName.toUpperCase() : '';
    if (INPUT_TAGS.has(tag) && !(tag === 'BUTTON')) return;

    const k = e.key.toLowerCase();
    try {
        if (k === 'a') {
            e.preventDefault();
            await changeScore('A', +1);
        } else if (k === 'z') {
            e.preventDefault();
            await changeScore('A', -1);
        } else if (k === 'k') {
            e.preventDefault();
            await changeScore('B', +1);
        } else if (k === 'm') {
            e.preventDefault();
            await changeScore('B', -1);
        } else if (k === 'u') {
            e.preventDefault();
            await triggerUndo();
        } else if (k === 'enter') {
            e.preventDefault();
            await triggerFinish();
        } else if (k === ' ') {
            e.preventDefault();
            muted = !muted;
            localStorage.setItem('tv_muted', muted ? '1' : '0');
            renderMute();
        }
    } catch (_) {
        /* Alerts kommen aus den Funktionen */ }
});
</script>
<?php $content = ob_get_clean(); include __DIR__ . '/layout.php'; ?>