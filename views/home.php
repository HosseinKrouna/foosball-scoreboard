<?php ob_start(); ?>
<section class="py-4">
    <div class="text-center mb-4">

        <section class="hero-cover d-flex">
            <video class="hero-cover__bg" autoplay controls loop muted playsinline preload="metadata"
                src="https://dtfb.de/images/videos/WEBSITE_TRAILER.mp4" type="video/mp4">
                Dein Browser unterstützt das Video-Tag nicht.
            </video>

            <div class="hero-cover__content flex-column align-self-center align-items-center d-flex container">
                <h1>Töggelikasten</h1>
                <a class="btn btn-primary shadow-lg" href="/match/new">
                    <img src="/assets/images/plus-outline-white-thick-512.png" width="16" height="16" alt="Neues Match">
                    <span class="link-titel-match-create">Match erstellen</span>
                </a>
            </div>
        </section>

    </div>

    <?php if (!empty($stats) && is_array($stats)): ?>
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card card-stat text-center">
                <div class="card-body">
                    <div class="state-matches h2 text-white mb-1"><?= (int)($stats['teams'] ?? 0) ?></div>
                    <div class="muted">Teams</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-stat text-center">
                <div class="card-body">
                    <div class="state-matches h2 text-white mb-1"><?= (int)($stats['matches'] ?? 0) ?></div>
                    <div class="muted">Spiele</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-stat text-center">
                <div class="card-body">
                    <div class="state-matches h2 text-white mb-1"><?= (int)($stats['finished'] ?? 0) ?></div>
                    <div class="muted">Abgeschlossen</div>
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
                        <h3 class="h5 mb-2 text-white">Spielverlauf</h3>
                        <p class="muted mb-0">Durchsuche aktuelle Spiele, filtere nach Team oder Datum und blättere mit
                            der Seitennavigation.</p>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-6">
            <a href="/teams" class="text-decoration-none">
                <div class="card h-100 hover-card">
                    <div class="card-body">
                        <h3 class="h5 mb-2 text-white">Teams</h3>
                        <p class="muted mb-0">Alle registrierten Teams mit Rating und Bilanz.</p>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <?php if (!empty($recent) && is_array($recent)): ?>
    <div class="card bg-transparent border-0 shadow-soft mt-4">
        <div class="card-body p-0">
            <div class="p-2 muted small">Neueste Spiele</div>
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th style="width:80px;">ID</th>
                        <th style="width:160px;">Datum</th>
                        <th>Teams</th>
                        <th style="width:120px;">Spielstand</th>
                        <th style="width:120px;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent as $m): ?>
                    <tr>
                        <td><a href="/match/<?= (int)$m['id'] ?>">#<?= (int)$m['id'] ?></a></td>
                        <td><?= htmlspecialchars(date('Y-m-d H:i', strtotime($m['played_at'] ?? 'now'))) ?></td>
                        <td><?= htmlspecialchars($m['team_a']) ?> gegen <?= htmlspecialchars($m['team_b']) ?></td>
                        <td><?= (int)$m['score_a'] ?> : <?= (int)$m['score_b'] ?></td>
                        <td>
                            <?php if (($m['status'] ?? 'in_progress') === 'finished'): ?>
                            <span class="badge bg-success">abgeschlossen</span>
                            <?php else: ?>
                            <span class="badge bg-secondary">läuft</span>
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