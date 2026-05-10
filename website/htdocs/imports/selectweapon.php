<?php

if(!function_exists("Path")) return;

/* =========================
   STEAM INFO
========================= */
try {
    $steamApiUserInfo = file_get_contents(
        "https://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/?key=$SteamAPI_KEY&steamids=".$_SESSION['steamid']
    );
    $UserInfo = json_decode($steamApiUserInfo)->response->players[0];
} catch(Exception $e) {
    header("Refresh:0;");
}

/* =========================
   LANGS
========================= */
$langs = array_values(array_diff(scandir('translation/'), ['.', '..']));

/* =========================
   CATEGORIES
========================= */
$weapon_type_select = [
    "knifes" => '',
    "pistols" => '',
    "rifles" => '',
    "smg" => '',
    "machine_guns" => '',
    "sniper_rifles" => '',
    "shotguns" => '',
    "gloves" => '',
    "agents" => '',
    "mvp" => ''
];

if($Website_UseCategories) {

    if(isset($_POST['category'])) {
        $_SESSION['category'] = $_POST['category'] !== 'none' ? $_POST['category'] : null;

        echo "<script>history.replaceState(null,null,location.href);</script>";
        unset($_POST['category']);
    }

    $category = $_SESSION['category'] ?? null;

    if($category) {
        $weapon_type_select[$category] = "class='selected'";
    }

    $choose_translate = str_replace(
        '{{label}}',
        $category ? $translations->skins->categories->{$category} : $translations->skins->choose_weapon_default,
        $translations->skins->choose_weapon
    );
}

/* =========================
   PLAYER DATA
========================= */
$state = $pdo->prepare("SELECT * FROM wp_player_skins WHERE steamid = ?");
$state->execute([$_SESSION['steamid']]);
$results = $state->fetchAll();

$state = $pdo->prepare("SELECT * FROM wp_player_knife WHERE steamid = ?");
$state->execute([$_SESSION['steamid']]);
$savedknifes = $state->fetchAll();

$state = $pdo->prepare("SELECT * FROM wp_player_gloves WHERE steamid = ?");
$state->execute([$_SESSION['steamid']]);
$savedgloves = $state->fetchAll();

$state = $pdo->prepare("SELECT * FROM wp_player_agents WHERE steamid = ?");
$state->execute([$_SESSION['steamid']]);
$savedagents = $state->fetch();

$state = $pdo->prepare("SELECT * FROM wp_player_music WHERE steamid = ?");
$state->execute([$_SESSION['steamid']]);
$savedmusic = $state->fetchAll();

/* =========================
   KNIFES
========================= */
$knife_t = null;
$knife_ct = null;

foreach($savedknifes as $k) {
    if($k['weapon_team'] == 2) $knife_t = $k;
    if($k['weapon_team'] == 3) $knife_ct = $k;
}

/* =========================
   GLOVES
========================= */
$gloves_selected = ['t' => null, 'ct' => null];

foreach($savedgloves as $g) {
    if($g['weapon_team'] == 2) $gloves_selected['t'] = $g['weapon_defindex'];
    if($g['weapon_team'] == 3) $gloves_selected['ct'] = $g['weapon_defindex'];
}

/* =========================
   MUSIC
========================= */
$music_t = null;
$music_ct = null;

foreach($savedmusic as $saved) {
    foreach($songs as $song) {
        if($song->id == $saved['music_id']) {
            if($saved['weapon_team'] == 2) $music_t = $song;
            if($saved['weapon_team'] == 3) $music_ct = $song;
        }
    }
}

/* =========================
   TEAM FILTER
========================= */
$selected_team = strtoupper(Path(1) ?? '');

$selected_team = match($selected_team) {
    'TR' => 'T',
    'CT' => 'CT',
    default => null
};

$selected_team = $selected_team ?: 'ALL';

/* =========================
   SAVED SKINS MAP
========================= */
$savedskins = [];
$savedskins_info = [];

