<?php ob_start(); ?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h3 mb-0">New Match</h1>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-info btn-sm" href="/leaderboard">Leaderboard</a>
        <a class="btn btn-outline-secondary btn-sm" href="/matches">History</a>
    </div>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <strong>Could not create:</strong>
    <ul class="mb-0">
        <?php foreach ($errors as $e): ?>
        <li><?= htmlspecialchars($e) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<?php if (empty($teams)): ?>
<div class="alert alert-warning">No teams available yet. Please add teams first.</div>
<?php else: ?>
<div class="card bg-transparent border-0 shadow-sm">
    <div class="card-body">
        <form action="/match" method="post">
            <div class="mb-3">
                <label for="mode" class="form-label">Mode</label>
                <select id="mode" name="mode" class="form-select" required>
                    <?php $sel = $old['mode'] ?? '1v1'; ?>
                    <option value="1v1" <?= $sel==='1v1' ? 'selected' : '' ?>>1v1</option>
                    <option value="2v2" <?= $sel==='2v2' ? 'selected' : '' ?>>2v2</option>
                </select>
            </div>

            <div class="row g-3">
                <div class="col-md-6">
                    <label for="team_a" class="form-label">Team A</label>
                    <select id="team_a" name="team_a_id" class="form-select" required>
                        <option value="" disabled <?= empty($old['team_a_id']) ? 'selected' : '' ?>>Choose team…
                        </option>
                        <?php foreach ($teams as $t): ?>
                        <option value="<?= (int)$t['id'] ?>"
                            <?= isset($old['team_a_id']) && (int)$old['team_a_id']===(int)$t['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($t['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label for="team_b" class="form-label">Team B</label>
                    <select id="team_b" name="team_b_id" class="form-select" required>
                        <option value="" disabled <?= empty($old['team_b_id']) ? 'selected' : '' ?>>Choose team…
                        </option>
                        <?php foreach ($teams as $t): ?>
                        <option value="<?= (int)$t['id'] ?>"
                            <?= isset($old['team_b_id']) && (int)$old['team_b_id']===(int)$t['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($t['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="mt-3">
                <label for="notes" class="form-label">Notes (optional)</label>
                <input id="notes" name="notes" type="text" class="form-control"
                    value="<?= htmlspecialchars((string)($old['notes'] ?? '')) ?>" placeholder="e.g., friendly match">
            </div>

            <div class="d-flex gap-2 mt-4">
                <a class="btn btn-outline-secondary" href="/leaderboard">Cancel</a>
                <button class="btn btn-primary" type="submit">Create & open TV view</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
<?php $content = ob_get_clean(); include __DIR__ . '/layout.php'; ?>