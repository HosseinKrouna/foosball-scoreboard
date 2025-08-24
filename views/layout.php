<?php
declare(strict_types=1);
/** @var string|null $title */
/** @var string|null $content */
$pageTitle = isset($title) && $title !== '' ? $title . ' · Foosball' : 'Foosball Scoreboard';
$cur = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$active = function(string $p) use ($cur): string {
  if ($p === '/') return $cur === '/' ? 'active' : '';
  return (strpos($cur, $p) === 0) ? 'active' : '';
};
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="dark light">
    <?php if (function_exists('csrf_token')): ?>
    <meta name="csrf-token" content="<?= htmlspecialchars(csrf_token(), ENT_QUOTES) ?>">
    <?php endif; ?>

    <title><?= htmlspecialchars($pageTitle) ?></title>

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Flatpickr (nur für /matches Filter) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/dark.css">
    <!-- Theme -->
    <link rel="stylesheet" href="/css/style.css">

</head>

<body>
    <!-- animierter Hintergrund -->
    <div class="fx-bg" aria-hidden="true"></div>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-md glass-nav sticky-top">
        <div class="container">
            <a class="navbar-brand" href="/">
                <!-- kleines Icon -->
                <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path d="M7 4h10l1 4-6 3-6-3 1-4zm-2 16h14v-2l-5-3-2 1-2-1-5 3v2z" />
                </svg>
                Foosball <span class="brand-badge">Scoreboard</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"
                aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon" style="filter: invert(1);"></span>
            </button>

            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav me-auto mb-2 mb-md-0">
                    <li class="nav-item"><a class="nav-link <?= $active('/leaderboard') ?>"
                            href="/leaderboard">Leaderboard</a></li>
                    <li class="nav-item"><a class="nav-link <?= $active('/matches') ?>" href="/matches">History</a></li>
                    <li class="nav-item"><a class="nav-link <?= $active('/teams') ?>" href="/teams">Teams</a></li>
                </ul>
                <div class="d-flex">
                    <a class="btn btn-primary" href="/match/new">+ New Match</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Inhalt -->
    <main class="container my-3">
        <?= $content ?? '' ?>
    </main>

    <footer class="container my-4">
        <p class="muted small mb-0">Foosball Scoreboard • <?= date('Y') ?></p>
    </footer>

    <script defer src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
</body>

</html>