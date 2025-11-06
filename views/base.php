<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title><?= isset($title) ? htmlspecialchars($title) : 'ivi.php' ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>

<body>
    <main>
        <h1>Base.php</h1>
        <?= $content ?? '' ?>
    </main>
</body>

</html>