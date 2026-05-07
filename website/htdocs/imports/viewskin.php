<?php

if(!function_exists("Path")) {
    return;
}

/*************/
/* SteamInfo */
/*************/
try {
    $steamApiUserInfo = file_get_contents("http://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/?key=$SteamAPI_KEY&steamids=".$_SESSION['steamid']);
    $UserInfo = json_decode($steamApiUserInfo)->response->players[0];
}catch(Exception $err) {
    header("Refresh:0;");
}

/*************/
/* Languages */
/*************/
$langs = scandir('translation/');
array_shift($langs);
array_shift($langs);

/*****************/
/* WeaponPreview */
/*****************/
$current = false;

$saved_t = false;
$saved_ct = false;

$current_t = false;
$current_ct = false;

$player_skin = false;

$selectedweapon = (string) Path(1);
$selectedpaint  = (string) Path(2);

if($selectedpaint === '' || $selectedpaint === 'default') {
    $selectedpaint = '0';
}

$type = GetWeaponType($selectedweapon);

if(!$type) {
    header("Location: ".GetPrefix()."skins/");
    exit;
}

/* =========================
   CARREGA ITEM ATUAL
========================= */

if($type === 'gloves') {

    foreach($gloves as $glove) {
        if(
            (string)$glove->weapon_defindex === $selectedweapon &&
            (string)$glove->paint === $selectedpaint
        ) {
            $current = $glove;
            break;
        }
    }

} else if($type === 'agents') {

    $team = $selectedweapon === 'terrorist' ? 2 : 3;
    $modelagent = str_replace('-', '/', $selectedpaint);

    foreach($agents as $agent) {
        if(
            (int)$agent->team === $team &&
            (string)$agent->model === $modelagent
        ) {
            $current = $agent;
            break;
        }
    }

} else if($type === 'mvp') {

    foreach($songs as $song) {
        if((string)$song->id === $selectedpaint) {
            $current = $song;
            break;
        }
    }

} else {

    foreach($full_skins as $skin) {
        if(
            (string)$skin->weapon_name === $selectedweapon &&
            (string)$skin->paint === $selectedpaint
        ) {
            $current = $skin;
            break;
        }
    }
}

if(!$current) {
    header("Location: ".GetPrefix()."skins/".$selectedweapon."/");
    exit;
}

/* =========================
   DADOS DO WEAPON
========================= */

switch($type) {

    case 'gloves':

        $weapon = [
            'name' => $current->paint_name,
            'index' => $current->weapon_defindex,
            'paint' => $current->paint,
            'weapon_name' => $current->weapon_defindex,
            'img' => $current->image
        ];

        $query = $pdo->prepare("SELECT * FROM `wp_player_skins` WHERE `steamid` = ? AND `weapon_defindex` = ? AND `weapon_paint_id` = ?");
        $query->execute([$_SESSION['steamid'], $current->weapon_defindex, $current->paint]);
        $player_skins = $query->fetchAll();

        foreach($player_skins as $skin) {
            if($skin['weapon_team'] == 2) {
                $saved_t = $skin;
            }
            if($skin['weapon_team'] == 3) {
                $saved_ct = $skin;
            }
        }

        $player_skin = $saved_t ?: $saved_ct;

        $weapon['t'] = $saved_t ? true : false;
        $weapon['ct'] = $saved_ct ? true : false;

        break;

    case 'agents':

        $weapon = [
            'name' => $current->agent_name,
            'index' => $current->model,
            'paint' => $selectedpaint,
            'weapon_name' => $selectedweapon,
            'img' => $current->image
        ];

        $query = $pdo->prepare("SELECT * FROM `wp_player_agents` WHERE `steamid` = ?");
        $query->execute([$_SESSION['steamid']]);
        $savedagent = $query->fetch();

        if($current->team == 2) {
            $weapon['t'] = ($savedagent && $savedagent['agent_t'] == $current->model);
        }

        if($current->team == 3) {
            $weapon['ct'] = ($savedagent && $savedagent['agent_ct'] == $current->model);
        }

        break;

    case 'mvp':

        $weapon = [
            'name' => $current->name,
            'index' => $current->id,
            'paint' => $current->id,
            'weapon_name' => 'mvp',
            'img' => $current->image
        ];

        $query = $pdo->prepare("SELECT * FROM `wp_player_music` WHERE `steamid` = ? AND `music_id` = ?");
        $query->execute([$_SESSION['steamid'], $current->id]);
        $results = $query->fetchAll();

        $weapon['t'] = false;
        $weapon['ct'] = false;

        foreach($results as $res) {
            if($res['weapon_team'] == 2) $weapon['t'] = true;
            if($res['weapon_team'] == 3) $weapon['ct'] = true;
        }

        break;

    default:

        $weapon = [
            'name' => $current->paint_name,
            'index' => $current->weapon_defindex,
            'paint' => $current->paint,
            'weapon_name' => $current->weapon_name,
            'img' => $current->image
        ];

        $query = $pdo->prepare("SELECT * FROM `wp_player_skins` WHERE `steamid` = ? AND `weapon_defindex` = ? AND `weapon_paint_id` = ?");
        $query->execute([$_SESSION['steamid'], $current->weapon_defindex, $current->paint]);
        $player_skins = $query->fetchAll();

        if(!in_array($current->weapon_name, $ct_only)) {
            $weapon['t'] = false;
        }

        if(!in_array($current->weapon_name, $t_only)) {
            $weapon['ct'] = false;
        }

        foreach($player_skins as $skin) {
            if($skin['weapon_team'] == 2) {
                $saved_t = $skin;
                $weapon['t'] = true;
            }

            if($skin['weapon_team'] == 3) {
                $saved_ct = $skin;
                $weapon['ct'] = true;
            }
        }

        $player_skin = $saved_t ?: $saved_ct;

        break;
}

