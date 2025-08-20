<?php ob_start(); ?>
<h1>Leaderboard</h1>
<p class="muted">Platzhalterdaten – DB-Anbindung folgt.</p>
<div class="card">
    <table class="table">
        <thead>
            <tr>
                <th>#</th>
                <th>Team / Spieler</th>
                <th>Rating</th>
                <th>Siege</th>
                <th>Spiele</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($teams as $t): ?>
            <tr>
                <td><?= (int)$t['rank'] ?></td>
                <td><?= htmlspecialchars($t['name']) ?></td>
                <td><?= (int)$t['rating'] ?></td>
                <td><?= (int)$t['wins'] ?></td>
                <td><?= (int)$t['games'] ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php $content = ob_get_clean(); include __DIR__ . '/layout.php'; ?>