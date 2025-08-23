<?php ob_start(); ?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h3 mb-0">Matches</h1>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-info btn-sm" href="/leaderboard">Leaderboard</a>
        <a class="btn btn-primary btn-sm" href="/match/new">+ New Match</a>
    </div>
</div>

<div class="card bg-transparent border-0 shadow-sm">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Date</th>
                    <th>Teams</th>
                    <th>Score</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($matches as $m): ?>
                <tr>
                    <td><a href="/match/<?= (int)$m['id'] ?>">#<?= (int)$m['id'] ?></a></td>
                    <td><?= htmlspecialchars(date('Y-m-d H:i', strtotime($m['played_at'] ?? 'now'))) ?></td>
                    <td><?= htmlspecialchars($m['team_a']) ?> vs <?= htmlspecialchars($m['team_b']) ?></td>
                    <td><?= (int)$m['score_a'] ?> : <?= (int)$m['score_b'] ?></td>
                    <td>
                        <?php if (($m['status'] ?? 'in_progress') === 'finished'): ?>
                        <span class="badge bg-success">finished</span>
                        <?php else: ?>
                        <span class="badge bg-secondary">in&nbsp;progress</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php $content = ob_get_clean(); include __DIR__ . '/layout.php'; ?>