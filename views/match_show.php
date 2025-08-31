<?php ob_start(); ?>

<?php if (!empty($created)): ?>
<div class="alert alert-success py-2">Match created — adjust scores on this TV view.</div>
<?php endif; ?>
<?php if (!empty($finishedMsg)): ?>
<div class="alert alert-info py-2">Match finished. Stats and Elo updated.</div>
<?php endif; ?>

<div id="appAlert" class="alert alert-warning py-2 d-none"></div>

<!-- Header -->
<div class="card bg-transparent border-0 shadow-soft mb-3">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span class="badge badge-mode"><?= htmlspecialchars($match['mode']) ?></span>
            <span class="badge bg-secondary">to <?= (int)$match['target_score'] ?></span>
            <span id="matchStatusText" class="muted">
                <?= (($match['status'] ?? 'in_progress') !== 'finished') ? 'In progress' : 'Finished' ?>
                · <?= htmlspecialchars(date('Y-m-d H:i', strtotime($match['played_at']))) ?>
            </span>
            <?php if (!empty($match['notes'])): ?>
            <span class="muted">Notes: <?= htmlspecialchars($match['notes']) ?></span>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Scoreboard -->
<div id="matchApp" data-mid="<?= (int)$match['id'] ?>"
    data-status="<?= htmlspecialchars($match['status'] ?? 'in_progress') ?>">
    <div class="card bg-transparent border-0 shadow-soft">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="team-name text-light">
                    <?= htmlspecialchars($match['team_a_name']) ?> <span class="muted">·
                        <?= (int)$match['rating_a'] ?></span>
                </div>
                <div class="team-name text-end text-light">
                    <?= htmlspecialchars($match['team_b_name']) ?> <span class="muted">·
                        <?= (int)$match['rating_b'] ?></span>
                </div>
            </div>

            <?php $inProg = (($match['status'] ?? 'in_progress') !== 'finished'); ?>
            <div class="d-flex align-items-center justify-content-center gap-3">
                <?php if ($inProg): ?>
                <button class="btn btn-outline-secondary btn-sm js-score" data-team="A" data-delta="-1">−</button>
                <?php endif; ?>

                <div id="scoreA" class="score text-light" style="min-width:2ch; text-align:right;">
                    <?= isset($match['score_a']) ? (int)$match['score_a'] : 0 ?>
                </div>
                <div class="score text-light" aria-hidden="true">:</div>
                <div id="scoreB" class="score text-light" style="min-width:2ch; text-align:left;">
                    <?= isset($match['score_b']) ? (int)$match['score_b'] : 0 ?>
                </div>

                <?php if ($inProg): ?>
                <button class="btn btn-outline-secondary btn-sm js-score" data-team="B" data-delta="1">+</button>
                <?php endif; ?>
            </div>

            <?php if ($inProg): ?>
            <div class="d-flex justify-content-center gap-3 mt-3 flex-wrap">
                <button class="btn btn-outline-secondary btn-sm js-score" data-team="A" data-delta="1">+ A</button>
                <button class="btn btn-outline-secondary btn-sm js-score" data-team="B" data-delta="-1">− B</button>
                <button id="btnUndo" class="btn btn-outline-warning btn-sm" type="button">Undo last</button>
            </div>
            <div class="d-flex justify-content-center mt-4">
                <button id="btnFinish" class="btn btn-primary btn-lg" type="button">Finish match</button>
            </div>
            <?php else: ?>
            <div class="text-center mt-3" id="finishedBadgeWrap">
                <span class="badge bg-success">Finished</span>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Seite-spezifisches JS -->
<script src="/assets/match_show.js"></script>

<?php $content = ob_get_clean(); include __DIR__ . '/layout.php'; ?>