<?php ob_start(); ?>

<?php if (!empty($created)): ?>
<div class="alert alert-success py-2">Match created — adjust scores on this TV view.</div>
<?php endif; ?>
<?php if (!empty($finishedMsg)): ?>
<div class="alert alert-info py-2">Match finished. Stats and Elo updated.</div>
<?php endif; ?>

<div id="appAlert" class="alert alert-warning py-2 d-none"></div>

<!-- Header-Infos -->
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

<!-- Scoreboard -->
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
// ===== Base-URL & Konstanten =====
(function() {
    const m = window.location.pathname.match(/^(.*)\/match\/\d+(?:\/.*)?$/);
    window.__BASE__ = m ? m[1] : '';
})();
const BASE = window.__BASE__;
const MID = <?= (int)$match['id'] ?>;
const API = (p) => BASE + p;
const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

// CSRF-Token aus <meta> lesen (Layout)
const CSRF = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

// ===== DOM-Refs =====
const elA = document.getElementById('scoreA');
const elB = document.getElementById('scoreB');
const alertBox = document.getElementById('appAlert');
const btnFinish = document.getElementById('btnFinish');
const btnUndo = document.getElementById('btnUndo');
const statusText = document.getElementById('matchStatusText');

let finishedApplied = <?= (($match['status'] ?? 'in_progress') !== 'finished') ? 'false' : 'true' ?>;
let poller = null;

// ===== UI-Helfer =====
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
    if (statusText) {
        // "In progress · 2025-..." -> "Finished · 2025-..."
        statusText.innerHTML = statusText.innerHTML.replace('In progress', 'Finished');
        if (!/Finished/.test(statusText.textContent)) {
            statusText.innerHTML = 'Finished · ' + statusText.textContent.replace(/^.*·\s*/, '');
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


// ===== API-Helper (sendet CSRF-Header bei POST) =====
async function api(path, opts = {}) {
    const isPostLike = (opts.method || 'GET').toUpperCase() !== 'GET';
    const headers = Object.assign({}, opts.headers || {}, isPostLike && CSRF ? {
        'X-CSRF-Token': CSRF
    } : {});
    const res = await fetch(API(path), Object.assign({}, opts, {
        headers
    }));
    const data = await res.json().catch(() => ({}));
    if (!res.ok || data.ok === false) {
        const err = data.error || `HTTP ${res.status}`;
        throw new Error(err);
    }
    return data;
}

// ===== Sync-Logik =====
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

// ===== Score ändern (Buttons nutzen diese Funktion) =====
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
    if (d.reached === true) showAlert('Target reached — press "Finish match" to finalize.', 'success');
    pulseFinishCTA(d.reached === true);
    applyStatus(d.status);
    return d;
}

// Buttons: +/−
document.querySelectorAll('.js-score').forEach(btn => {
    btn.addEventListener('click', async () => {
        const team = btn.dataset.team;
        const delta = parseInt(btn.dataset.delta || '0', 10);
        btn.disabled = true;
        try {
            await changeScore(team, delta);
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
            bump(elA);
            elB.textContent = d.score_b;
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
            setFinishedUI();
            showAlert('Match finished.', 'success');
        } catch (e) {
            showAlert('Could not finish: ' + e.message, 'danger');
            btnFinish.disabled = false;
        }
    });
}

// ===== Keyboard-Shortcuts =====
// A=+A, Z=-A, K=+B, M=-B, U=Undo, Enter=Finish
const INPUT_TAGS = new Set(['INPUT', 'TEXTAREA', 'SELECT', 'BUTTON']);
window.addEventListener('keydown', async (e) => {
    const tag = (e.target && e.target.tagName) ? e.target.tagName.toUpperCase() : '';
    if (INPUT_TAGS.has(tag) && tag !== 'BUTTON') return;

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
            btnUndo?.click();
        } else if (k === 'enter') {
            e.preventDefault();
            btnFinish?.click();
        }
    } catch (_) {
        /* Fehler werden bereits angezeigt */
    }
});
</script>
<?php $content = ob_get_clean(); include __DIR__ . '/layout.php'; ?>