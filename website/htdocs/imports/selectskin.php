<?php

if(!function_exists("Path")) {
    return;
}

/* =========================
   RARIDADE CS2
========================= */

function GetRarityData($skin) {

    static $rarityMap = null;

    if ($rarityMap === null) {
        $file = __DIR__ . '/../src/data/skin_rarities.json';

        if (file_exists($file)) {
            $rarityMap = json_decode(file_get_contents($file), true) ?: [];
        } else {
            $rarityMap = [];
        }
    }

    $paint = (string)($skin->paint ?? 0);

    return $rarityMap[$paint] ?? [
        'name' => 'unknown',
        'color' => '#2a2a2a',
        'order' => 0
    ];
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

/*******************/
/* Selected weapon */
/*******************/

$selectedweapon = Path(1);
$selectedweapon_type = GetWeaponType($selectedweapon);

$selected_team = $_COOKIE['selectedTeam'] ?? null;

$selected_team = strtoupper($selected_team);

$team_id = match($selected_team) {
    'TR' => 2,
    'T' => 2,
    'CT' => 3,
    default => null
};

if(!$selectedweapon_type) {
    header('Location: '.GetPrefix());
    exit;
}

$saved_ct = false;
$saved_t = false;

$current_ct = false;
$current_t = false;

$weapon_info = [];
switch($selectedweapon_type) {
    case 'gloves':
        $state = $pdo->prepare("SELECT * FROM `wp_player_gloves` WHERE `steamid` = ? AND `weapon_defindex` = ?");
        $state->execute([$_SESSION['steamid'], $selectedweapon]);
        $savedgloves = $state->fetchAll();

        foreach($savedgloves as $saved) {
            if($saved['weapon_team'] == 2) {
                $saved_t = $saved;
            }
            if($saved['weapon_team'] == 3) {
                $saved_ct = $saved;
            }
        }

        $state = $pdo->prepare("SELECT * FROM `wp_player_skins` WHERE `steamid` = ? AND `weapon_defindex` = ?");
        $state->execute([$_SESSION['steamid'], $selectedweapon]);
        $savedskins = $state->fetchAll();

        $temp_t = false;
        $temp_ct = false;
        foreach($savedskins as $saved) {
            if($saved['weapon_team'] == 2) {
                $temp_t = $saved;
            }
            if($saved['weapon_team'] == 3) {
                $temp_ct = $saved;
            }
        }

        foreach($gloves as $glove) {
            if(!$temp_t && !$temp_ct) {
                if($glove->weapon_defindex == $selectedweapon) {
                    $current_t = $glove;
                    break;
                }

                continue;
            }

            if($temp_t && $glove->weapon_defindex == $temp_t['weapon_defindex'] && $glove->paint == $temp_t['weapon_paint_id']) {
                $current_t = $glove;
            }
            if($temp_ct && $glove->weapon_defindex == $temp_ct['weapon_defindex'] && $glove->paint == $temp_ct['weapon_paint_id']) {
                $current_ct = $glove;
            }
        }

        $weapon_info['img'] = [];

$current_preview = $team_id === 2 ? $current_t : ($team_id === 3 ? $current_ct : ($current_t ?: $current_ct));

if($current_preview) {
    $weapon_info['name'] = explode(' | ', $current_preview->paint_name)[0];
    $weapon_info['img'] = [$current_preview->image];
        }
        break;
    case 'mvp':
        $state = $pdo->prepare("SELECT * FROM `wp_player_music` WHERE `steamid` = ?");
        $state->execute([$_SESSION['steamid']]);
        $savedmusic = $state->fetchAll();

        foreach($savedmusic as $saved) {
            if($saved['weapon_team'] == 2) {
                $saved_t = $saved;
            }
            if($saved['weapon_team'] == 3) {
                $saved_ct = $saved;
            }
        }

        foreach($songs as $song) {
            if(!$saved_t && !$saved_ct) {
                if($song->id == 0) {$current_t = $song;break;}
                continue;
            }
            if($saved_t && $song->id == $saved_t['music_id']) {
                $current_t = $song;
            }
            if($saved_ct && $song->id == $saved_ct['music_id']) {
                $current_ct = $song;
            }
        }

        $weapon_info['img'] = [];

        if($current_t) {
            $weapon_info['name'] = $current_t->name;
            array_push($weapon_info['img'], $current_t->image);
        }
        if($current_ct) {
            if(!isset($weapon_info['name'])) {
                $weapon_info['name'] = $current_ct->name;
            }
            array_push($weapon_info['img'], $current_ct->image);
        }

        if(empty($weapon_info['name'])) {
            $weapon_info['name'] = $songs[0]->name;
        }
        if(empty($weapon_info['img'])) {
            $weapon_info['img'] = [$songs[0]->image];
        }

        break;
    case 'agents':
        $state = $pdo->prepare("SELECT * FROM `wp_player_agents` WHERE `steamid` = ?");
        $state->execute([$_SESSION['steamid']]);
        $savedagents = $state->fetch();

        if($savedagents && isset($savedagents['agent_t'])) {
            $saved_t = $savedagents['agent_t'];
        }
        if($savedagents && isset($savedagents['agent_ct'])) {
            $saved_ct = $savedagents['agent_ct'];
        }

        foreach($agents as $agent) {
            if($selectedweapon == 'terrorist' && $agent->team != 2 || $selectedweapon == 'counter-terrorist' && $agent->team != 3) {continue;}

            if($selectedweapon == 'terrorist' && !$saved_t && $agent->model == 'default' && $agent->team == 2) {
                $current_t = $agent;
                break;
            }
            if($selectedweapon == 'counter-terrorist' && !$saved_ct && $selectedweapon == 'counter-terrorist' && $agent->model == 'default' && $agent->team == 3) {
                $current_t = $agent;
                break;
            }

            if($selectedweapon == 'terrorist' && $agent->model == $saved_t) {
                $current_t = $agent;
            }else if($selectedweapon == 'counter-terrorist' && $agent->model == $saved_ct) {
                $current_t = $agent;
            }
        }

        if($current_t) {
            $weapon_info['name'] = $current_t->agent_name;
            $weapon_info['img'] = [$current_t->image];
        }

        break;
    default:
        foreach($full_skins as $skin) {
            if($skin->weapon_name != $selectedweapon || $skin->paint != 0) {continue;}

            $current_t = $skin;
            break;
        }

        $state = $pdo->prepare("SELECT * FROM `wp_player_skins` WHERE `steamid` = ? AND `weapon_defindex` = ?");
        $state->execute([$_SESSION['steamid'], $current_t->weapon_defindex]);
        $savedskins = $state->fetchAll();

        $is_knife = $selectedweapon == 'weapon_bayonet' || strpos($selectedweapon, 'knife') != 0;
        if($is_knife) {
            $state = $pdo->prepare("SELECT * FROM `wp_player_knife` WHERE `steamid` = ?");
            $state->execute([$_SESSION['steamid']]);
            $savedknifes = $state->fetchAll();
            
            $knife_t = false;
            $knife_ct = false;
            foreach($savedknifes as $saved) {
                if($saved['weapon_team'] == 2) {
                    $knife_t = $saved;
                }
                if($saved['weapon_team'] == 3) {
                    $knife_ct = $saved;
                }
            }
        }

        foreach($savedskins as $saved) {
            if($saved['weapon_team'] == 2) {
                $saved_t = $saved;
            }
            if($saved['weapon_team'] == 3) {
                $saved_ct = $saved;
            }
        }

        foreach($full_skins as $skin) {
            if($saved_t && $saved_t['weapon_defindex'] == $skin->weapon_defindex && $saved_t['weapon_paint_id'] == $skin->paint) {
                $current_t = $skin;
            }
            if($saved_ct && $saved_ct['weapon_defindex'] == $skin->weapon_defindex && $saved_ct['weapon_paint_id'] == $skin->paint) {
                $current_ct = $skin;
            }
        }

        $weapon_info['img'] = [];

$current_preview = $team_id === 2 ? $current_t : ($team_id === 3 ? $current_ct : ($current_t ?: $current_ct));

if($current_preview) {
    $weapon_info['name'] = explode(' | ', $current_preview->paint_name)[0];
    $weapon_info['img'] = [$current_preview->image];
        }

        break;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="shortcut icon" href="<?= GetPrefix(); ?>src/logo.png" type="image/x-icon">

    <link rel="stylesheet" href="<?= GetPrefix(); ?>css/main.css?v=<?= filemtime('css/main.css'); ?>">
    <link rel="stylesheet" href="<?= GetPrefix(); ?>css/layout.css?v=<?= filemtime('css/layout.css'); ?>">
    <link rel="stylesheet" href="<?= GetPrefix(); ?>css/skins.css?v=<?= filemtime('css/skins.css'); ?>">

<script src="<?= GetPrefix(); ?>js/skins.js?v=<?= filemtime('js/skins.js'); ?>" defer></script>

    <title><?= $translations->website_name; ?> - Skins</title>
</head>
<body <?= $bodyStyle ?? "" ?> class="<?= $selectedweapon_type === 'agents' ? 'page-agents' : ''; ?>">

<div id="loading">
    <span></span>
</div>

<div class="wrapper">

    <!-- 🔙 BOTÃO VOLTAR -->
    <div style="margin: 80px 0 20px;">
        <button onclick="history.back()" class="main-btn btn-yellow">
            ← Voltar para armas
        </button>
    </div>

    <!-- 🎯 HEADER -->
    <div class="container">

        <div style="display:flex; align-items:center; gap:25px; flex-wrap:wrap;">

            <!-- PREVIEW -->
            <div class="skin-preview-box">
                <?php foreach($weapon_info['img'] as $imgsrc): ?>
                    <img src="<?= $imgsrc ?>" loading="lazy">
                <?php endforeach; ?>
            </div>

            <!-- INFO -->
            <div class="skin-header-info">
                <h2><?= $weapon_info['name']; ?></h2>
                <p><?= $translations->skins->selected_weapon->weapon_selected_label; ?></p>

                <div class="server-connect">
    <a href="steam://connect/186.226.49.24:27017?appid=730" class="server-connect-btn">
        🎮 Conectar ao Servidor
    </a>
</div>
            </div>

        </div>

    </div>

    <!-- 🎨 SKINS -->
    <div class="container" style="margin-top:40px;">

        <h3 class="section-title">
            <?= $translations->skins->selected_weapon->select_skin_label; ?>
        </h3>

        <div class="choose">
            <ul class="skins-grid <?= $selectedweapon_type === 'agents' ? 'agents-grid' : ''; ?>">

<?php
/* =========================
   RENDER PADRÃO (REFATORADO)
========================= */

switch($selectedweapon_type) {

    case 'gloves':

    $weapon_gloves = array_filter($gloves, function($glove) use ($selectedweapon) {
        return (string)$glove->weapon_defindex === (string)$selectedweapon;
    });

    usort($weapon_gloves, function($a, $b) {
        $rarityA = GetRarityData($a);
        $rarityB = GetRarityData($b);

        return $rarityB['order'] <=> $rarityA['order'];
    });

    foreach($weapon_gloves as $glove) {
        $selected = false;

if($team_id === 2 && $current_t && (string)$current_t->paint === (string)$glove->paint) {
    $selected = true;
}

if($team_id === 3 && $current_ct && (string)$current_ct->paint === (string)$glove->paint) {
    $selected = true;
}

        $rarityData = GetRarityData($glove);
?>
<li class="skin-item">
    <div class="skin-card <?= $selected ? 'selected' : '' ?>"
         style="--rarity-color: <?= $rarityData['color']; ?>;"
         data-action="weapon_change"
         data-weapon="<?= $selectedweapon; ?>"
         data-defindex="<?= $glove->weapon_defindex; ?>"
         data-paint="<?= (string)$glove->paint; ?>">

        <div class="skin-image">
            <img src="<?= $glove->image; ?>" loading="lazy">
        </div>

        <div class="skin-info">
            <span class="skin-name"><?= explode('|', $glove->paint_name)[1] ?? $glove->paint_name; ?></span>
        </div>

    </div>
</li>
<?php } break; ?>


<?php
case 'mvp':
    foreach($songs as $song) {
        $selected =
            ($saved_t && $saved_t['music_id'] == $song->id) ||
            ($saved_ct && $saved_ct['music_id'] == $song->id);
?>
<li class="skin-item">
    <div class="skin-card <?= $selected ? 'selected' : '' ?>"
         data-action="mvp_change"
         data-id="<?= $song->id ?: 'default'; ?>">

        <div class="skin-image">
            <img src="<?= $song->image; ?>" loading="lazy">
        </div>

        <div class="skin-info">
            <span class="skin-name"><?= $song->name; ?></span>
        </div>

    </div>
</li>
<?php } break; ?>


<?php
case 'agents':
    foreach($agents as $agent) {
        if(($selectedweapon == 'terrorist' && $agent->team != 2) ||
           ($selectedweapon == 'counter-terrorist' && $agent->team != 3)) continue;

        $selected =
            ($saved_t && $saved_t == $agent->model) ||
            ($saved_ct && $saved_ct == $agent->model);
?>
<li class="skin-item">
    <div class="skin-card <?= $selected ? 'selected' : '' ?>"
         data-action="agent_change"
         data-agent="<?= $agent->model; ?>"
         data-team="<?= $agent->team; ?>">

        <div class="skin-image">
            <img src="<?= $agent->image; ?>" loading="lazy">
        </div>

        <div class="skin-info">
            <span class="skin-name"><?= explode('|', $agent->agent_name)[0]; ?></span>
        </div>

    </div>
</li>
<?php } break; ?>


<?php
default:

    $weapon_skins = array_filter($full_skins, function($skin) use ($selectedweapon) {
        return $skin->weapon_name == $selectedweapon;
    });

    usort($weapon_skins, function($a, $b) {
        $rarityA = GetRarityData($a);
        $rarityB = GetRarityData($b);

        return $rarityB['order'] <=> $rarityA['order'];
    });

    foreach($weapon_skins as $skin) {
        $selected = false;

if($team_id === 2 && $saved_t && $saved_t['weapon_paint_id'] == $skin->paint) {
    $selected = true;
}

if($team_id === 3 && $saved_ct && $saved_ct['weapon_paint_id'] == $skin->paint) {
    $selected = true;
}

        $rarityData = GetRarityData($skin);?>
<li class="skin-item">
    <div class="skin-card <?= $selected ? 'selected' : '' ?>"
         style="--rarity-color: <?= $rarityData['color']; ?>;"
         data-action="weapon_change"
         data-weapon="<?= $skin->weapon_name; ?>"
         data-defindex="<?= $skin->weapon_defindex; ?>"
         data-paint="<?= (string)$skin->paint; ?>">

        <div class="skin-image">
            <img src="<?= $skin->image; ?>" loading="lazy">
        </div>

        <div class="skin-info">
            <span class="skin-name"><?= explode('|', $skin->paint_name)[1]; ?></span>
        </div>

    </div>
</li>
<?php } break; } ?>

            </ul>
        </div>

    </div>

</div>

<footer>
    <div class="wrapper">
        <a class="info" href="https://steamcommunity.com/profiles/<?= $_SESSION['steamid']; ?>" target="_blank">
            <img src="<?= $UserInfo->avatarfull ?>">
            <p><?= str_replace('{{name}}', "<strong>$UserInfo->personaname</strong>", $translations->skins->footer->signedin); ?></p>
        </a>

        <div class="credit">
            <p>This website created by CefreptGOD</p>
        </div>

        <div class="actions">
            <button class="main-btn btn-yellow" onclick="ToggleLoading();location.href='<?= GetPrefix(); ?>signout';">
                <?= $translations->skins->footer->sign_out ?>
            </button>
        </div>
    </div>
</footer>

</body>
</html>
