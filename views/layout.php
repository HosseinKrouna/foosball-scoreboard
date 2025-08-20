<?php /** @var string $title */ ?>
<!doctype html>
<html lang="de">

<head>
    <meta charset="utf-8">
    <title><?= htmlspecialchars($title ?? 'App') ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="/css/style.css" rel="stylesheet">
</head>

<body>
    <main class="container">
        <?= $content ?? '' ?>
    </main>
</body>

</html>