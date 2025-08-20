<?php ob_start(); ?>
<h1>New Match</h1>
<p class="muted">Nur Formular – Speichern kommt im nächsten Schritt.</p>

<form action="/match" method="post" class="card" onsubmit="return false;">
    <div class="row">
        <label for="mode">Mode</label>
        <select id="mode" name="mode" required>
            <option value="1v1">1v1</option>
            <option value="2v2">2v2</option>
        </select>
    </div>

    <div class="row">
        <label for="team_a">Team A</label>
        <select id="team_a" name="team_a_id" required>
            <option value="" disabled selected>Choose team…</option>
            <?php foreach ($teams as $t): ?>
            <option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="row">
        <label for="team_b">Team B</label>
        <select id="team_b" name="team_b_id" required>
            <option value="" disabled selected>Choose team…</option>
            <?php foreach ($teams as $t): ?>
            <option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="row">
        <label for="score_a">Score A</label>
        <input id="score_a" name="score_a" type="number" min="0" step="1" value="0" required>
    </div>

    <div class="row">
        <label for="score_b">Score B</label>
        <input id="score_b" name="score_b" type="number" min="0" step="1" value="0" required>
    </div>

    <div class="row">
        <label for="notes">Notes (optional)</label>
        <input id="notes" name="notes" type="text" placeholder="e.g. friendly match">
    </div>

    <div class="actions">
        <a class="btn" href="/leaderboard">← Back</a>
        <!-- submit ist noch disabled, da POST im nächsten Step kommt -->
        <button class="btn primary" type="button" disabled title="Save follows in next step">Save (next step)</button>
    </div>
</form>

<script>
// einfache Client-Validierung: gleiche Teams verhindern
const a = document.getElementById('team_a');
const b = document.getElementById('team_b');

function preventSame() {
    if (a.value && b.value && a.value === b.value) {
        alert('Team A und Team B dürfen nicht gleich sein.');
        b.value = '';
    }
}
a.addEventListener('change', preventSame);
b.addEventListener('change', preventSame);
</script>
<?php $content = ob_get_clean(); include __DIR__ . '/layout.php'; ?>