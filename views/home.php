<?php ob_start(); ?>
<section class="py-4">
    <div class="text-center mb-4">
        <h1 class="display-5 fw-bold" style="letter-spacing:.5px;">
            <span
                style="background:linear-gradient(90deg, var(--acc1,#33e1ed), var(--acc2,#ff5da2)); -webkit-background-clip:text; background-clip:text; color:transparent;">
                Foosball Scoreboard
            </span>
        </h1>
        <p class="lead muted mb-3">Track matches, live scores, and rankings — fast and beautiful.</p>
        <div class="d-flex justify-content-center gap-2 flex-wrap">
            <a class="btn btn-primary btn-lg" href="/match/new">Start a Match</a>
            <a class="btn btn-outline-info btn-lg" href="/leaderboard">View Leaderboard</a>
        </div>
    </div>

    <?php if (!empty($stats) && is_array($stats)): ?>
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card card-stat text-center">
                <div class="card-body">
                    <div class="h2 mb-1"><?= (int)($stats['teams'] ?? 0) ?></div>
                    <div class="muted">Teams</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-stat text-center">
                <div class="card-body">
                    <div class="h2 mb-1"><?= (int)($stats['matches'] ?? 0) ?></div>
                    <div class="muted">Matches</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-stat text-center">
                <div class="card-body">
                    <div class="h2 mb-1"><?= (int)($stats['finished'] ?? 0) ?></div>
                    <div class="muted">Finished</div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="row g-3">
        <div class="col-md-6">
            <a href="/matches" class="text-decoration-none">
                <div class="card h-100 hover-card">
                    <div class="card-body">
                        <h3 class="h5 mb-2">Match History</h3>
                        <p class="muted mb-0">Browse, filter by team or date, and paginate recent matches.</p>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-6">
            <a href="/teams" class="text-decoration-none">
                <div class="card h-100 hover-card">
                    <div class="card-body">
                        <h3 class="h5 mb-2">Teams</h3>
                        <p class="muted mb-0">See all registered teams with ratings and records.</p>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <?php if (!empty($recent) && is_array($recent)): ?>
    <div class="card bg-transparent border-0 shadow-soft mt-4">
        <div class="card-body p-0">
            <div class="p-2 muted small">Recent matches</div>
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th style="width:80px;">ID</th>
                        <th style="width:160px;">Date</th>
                        <th>Teams</th>
                        <th style="width:120px;">Score</th>
                        <th style="width:120px;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent as $m): ?>
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
    <?php endif; ?>
</section>
<?php $content = ob_get_clean(); include __DIR__ . '/layout.php'; ?>