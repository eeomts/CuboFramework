<!doctype html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $view->escape('titulo', 'Cubo') ?></title>
    <?= $view->assets('base') ?>
</head>
<body>
    <h1><?= $view->escape('titulo', 'Cubo') ?></h1>
    <p><?= $view->escape('mensagem') ?></p>
    <?= $view->assets('scripts') ?>
</body>
</html>
