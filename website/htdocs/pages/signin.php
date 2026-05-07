<?php
if(!function_exists("Path")) return;

if(isset($_SESSION['steamid'])) {
    header('Location: ./skins/');
    exit;
}

$title_num = rand(0, count($translations->login->titles)-1);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="shortcut icon" href="<?= GetPrefix(); ?>src/logo.png" type="image/x-icon">

    <link rel="stylesheet" href="<?= GetPrefix(); ?>css/main.css?v=<?= filemtime('css/main.css'); ?>">
    <link rel="stylesheet" href="<?= GetPrefix(); ?>css/layout.css?v=<?= filemtime('css/layout.css'); ?>">
    <link rel="stylesheet" href="<?= GetPrefix(); ?>css/login.css?v=<?= filemtime('css/login.css'); ?>">

    <title><?= $translations->website_name; ?> - Login</title>
</head>

<body <?= $bodyStyle ?? "" ?> class="login-page">

    <div id="loading">
        <span></span>
    </div>

    <form 
        class="login-box"
        action="<?= GetPrefix(); ?>authorize" 
        method="POST" 
        onsubmit="document.getElementById('loading').setAttribute('data-loading', true);"
    >
        <div class="login-badge">
            Counter-Strike 2 Server
        </div>

        <h1>
            <a 
                title="<?= $translations->login->titles[$title_num ?? 0]->hover; ?>" 
                href="<?= $translations->login->titles[$title_num ?? 0]->link; ?>"
            >
                CEFREPT SERVERS
            </a>
        </h1>

        <p>
            Faça login com sua Steam para acessar seu loadout,
            escolher skins e conectar ao servidor.
        </p>

        <button class="steam-login" type="submit">
            <?= $translations->login->button; ?>
            🎮
        </button>
    </form>

</body>
</html>