/* =========================
   CUSTOM WEAR
========================= */

if(
    $player_skin &&
    isset($player_skin['weapon_wear']) &&
    !in_array((float)$player_skin['weapon_wear'], [0, 0.07, 0.15, 0.38, 0.45])
) {
    $custom_wear = $player_skin['weapon_wear'];
}

/* =========================
   STICKERS / KEYCHAIN
========================= */

$stickers_loop = false;

if($player_skin) {

    for($i = 0; $i <= 4; $i++) {
        if(!empty($player_skin["weapon_sticker_$i"])) {
            ${"sticker$i"} = explode(';', $player_skin["weapon_sticker_$i"]);
            $stickers_loop = true;
        }
    }

    if(!empty($player_skin['weapon_keychain'])) {
        $keychain0 = explode(';', $player_skin['weapon_keychain']);

        foreach($keychains as $keychain) {
            if($keychain->id == $keychain0[0]) {
                $keychain0_info = $keychain;
                break;
            }
        }
    }
}

if($stickers_loop) {
    foreach($stickers as $sticker) {
        for($i = 0; $i <= 4; $i++) {
            if(isset(${"sticker$i"}) && $sticker->id == ${"sticker$i"}[0]) {
                ${"sticker{$i}_info"} = $sticker;
            }
        }
    }
}

if(isset($_GET['sticker_slot'], $_GET['sticker'])) {
    $slot = (int)$_GET['sticker_slot'];
    $stickerId = $_GET['sticker'];

    if($slot >= 0 && $slot <= 4) {
        ${"sticker$slot"} = [$stickerId];

        foreach($stickers as $sticker) {
            if((string)$sticker->id === (string)$stickerId) {
                ${"sticker{$slot}_info"} = $sticker;
                break;
            }
        }
    }
}

if(isset($_GET['keychain'])) {
    $keychainId = $_GET['keychain'];
    $keychain0 = [$keychainId];

    foreach($keychains as $keychain) {
        if((string)$keychain->id === (string)$keychainId) {
            $keychain0_info = $keychain;
            break;
        }
    }
}

$useThree = isset($Website_UseThreejs) && $Website_UseThreejs ? 'true' : 'false';
$legacy = !empty($current->legacy_model) ? 'true' : 'false';

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link rel="shortcut icon" href="<?= GetPrefix(); ?>src/logo.png">

