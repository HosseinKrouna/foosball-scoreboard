<?php ob_start(); ?>
<div class="d-flex align-items-center justify-content-between mb-3">

    <h1 class="h3 mb-0">Teams</h1>


    <div class="card bg-transparent border-0 shadow-sm">
        <div class="card-body p-0">
            <?php if (empty($teams)): ?>
            <div class="p-4 muted">No teams yet.</div>
            <?php else: ?>
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th style="width:80px;">ID</th>
                        <th>Name</th>
                        <th style="width:120px;">Rating</th>
                        <th style="width:120px;">Wins</th>
                        <th style="width:140px;">Games</th>
                        <th style="width:180px;">Created</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($teams as $t): ?>
                    <tr>
                        <td>#<?= (int)$t['id'] ?></td>
                        <td><?= htmlspecialchars($t['name']) ?></td>
                        <td><?= (int)$t['rating'] ?></td>
                        <td><?= (int)$t['wins'] ?></td>
                        <td><?= (int)$t['games_played'] ?></td>
                        <td><?= htmlspecialchars(date('Y-m-d H:i', strtotime($t['created_at'] ?? 'now'))) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
    <?php $content = ob_get_clean(); include __DIR__ . '/layout.php'; ?>