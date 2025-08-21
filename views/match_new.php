<?php ob_start(); ?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h3 mb-0">New Match</h1>
    <a class="btn btn-outline-info btn-sm" href="/leaderboard">← Leaderboard</a>
</div>

<?php if (empty($teams)): ?>
<div class="alert alert-warning">
    No teams available yet. Please add teams first (via SQL seed).
</div>
<?php else: ?>
<div class="card bg-transparent border-0 shadow-sm">
    <div class="card-body">
        <form action="/match" method="post">
            <div class="mb-3">
                <label for="mode" class="form-label">Mode</label>
                <select id="mode" name="mode" class="form-select" required>
                    <option value="1v1">1v1</option>
                    <option value="2v2">2v2</option>
                </select>
            </div>

            <div class="row g-3">
                <div class="col-md-6">
                    <label for="team_a" class="form-label">Team A</label>
                    <select id="team_a" name="team_a_id" class="form-select" required>
                        <option value="" disabled selected>Choose team…</option>
                        <?php foreach ($teams as $t): ?>
                        <option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label for="team_b" class="form-label">Team B</label>
                    <select id="team_b" name="team_b_id" class="form-select" required>
                        <option value="" disabled selected>Choose team…</option>
                        <?php foreach ($teams as $t): ?>
                        <option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="row g-3 mt-1">
                <div class="col-md-6">
                    <label for="score_a" class="form-label">Score A</label>
                    <input id="score_a" name="score_a" type="number" min="0" step="1" value="0" class="form-control"
                        required>
                </div>
                <div class="col-md-6">
                    <label for="score_b" class="form-label">Score B</label>
                    <input id="score_b" name="score_b" type="number" min="0" step="1" value="0" class="form-control"
                        required>
                </div>
            </div>

            <div class="mt-3">
                <label for="notes" class="form-label">Notes (optional)</label>
                <input id="notes" name="notes" type="text" class="form-control" placeholder="e.g., friendly match">
            </div>

            <div class="d-flex gap-2 mt-4">
                <a class="btn btn-outline-secondary" href="/leaderboard">Cancel</a>
                <!-- Speichern folgt im nächsten Schritt (POST /match) -->
                <button class="btn btn-primary" type="button" disabled title="Saving comes next step">
                    Save (next step)
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
<?php $content = ob_get_clean(); include __DIR__ . '/layout.php'; ?>