<link rel="stylesheet" href="<?= GetPrefix(); ?>css/main.css?v=<?= filemtime('css/main.css'); ?>">
<link rel="stylesheet" href="<?= GetPrefix(); ?>css/skins.css?v=<?= filemtime('css/skins.css'); ?>">
<link rel="stylesheet" href="<?= GetPrefix(); ?>css/view.css?v=<?= filemtime('css/view.css'); ?>">
<link rel="stylesheet" href="<?= GetPrefix(); ?>css/layout.css?v=<?= filemtime('css/layout.css'); ?>">

<script src="<?= GetPrefix(); ?>js/skins.js?v=<?= filemtime('js/skins.js'); ?>" defer></script>

<script type="importmap">
{
 "imports": {
   "three": "https://cdn.jsdelivr.net/npm/three@0.170.0/build/three.module.js",
   "three/addons/": "https://cdn.jsdelivr.net/npm/three@0.170.0/examples/jsm/"
 }
}
</script>

<script>
let usethreejs = <?= $useThree; ?>;
let modelpath = false;
let texturepath = false;
let legacy = <?= $legacy; ?>;

const Prefix = "<?= GetPrefix(); ?>";
const currenttype = "<?= $type; ?>";

let saved_t = `<?= $saved_t ? json_encode($saved_t) : '0'; ?>`;
let saved_ct = `<?= $saved_ct ? json_encode($saved_ct) : '0'; ?>`;
</script>

<script src="<?= GetPrefix(); ?>js/weaponviewer.js" type="module" defer></script>

<title><?= $translations->website_name; ?> - View Skin</title>
</head>

<body <?= $bodyStyle ?? "" ?>>

<div id="loading"><span></span></div>
<div id="messages"></div>

<div class="wrapper mainbox"
     data-index="<?= $weapon['index']; ?>"
     data-paint="<?= $weapon['paint']; ?>"
     data-name="<?= $weapon['weapon_name']; ?>">


   <div style="margin:80px 0 20px;display:flex;gap:12px;flex-wrap:wrap;">

    <button
        onclick="history.back()"
        class="main-btn btn-yellow"
    >
        ← Voltar para skins
    </button>

    <button
        onclick="window.location.href='<?= GetPrefix(); ?>skins/'"
        class="main-btn btn-dark"
    >
        🎯 Voltar para Loadout
    </button>

