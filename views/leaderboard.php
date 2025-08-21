<?php ob_start(); ?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h3 mb-0">Leaderboard</h1>
    <a class="btn btn-outline-info btn-sm" href="/">Home</a>
</div>

<?php if (empty($teams)): ?>
<div class="alert alert-warning">No teams yet.</div>
<?php else: ?>
<div class="card bg-transparent border-0 shadow-sm">
    <div class="card-body p-0">
        <table class="table table-dark table-striped table-hover mb-0 align-middle">
            <thead class="table-secondary text-dark">
                <tr>
                    <th scope="col">#</th>
                    <th scope="col">Team / Player</th>
                    <th scope="col">Rating</th>
                    <th scope="col">Wins</th>
                    <th scope="col">Games</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($teams as $i => $t): ?>
                <tr>
                    <th scope="row"><?= $i + 1 ?></th>
                    <td><?= htmlspecialchars($t['name']) ?></td>
                    <td><?= (int)$t['rating'] ?></td>
                    <td><?= (int)$t['wins'] ?></td>
                    <td><?= (int)$t['games_played'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
<?php $content = ob_get_clean(); include __DIR__ . '/layout.php'; ?>