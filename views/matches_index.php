<?php ob_start(); ?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h3 mb-0">Matches</h1>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-info btn-sm" href="/leaderboard">Leaderboard</a>
        <a class="btn btn-primary btn-sm" href="/match/new">+ New Match</a>
    </div>
</div>

<!-- Filter -->
<form class="card bg-transparent border-0 shadow-sm mb-3" method="get" action="/matches">
    <div class="card-body">
        <div class="row g-3 align-items-end">
            <div class="col-md-4">
                <label for="team_id" class="form-label">Team</label>
                <select id="team_id" name="team_id" class="form-select">
                    <option value="0">All teams</option>
                    <?php foreach ($teams as $t): ?>
                    <option value="<?= (int)$t['id'] ?>"
                        <?= (!empty($selectedTeamId) && (int)$selectedTeamId === (int)$t['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($t['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3">
                <label for="status" class="form-label">Status</label>
                <select id="status" name="status" class="form-select">
                    <?php
              $opts = ['all'=>'All','in_progress'=>'In progress','finished'=>'Finished'];
              foreach ($opts as $val=>$label):
                $sel = (isset($selectedStatus) && $selectedStatus === $val) ? 'selected' : '';
            ?>
                    <option value="<?= $val ?>" <?= $sel ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2">
                <label for="from" class="form-label">From</label>
                <input id="from" name="from" type="text" class="form-control js-date"
                    value="<?= htmlspecialchars($selectedFrom ?? '') ?>" placeholder="YYYY-MM-DD" autocomplete="off">
            </div>

            <div class="col-md-2">
                <label for="to" class="form-label">To</label>
                <input id="to" name="to" type="text" class="form-control js-date"
                    value="<?= htmlspecialchars($selectedTo ?? '') ?>" placeholder="YYYY-MM-DD" autocomplete="off">
            </div>

            <div class="col-md-1 d-flex gap-2">
                <button class="btn btn-primary w-100" type="submit">Apply</button>
            </div>
            <div class="col-md-12 mt-2">
                <?php if (!empty($selectedTeamId) || (isset($selectedStatus) && $selectedStatus !== 'all') || !empty($selectedFrom) || !empty($selectedTo)): ?>
                <a class="btn btn-outline-secondary btn-sm" href="/matches">Reset filters</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</form>

<!-- Ergebnis-Tabelle -->
<div class="card bg-transparent border-0 shadow-sm">
    <div class="card-body p-0">
        <?php if (empty($matches)): ?>
        <div class="p-4 muted">No matches found.</div>
        <?php else: ?>
        <div class="p-2 muted small">Showing <?= count($matches) ?> result(s)</div>
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
        <?php endif; ?>
    </div>
</div>

<?php
    // Pagination-Links bauen (Filter erhalten)
    $page = max(1, (int)($_GET['page'] ?? 1));
    $params = [];
    if (!empty($selectedTeamId)) $params['team_id'] = (int)$selectedTeamId;
    if (isset($selectedStatus) && $selectedStatus !== 'all') $params['status'] = $selectedStatus;
    if (!empty($selectedFrom)) $params['from'] = $selectedFrom;
    if (!empty($selectedTo))   $params['to']   = $selectedTo;

    $prevUrl = null; $nextUrl = null;
    if (!empty($hasPrev)) { $q = $params; $q['page'] = $page - 1; $prevUrl = '/matches?' . http_build_query($q); }
    if (!empty($hasNext)) { $q = $params; $q['page'] = $page + 1; $nextUrl = '/matches?' . http_build_query($q); }
  ?>

<nav class="mt-3" aria-label="Pagination">
    <ul class="pagination justify-content-center">
        <li class="page-item <?= empty($hasPrev) ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= $prevUrl ?: '#' ?>" tabindex="-1"
                aria-disabled="<?= empty($hasPrev) ? 'true':'false' ?>">« Prev</a>
        </li>
        <li class="page-item disabled"><span class="page-link">Page <?= (int)$page ?></span></li>
        <li class="page-item <?= empty($hasNext) ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= $nextUrl ?: '#' ?>">Next »</a>
        </li>
    </ul>
</nav>

<script>
// Flatpickr init (falls eingebunden)
document.addEventListener('DOMContentLoaded', function() {
    if (window.flatpickr) {
        flatpickr('#from', {
            dateFormat: 'Y-m-d',
            maxDate: 'today',
            allowInput: true
        });
        flatpickr('#to', {
            dateFormat: 'Y-m-d',
            maxDate: 'today',
            allowInput: true
        });
    }
});
</script>
<?php $content = ob_get_clean(); include __DIR__ . '/layout.php'; ?>