foreach($results as $saved) {
    if(!isset($savedskins[$saved['weapon_defindex']])) {
        $savedskins[$saved['weapon_defindex']] = [];
        $savedskins_info[$saved['weapon_defindex']] = [];
    }

    $savedskins[$saved['weapon_defindex']][] = $saved;

    if(GetWeaponType($saved['weapon_defindex']) == 'gloves') {
        foreach($gloves as $glove) {
            if($glove->weapon_defindex == $saved['weapon_defindex'] && $glove->paint == $saved['weapon_paint_id']) {
                $savedskins_info[$saved['weapon_defindex']][] = $glove;
                break;
            }
        }
    } else {
        foreach($full_skins as $skin) {
            if($skin->weapon_defindex == $saved['weapon_defindex'] && $skin->paint == $saved['weapon_paint_id']) {
                $savedskins_info[$saved['weapon_defindex']][] = $skin;
                break;
            }
        }
    }
}

/* =========================
   CATEGORY DATA
========================= */
$category_names = [
    'pistols' => '🔫 Pistolas',
    'rifles' => '🎯 Rifles',
    'smg' => '⚡ SMGs',
    'machine_guns' => '💣 Metralhadoras',
    'sniper_rifles' => '🎯 Snipers',
    'shotguns' => '💥 Shotguns',
    'knifes' => '🔪 Facas',
    'gloves' => '🧤 Luvas',
    'agents' => '🕵️ Agentes',
    'mvp' => '🎵 MVP'
];

$category_order = [
    'pistols',
    'rifles',
    'smg',
    'machine_guns',
    'sniper_rifles',
    'shotguns',
    'knifes'
];

$grouped_weapons = [];
$shownskins = [];

