<?php ob_start(); ?>

<div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h3 mb-0">Match #<?= (int)$match['id'] ?></h1>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-info btn-sm" href="/leaderboard">Leaderboard</a>
        <a class="btn btn-outline-secondary btn-sm" href="/matches">History</a>
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
            <span class="muted">
                <?= ($isInProgress ? 'In progress' : 'Finished') ?>
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
        <div class="scoreboard">
            <div class="team-name text-truncate">
                <?= htmlspecialchars($match['team_a_name']) ?>
                <span class="muted">· <?= (int)$match['rating_a'] ?></span>
            </div>

            <div class="text-center">
                <?php if ($isInProgress): ?>
                <button class="btn btn-outline-secondary btn-sm js-score" data-team="A" data-delta="-1">−</button>
                <?php endif; ?>
                <div id="scoreA" class="score d-inline-block mx-2"><?= (int)($match['score_a'] ?? 0) ?></div>
                <?php if ($isInProgress): ?>
                <button class="btn btn-outline-secondary btn-sm js-score" data-team="A" data-delta="1">+</button>
                <?php endif; ?>
                <div class="muted" style="margin-top:.25rem; font-size:.9rem;">Score</div>
            </div>

            <div class="team-name text-end text-truncate">
                <?= htmlspecialchars($match['team_b_name']) ?>
                <span class="muted">· <?= (int)$match['rating_b'] ?></span>
            </div>
        </div>

        <div class="text-center mt-3">
            <?php if ($isInProgress): ?>
            <button class="btn btn-outline-secondary btn-sm js-score me-2" data-team="B" data-delta="-1">−</button>
            <div id="scoreB" class="score d-inline-block mx-2"><?= (int)($match['score_b'] ?? 0) ?></div>
            <button class="btn btn-outline-secondary btn-sm js-score ms-2" data-team="B" data-delta="1">+</button>
            <?php else: ?>
            <div id="scoreB" class="score d-inline-block mx-2"><?= (int)($match['score_b'] ?? 0) ?></div>
            <?php endif; ?>
        </div>

        <?php if ($isInProgress): ?>
        <div class="d-flex justify-content-center mt-4">
            <button id="btnFinish" class="btn btn-primary btn-lg" type="button">Finish match</button>
        </div>
        <?php else: ?>
        <div class="text-center mt-3">
            <span class="badge bg-success">Finished</span>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
const BASE = window.location.pathname.replace(/\/match\/\d+.*$/, '');
const API = (p) => BASE + p;
const MID = <?= (int)$match['id'] ?>;
const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

const elA = document.getElementById('scoreA');
const elB = document.getElementById('scoreB');
const alertBox = document.getElementById('appAlert');

function showAlert(msg, type = 'warning') {
    alertBox.className = `alert alert-${type} py-2`;
    alertBox.textContent = msg;
    alertBox.classList.remove('d-none');
    clearTimeout(showAlert._t);
    showAlert._t = setTimeout(() => alertBox.classList.add('d-none'), 2500);
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
        const err = data.error || `HTTP ${res.status}`;
        throw new Error(err);
    }
    return data;
}

// ====== Live-Sync ======
async function syncScores(forceBump = false) {
    try {
        const data = await api(`/api/match/${MID}`);
        if (elA && String(elA.textContent) !== String(data.score_a)) {
            elA.textContent = data.score_a;
            if (forceBump) bump(elA);
        }
        if (elB && String(elB.textContent) !== String(data.score_b)) {
            elB.textContent = data.score_b;
            if (forceBump) bump(elB);
        }
    } catch (e) {
        showAlert('Sync failed: ' + e.message, 'warning');
    }
}

// Initialer Sync beim Laden
window.addEventListener('load', () => syncScores(true));

// Polling (alle 2s) – für Mehrschirm-Setups
const poller = setInterval(syncScores, 2000);

// Sync beim Tab-Refocus
document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') syncScores();
});

// ====== Score-Buttons (ohne Reload) ======
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
            const data = await api(`/api/match/${MID}/score`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body
            });
            if (typeof data.score_a !== 'undefined') elA.textContent = data.score_a;
            if (typeof data.score_b !== 'undefined') elB.textContent = data.score_b;
            bump(team === 'A' ? elA : elB);
        } catch (e) {
            showAlert('Could not change score: ' + e.message, 'danger');
        } finally {
            btn.disabled = false;
        }
    });
});

// ====== Match abschließen (AJAX) ======
const btnFinish = document.getElementById('btnFinish');
if (btnFinish) {
    btnFinish.addEventListener('click', async () => {
        if (!confirm('Finish this match and update stats/Elo?')) return;
        btnFinish.disabled = true;
        try {
            await api(`/api/match/${MID}/finish`, {
                method: 'POST'
            });
            showAlert('Match finished.', 'success');
            document.querySelectorAll('.js-score').forEach(b => b.remove());
            const badge = document.createElement('div');
            badge.className = 'text-center mt-3';
            badge.innerHTML = '<span class="badge bg-success">Finished</span>';
            document.querySelector('.card .card-body').appendChild(badge);
            clearInterval(poller);
            await syncScores(); // letzter Sync
        } catch (e) {
            showAlert('Could not finish: ' + e.message, 'danger');
            btnFinish.disabled = false;
        }
    });
}
</script>
<?php $content = ob_get_clean(); include __DIR__ . '/layout.php'; ?>