</div>

    <div class="container">
        <div style="display:flex;gap:20px;flex-wrap:wrap;align-items:center;">

            <div class="preview" style="background:#111;padding:15px;border-radius:12px;">
                <img src="<?= $weapon['img']; ?>" style="height:70px;">
            </div>

            <div>
                <h2><?= $weapon['name']; ?></h2>
                <p>Skin selecionada</p>

                <a href="steam://connect/186.226.49.24:27017?appid=730" class="main-btn btn-dark">
                    🎮 Clique aqui para atualizar skin no servidor ou digite !wp no chat
                </a>
            </div>

        </div>
    </div>

    <div class="container" style="margin-top:40px;">
        <div style="display:flex;gap:30px;flex-wrap:wrap;">

            <div style="flex:2;min-width:300px;">
                <div style="background:#111;border-radius:12px;padding:20px;min-height:400px;display:flex;align-items:center;justify-content:center;">
                    <div id="threejsArea"></div>

                    <div id="previewImg">
                        <img src="<?= $weapon['img']; ?>">
                    </div>
                </div>
            </div>

            <div id="settings" style="flex:1;min-width:280px;">
                <div style="background:#111;border-radius:12px;padding:20px;">

                    <div class="title">
                        <span>⚙️ Configurar</span>
                    </div>

                    <div class="input" id="preset" <?= (!$saved_t || !$saved_ct || $type == 'agents' || $type == 'mvp') ? 'style="display:none;"' : ''; ?>>
                        <label>Time</label>
                        <div class="marks">
                            <input type="radio" name="preset" class="terrormark" checked>
                            <input type="radio" name="preset" class="counterterrormark">
                        </div>
                    </div>

                    <?php if($type != 'agents' && $type != 'mvp' && $selectedpaint !== '0') { ?>

                    <div class="input" id="wear">
                        <label>Desgaste</label>
                        <select>
                            <option value="0" <?= $player_skin && $player_skin['weapon_wear'] == 0 ? 'selected' : ''; ?>>Factory New</option>
                            <option value="0.07" <?= $player_skin && $player_skin['weapon_wear'] == 0.07 ? 'selected' : ''; ?>>Minimal Wear</option>
                            <option value="0.15" <?= $player_skin && $player_skin['weapon_wear'] == 0.15 ? 'selected' : ''; ?>>Field-Tested</option>
                            <option value="0.38" <?= $player_skin && $player_skin['weapon_wear'] == 0.38 ? 'selected' : ''; ?>>Well-Worn</option>
                            <option value="0.45" <?= $player_skin && $player_skin['weapon_wear'] == 0.45 ? 'selected' : ''; ?>>Battle-Scarred</option>
                            <option value="custom" <?= isset($custom_wear) ? 'selected' : ''; ?>>Custom</option>
                        </select>

                        <input type="range"
                               id="customwear"
                               min="0"
                               max="0.99"
                               step="0.01"
                               value="<?= isset($custom_wear) ? $custom_wear : '0'; ?>"
                               data-val="<?= isset($custom_wear) ? $custom_wear : '0'; ?>"
                               oninput="this.style.setProperty('--fill-precent', `${this.value / this.max * 100}%`);this.dataset.val = this.value;"
                               style="--fill-precent: <?= isset($custom_wear) ? (($custom_wear / 1) * 100).'%' : '0%'; ?>;">
                    </div>

                    <div class="input" id="seed">
                        <label>Seed</label>
                        <div class="box">
                            <input type="checkbox"
                                   oninput="this.checked?this.nextElementSibling.disabled=false:this.nextElementSibling.disabled=true;"
                                   <?= ($player_skin['weapon_seed'] ?? 0) > 0 ? 'checked' : ''; ?>>

                            <input type="number"
                                   placeholder="1 - 1000"
                                   min="1"
                                   max="1000"
                                   <?= ($player_skin['weapon_seed'] ?? 0) > 0 ? "value='".$player_skin['weapon_seed']."'" : 'disabled'; ?>>
                        </div>
                    </div>

                    <div class="input" id="nametag">
                        <label>Nome</label>
                        <div class="box">
                            <input type="checkbox"
                                   oninput="this.checked?this.nextElementSibling.disabled=false:this.nextElementSibling.disabled=true;"
                                   <?= !is_null($player_skin['weapon_nametag'] ?? null) ? 'checked' : ''; ?>>

                            <input type="text"
                                   placeholder="Nome da arma"
                                   <?= ($player_skin && !is_null($player_skin['weapon_nametag'])) ? 'value="'.$player_skin['weapon_nametag'].'"' : 'disabled'; ?>>
                        </div>
                    </div>

                    <?php if($type != 'gloves') { ?>

                    <div class="box" id="stattrak">
                        <div class="input">
                            <label>StatTrak</label>
                            <div class="box">
                                <input type="checkbox"
                                       oninput="this.checked?document.querySelector('#stattrak p').style.opacity = 1:document.querySelector('#stattrak p').style.opacity = 0.3;"
                                       <?= ($player_skin['weapon_stattrak'] ?? false) ? 'checked' : ''; ?>>
                            </div>
                        </div>

                        <div class="input">
                            <label>Kills</label>
                            <p <?= ($player_skin['weapon_stattrak'] ?? false) ? 'style="opacity:1;color:white;"' : ''; ?>>
                                <?= ($player_skin['weapon_stattrak'] ?? false) ? ($player_skin['weapon_stattrak_count'] ?? 0) : '/'; ?>
                            </p>
                        </div>
                    </div>

                    <?php if($type != 'knifes') { ?>

                    <div class="addons">
    <div class="box-col">
        <label>Stickers</label>

        <div class="stickers">
            <?php for($i = 0; $i <= 4; $i++): ?>
                <?php
                    $hasSticker = isset(${"sticker{$i}_info"});
                    $stickerId = $hasSticker ? ${"sticker$i"}[0] : '';
                    $stickerName = $hasSticker ? ${"sticker{$i}_info"}->name : '';
                    $stickerImage = $hasSticker ? ${"sticker{$i}_info"}->image : '';
                ?>

                <button
                    type="button"
                    data-action="sticker_select"
                    data-slot="<?= $i; ?>"
                    <?= $hasSticker ? 'data-id="'.htmlspecialchars($stickerId).'" title="'.htmlspecialchars($stickerName).'"' : ''; ?>
                >
                    <?php if($hasSticker): ?>
                        <img src="<?= htmlspecialchars($stickerImage); ?>" loading="lazy">
                    <?php else: ?>
                        +
                    <?php endif; ?>
                </button>
            <?php endfor; ?>
        </div>
    </div>

    <div class="box-col">
        <label>Keychain</label>

        <div class="keychains">
            <?php
                $hasKeychain = isset($keychain0_info);
                $keychainId = $hasKeychain ? $keychain0[0] : '';
                $keychainName = $hasKeychain ? $keychain0_info->name : '';
                $keychainImage = $hasKeychain ? $keychain0_info->image : '';
            ?>

            <button
                type="button"
                data-action="keychain_select"
                <?= $hasKeychain ? 'data-id="'.htmlspecialchars($keychainId).'" title="'.htmlspecialchars($keychainName).'"' : ''; ?>
            >
                <?php if($hasKeychain): ?>
                    <img src="<?= htmlspecialchars($keychainImage); ?>" loading="lazy">
                <?php else: ?>
                    +
                <?php endif; ?>
            </button>
        </div>
    </div>
</div>

                    <?php } ?>
                    <?php } ?>
                    <?php } ?>

                    <div class="apply">
                        <?php if(isset($weapon['t'])) { ?>
                            <button class="main-btn terror<?= $weapon['t'] ? ' applied' : ''; ?>">
                                Aplicar T
                            </button>
                        <?php } ?>

                        <?php if(isset($weapon['ct'])) { ?>
                            <button class="main-btn counter-terror<?= $weapon['ct'] ? ' applied' : ''; ?>">
                                Aplicar CT
                            </button>
                        <?php } ?>
                    </div>

                </div>
            </div>

        </div>
    </div>