/* =========================
   GROUP WEAPONS
========================= */
foreach($full_skins as $skin) {

    if($skin->weapon_name === 'weapon_knife_default') {
        continue;
    }

    if($selected_team !== 'ALL') {
        if($selected_team === 'CT' && in_array($skin->weapon_name, $t_only)) {
            continue;
        }

        if($selected_team === 'T' && in_array($skin->weapon_name, $ct_only)) {
            continue;
        }
    }

    $weapon_category = GetWeaponType($skin->weapon_name);

    if($Website_UseCategories && isset($_SESSION['category']) && $weapon_category != $_SESSION['category']) {
        continue;
    }

    if(in_array($skin->weapon_name, $shownskins)) {
        continue;
    }

    $shownskins[] = $skin->weapon_name;

    if(!isset($grouped_weapons[$weapon_category])) {
        $grouped_weapons[$weapon_category] = [];
    }

    $grouped_weapons[$weapon_category][] = $skin;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="shortcut icon" href="<?= GetPrefix(); ?>src/logo.png">

   <link rel="stylesheet" href="<?= GetPrefix(); ?>css/main.css?v=<?= filemtime('css/main.css'); ?>">
    <link rel="stylesheet" href="<?= GetPrefix(); ?>css/skins.css?v=<?= filemtime('css/skins.css'); ?>">
    <link rel="stylesheet" href="<?= GetPrefix(); ?>css/layout.css?v=<?= filemtime('css/layout.css'); ?>">

    <script src="<?= GetPrefix(); ?>js/skins.js?v=<?= filemtime('js/skins.js'); ?>" defer></script>

    <title><?= $translations->website_name; ?> - Skins</title>
</head>

<body class="selectweapon-page" <?= $bodyStyle ?? "" ?>>

<div id="team-screen">
    <div class="team-title">
        <h1>Selecione seu Loadout</h1>
        <p>Escolha entre Terrorista (TR) ou Contra-Terrorista (CT)</p>
    </div>

    <div class="team-buttons">
        <button class="team-btn tr" data-action="team_select" data-team="TR">
            <img src="https://app.skin.land/blogfiles/Yu13yzLHwqyq3vQDFEwB6FeSr3A0AhXqz6jIqTWE.png">
            <span>Terrorista</span>
        </button>

        <button class="team-btn ct" data-action="team_select" data-team="CT">
            <img src="https://app.skin.land/blogfiles/dpKrvrXM82b2kNxfr93mRwUk5wvhVdGTQfucPtiq.png">
            <span>Contra-Terrorista</span>
        </button>
    </div>
</div>

<div class="server-connect">
    <a href="steam://connect/186.226.49.24:27017?appid=730" class="server-connect-btn">
        🎮 Conectar no Server
    </a>
</div>

<button id="back-to-team" data-action="team_back">
    <span class="arrow">←</span>
    <span>Seleção de Loadout</span>
</button>

<div id="loading">
    <span></span>
</div>

<div class="wrapper">

    <?php if($Website_UseCategories) { ?>
    <header class="categories">
        <ul>
            <li><button data-action="category" data-category="agents" <?= $weapon_type_select['agents']; ?>><span class="helpbox"><?= $translations->skins->categories->agents->label ?? 'Agents'; ?></span></button></li>
            <li><button data-action="category" data-category="gloves" <?= $weapon_type_select['gloves']; ?>><span class="helpbox"><?= $translations->skins->categories->gloves; ?></span></button></li>
            <li><button data-action="category" data-category="knifes" <?= $weapon_type_select['knifes']; ?>><span class="helpbox"><?= $translations->skins->categories->knifes; ?></span></button></li>
            <li><button data-action="category" data-category="pistols" <?= $weapon_type_select['pistols']; ?>><span class="helpbox"><?= $translations->skins->categories->pistols; ?></span></button></li>
            <li><button data-action="category" data-category="rifles" <?= $weapon_type_select['rifles']; ?>><span class="helpbox"><?= $translations->skins->categories->rifles; ?></span></button></li>
            <li><button data-action="category" data-category="smg" <?= $weapon_type_select['smg']; ?>><span class="helpbox"><?= $translations->skins->categories->smg; ?></span></button></li>
            <li><button data-action="category" data-category="machine_guns" <?= $weapon_type_select['machine_guns']; ?>><span class="helpbox"><?= $translations->skins->categories->machine_guns; ?></span></button></li>
            <li><button data-action="category" data-category="sniper_rifles" <?= $weapon_type_select['sniper_rifles']; ?>><span class="helpbox"><?= $translations->skins->categories->sniper_rifles; ?></span></button></li>
            <li><button data-action="category" data-category="shotguns" <?= $weapon_type_select['shotguns']; ?>><span class="helpbox"><?= $translations->skins->categories->shotguns; ?></span></button></li>
            <h2><?= $choose_translate; ?></h2>
        </ul>
    </header>
    <?php } ?>

    <div class="container" <?= $Website_UseCategories ? 'style="margin-top: 100px;"' : '' ?>>
        <div class="choose">
            <div class="weapon-tabs">
              <button type="button" class="weapon-tab" data-tab="loadout">
    🎒 Loadout Atual
</button>

    <?php foreach($category_order as $category): ?>
        <?php if(!empty($grouped_weapons[$category])): ?>
            <button type="button" class="weapon-tab" data-tab="<?= $category; ?>">
                <?= $category_names[$category]; ?>
            </button>
        <?php endif; ?>
    <?php endforeach; ?>

    <button type="button" class="weapon-tab" data-tab="gloves">
        <?= $category_names['gloves']; ?>
    </button>

    <button type="button" class="weapon-tab" data-tab="agents">
        <?= $category_names['agents']; ?>
    </button>

    <button type="button" class="weapon-tab" data-tab="mvp">
        <?= $category_names['mvp']; ?>
    </button>
</div>

<ul class="weapon-grid-tabs">

<li class="weapon-tab-item tab-loadout loadout-panel">
    <?php
    $team_id = null;

    if($selected_team === 'T') {
        $team_id = 2;
    } elseif($selected_team === 'CT') {
        $team_id = 3;
    }

    $equipped = [];

    foreach($savedskins as $weaponDef => $savedItems) {
        foreach($savedItems as $index => $savedItem) {

            if($team_id !== null && (int)$savedItem['weapon_team'] !== $team_id) {
                continue;
            }

            if(!isset($savedskins_info[$weaponDef][$index])) {
                continue;
            }

            $item = $savedskins_info[$weaponDef][$index];
            $equipped[$weaponDef] = $item;
        }
    }

    $currentKnife = $team_id === 2 ? ($knife_t['knife'] ?? null) : ($knife_ct['knife'] ?? null);

    if($currentKnife) {
        foreach($equipped as $weaponDef => $item) {
            if(GetWeaponType($item->weapon_name ?? '') === 'knifes' && ($item->weapon_name ?? '') !== $currentKnife) {
                unset($equipped[$weaponDef]);
            }
        }
    }

    $currentGlove = $team_id === 2 ? ($gloves_selected['t'] ?? null) : ($gloves_selected['ct'] ?? null);

    if($currentGlove) {
        foreach($equipped as $weaponDef => $item) {
            if(GetWeaponType($weaponDef) === 'gloves' && (string)$weaponDef !== (string)$currentGlove) {
                unset($equipped[$weaponDef]);
            }
        }
    }

    $loadout_order = [
        'pistols' => 1,
        'rifles' => 2,
        'sniper_rifles' => 3,
        'smg' => 4,
        'shotguns' => 5,
        'machine_guns' => 6,
        'knifes' => 7,
        'gloves' => 8,
        'agents' => 9,
        'mvp' => 10
    ];

    uasort($equipped, function($a, $b) use ($loadout_order) {
        $typeA = GetWeaponType($a->weapon_name ?? $a->weapon_defindex ?? '');
        $typeB = GetWeaponType($b->weapon_name ?? $b->weapon_defindex ?? '');

        return ($loadout_order[$typeA] ?? 99) <=> ($loadout_order[$typeB] ?? 99);
    });

    $teamLabel = $selected_team === 'T' ? 'TR' : 'CT';

$currentAgentModel = null;

if($team_id === 2) {
    $currentAgentModel = $savedagents['agent_t'] ?? null;
} elseif($team_id === 3) {
    $currentAgentModel = $savedagents['agent_ct'] ?? null;
}

$currentAgentInfo = null;

foreach($agents as $agent) {
    if($currentAgentModel && $agent->model === $currentAgentModel) {
        $currentAgentInfo = $agent;
        break;
    }
}

$defaultTeamImage = $selected_team === 'T'
    ? 'https://app.skin.land/blogfiles/Yu13yzLHwqyq3vQDFEwB6FeSr3A0AhXqz6jIqTWE.png'
    : 'https://app.skin.land/blogfiles/dpKrvrXM82b2kNxfr93mRwUk5wvhVdGTQfucPtiq.png';

$agentImage = !empty($currentAgentInfo->image) ? $currentAgentInfo->image : $defaultTeamImage;

$agentName = $currentAgentInfo->agent_name ?? "Agente padrão {$teamLabel}";
    ?>

    <div class="loadout-layout cs2-loadout">

        <div class="cs2-character-panel">
            <div class="cs2-team-badge">
    <?= $agentName; ?>
</div>

<img class="cs2-character"
     src="<?= $agentImage; ?>"
     alt="<?= $agentName; ?>">
        </div>

        <div class="cs2-loadout-content">
            <h2>Loadout Atual <?= $teamLabel; ?></h2>

            <div class="loadout-grid">
                <?php foreach($equipped as $weaponDef => $item): ?>
                    <button class="loadout-card"
                            data-action="weapon_picked"
                            data-weapon="<?= $item->weapon_name ?? $weaponDef; ?>">

                        <div class="loadout-card-image">
                            <img src="<?= $item->image; ?>" loading="lazy">
                        </div>

                        <div class="loadout-card-info">
                            <strong><?= explode(' | ', $item->paint_name ?? 'Skin')[0]; ?></strong>
                            <span><?= explode(' | ', $item->paint_name ?? 'Skin')[1] ?? ''; ?></span>
                        </div>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>

    </div>
</li>

<?php foreach($category_order as $category): ?>
    <?php if(empty($grouped_weapons[$category])) continue; ?>

    <?php foreach($grouped_weapons[$category] as $skin): ?>
        <li class="weapon-tab-item tab-<?= $category; ?>">
            <button class="card"
                    data-action="weapon_picked"
                    data-weapon="<?= $skin->weapon_name; ?>">

                <div class="imgbox">
                    <?php if(isset($savedskins_info[$skin->weapon_defindex])): ?>
                        <?php
$team_id = null;

if($selected_team === 'T') {
    $team_id = 2;
} elseif($selected_team === 'CT') {
    $team_id = 3;
}

$equippedSkin = null;

if(isset($savedskins[$skin->weapon_defindex])) {
    foreach($savedskins[$skin->weapon_defindex] as $index => $savedItem) {
        if($team_id !== null && (int)$savedItem['weapon_team'] !== $team_id) {
            continue;
        }

        if(isset($savedskins_info[$skin->weapon_defindex][$index])) {
            $equippedSkin = $savedskins_info[$skin->weapon_defindex][$index];
            break;
        }
    }
}
?>

<?php if($equippedSkin): ?>
    <img src="<?= $equippedSkin->image; ?>" loading="lazy">
<?php else: ?>
    <img src="<?= $skin->image; ?>" loading="lazy">
<?php endif; ?>
                    <?php else: ?>
                        <img src="<?= $skin->image; ?>" loading="lazy">
                    <?php endif; ?>
                </div>

                <span><?= explode(' | ', $skin->paint_name)[0]; ?></span>
                <div class="marks"></div>
            </button>
        </li>
    <?php endforeach; ?>
<?php endforeach; ?>

<?php
$showngloves = [];

foreach($gloves as $glove):
    if($glove->weapon_defindex === 'gloves_default') continue;
    if(in_array($glove->weapon_defindex, $showngloves)) continue;

    $showngloves[] = $glove->weapon_defindex;
?>
    <li class="weapon-tab-item tab-gloves">
        <button class="card"
                data-action="weapon_picked"
                data-weapon="<?= $glove->weapon_defindex; ?>">

            <div class="imgbox">
                <img src="<?= $glove->image; ?>" loading="lazy">
            </div>

            <span><?= explode(' | ', $glove->paint_name)[0]; ?></span>
            <div class="marks"></div>
        </button>
    </li>
<?php endforeach; ?>

<?php foreach($agents as $agent): ?>
    <?php
    if(empty($agent->image)) continue;

    if($selected_team === 'T' && (int)$agent->team !== 2) continue;
    if($selected_team === 'CT' && (int)$agent->team !== 3) continue;

    $agent_team_slug = (int)$agent->team === 2 ? 'terrorist' : 'counter-terrorist';
    $agent_model_url = str_replace('/', '-', $agent->model);
    ?>
    
    <li class="weapon-tab-item tab-agents agent-card-item">
    <button class="card agent-card"
                onclick="location.href='<?= GetPrefix(); ?>skins/<?= $agent_team_slug; ?>/<?= $agent_model_url; ?>/'">

            <div class="imgbox">
                <img src="<?= $agent->image; ?>" loading="lazy">
            </div>

            <span><?= $agent->agent_name; ?></span>

            <div class="marks"></div>
        </button>
    </li>
<?php endforeach; ?>

<?php foreach($songs as $song): ?>
    <li class="weapon-tab-item tab-mvp">
        <button class="card"
                onclick="location.href='<?= GetPrefix(); ?>skins/mvp/<?= $song->id; ?>'">

            <div class="imgbox">
                <img src="<?= $song->image; ?>" loading="lazy">
            </div>

            <span><?= $song->name; ?></span>
            <div class="marks"></div>
        </button>
    </li>
<?php endforeach; ?>

</ul>
        </div>
    </div>
</div>

</div>

<footer>
    <div class="wrapper">
        <a class="info" href="https://steamcommunity.com/profiles/<?= $_SESSION['steamid']; ?>" target="_blank">
            <img src="<?= $UserInfo->avatarfull ?>" alt="name">
            <p><?= str_replace('{{name}}', "<strong>$UserInfo->personaname</strong>", $translations->skins->footer->signedin); ?></p>
        </a>

        <div class="credit">
            <p>This website created by CefreptGOD</p>
        </div>

        <div class="actions">
            <div class="settings">
                <svg viewBox="0 0 48 48" data-action="toggle_menu">
                    <path d="M0 0h48v48H0z" fill="none"/>
                    <path d="M38.86 25.95c.08-.64.14-1.29.14-1.95s-.06-1.31-.14-1.95l4.23-3.31c.38-.3.49-.84.24-1.28l-4-6.93c-.25-.43-.77-.61-1.22-.43l-4.98 2.01c-1.03-.79-2.16-1.46-3.38-1.97L29 4.84c-.09-.47-.5-.84-1-.84h-8c-.5 0-.91.37-.99.84l-.75 5.3c-1.22.51-2.35 1.17-3.38 1.97L9.9 10.1c-.45-.17-.97 0-1.22.43l-4 6.93c-.25.43-.14.97.24 1.28l4.22 3.31C9.06 22.69 9 23.34 9 24s.06 1.31.14 1.95l-4.22 3.31c-.38.3-.49.84-.24 1.28l4 6.93c.25.43.77.61 1.22.43l4.98-2.01c1.03.79 2.16 1.46 3.38 1.97l.75 5.3c.08.47.49.84.99.84h8c.5 0 .91-.37.99-.84l.75-5.3c1.22-.51 2.35-1.17 3.38-1.97l4.98 2.01c.45.17.97 0 1.22-.43l4-6.93c.25-.43.14-.97-.24-1.28l-4.22-3.31zM24 31c-3.87 0-7-3.13-7-7s3.13-7 7-7 7 3.13 7 7-3.13 7-7 7z"/>
                </svg>

                <div class="items">
                    <ul>
                        <?php if($Website_Settings['language']) { ?>
                            <button data-action="language_select" data-langs='<?= json_encode($langs); ?>'>
                                <span><?= $translations->skins->footer->settings->language ?></span>
                            </button>
                        <?php } ?>

                        <?php if($Website_Settings['theme']) { ?>
                            <button data-action="color_select" data-translations='<?= json_encode($translations->skins->footer->settings->theme); ?>'>
                                <span><?= $translations->skins->footer->settings->theme->label ?></span>
                            </button>
                        <?php } ?>
                    </ul>
                </div>
            </div>

            <button class="main-btn btn-yellow" onclick="ToggleLoading();location.href='<?= GetPrefix(); ?>signout';">
                <?= $translations->skins->footer->sign_out ?>
            </button>
        </div>
    </div>
</footer>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const tabs = document.querySelectorAll('.weapon-tab');
    const items = document.querySelectorAll('.weapon-tab-item');

    function openTab(tabName) {
        tabs.forEach(tab => {
            tab.classList.toggle('active', tab.dataset.tab === tabName);
        });

        items.forEach(item => {
            item.classList.toggle('active', item.classList.contains('tab-' + tabName));
        });
    }

    tabs.forEach(tab => {
        tab.addEventListener('click', () => openTab(tab.dataset.tab));
    });

    openTab('loadout');
});
</script>

</body>
</html>