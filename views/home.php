<?php ob_start(); ?>
<h1>Foosball Scoreboard</h1>
<p>Router & Views funktionieren !!</p>
<p><a href="/leaderboard"> Zum Leaderboard</a></p>
<?php $content = ob_get_clean(); include __DIR__ . '/layout.php'; ?>