<?php if($type != 'agents' && $type != 'mvp' && $type != 'gloves' && $type != 'knifes' && $selectedpaint !== '0'): ?>

<div class="container addon-picker-section" data-picker="stickers">
    <div class="addon-picker-header">
        <h2>Escolha um sticker</h2>
        <p>Clique em um slot "+" e selecione o adesivo desejado.</p>
    </div>

    <div class="addon-picker-grid">
        <?php foreach($stickers as $sticker): ?>
            <button
                type="button"
                class="addon-picker-card"
                data-action="sticker_change"
                data-sticker="<?= htmlspecialchars($sticker->id); ?>"
            >
                <img src="<?= htmlspecialchars($sticker->image); ?>" loading="lazy">
                <span><?= htmlspecialchars($sticker->name); ?></span>
            </button>
        <?php endforeach; ?>
    </div>
</div>

<div class="container addon-picker-section" data-picker="keychains">
    <div class="addon-picker-header">
        <h2>Escolha um chaveiro</h2>
        <p>Selecione o chaveiro desejado.</p>
    </div>

    <div class="addon-picker-grid">
        <?php foreach($keychains as $keychain): ?>
            <button
                type="button"
                class="addon-picker-card"
                data-action="keychain_change"
                data-keychain="<?= htmlspecialchars($keychain->id); ?>"
            >
                <img src="<?= htmlspecialchars($keychain->image); ?>" loading="lazy">
                <span><?= htmlspecialchars($keychain->name); ?></span>
            </button>
        <?php endforeach; ?>
    </div>
</div>

<?php endif; ?>

</div>

<footer>
    <div class="wrapper">

        <a class="info" href="https://steamcommunity.com/profiles/<?= $_SESSION['steamid']; ?>" target="_blank">
            <img src="<?= $UserInfo->avatarfull ?>">
            <p><?= $UserInfo->personaname; ?></p>
        </a>

        <div class="credit">
            <p>Website by CefreptGOD</p>
        </div>

        <div class="actions">
            <button class="main-btn btn-yellow" onclick="location.href='<?= GetPrefix(); ?>signout';">
                Sair
            </button>
        </div>

    </div>
</footer>

</body>
</html>

