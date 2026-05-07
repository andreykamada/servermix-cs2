let currentMenu = false;
let stoptimeout = false;
let lastHover = null;

let selectedStickerSlot = null;
let selectedStickers = {};
let selectedKeychain = null;

/* =========================
   SOM
========================= */
function playSound(id) {
    const audio = document.getElementById(id);
    if (!audio) return;
    audio.currentTime = 0;
    audio.play().catch(() => {});
}

/* =========================
   CLICK GLOBAL
========================= */
document.addEventListener('click', function (e) {

    const target = e.target.closest('[data-action]');
    if (!target) {
        let settings = document.querySelector('footer .settings');
        if (settings && settings.hasAttribute('data-open')) {
            settings.removeAttribute('data-open');
        }
        return;
    }

    const action = target.getAttribute('data-action');

    playSound('cs-click');

    switch (action) {

        /* =========================
           WEAPONS
        ========================= */

        case 'weapon_picked': {
            let weapon = target.getAttribute('data-weapon');
            if (!weapon) return;

            animateSelection(target, () => {
                window.location.href = `/skins/${weapon}/`;
            });
            break;
        }

        case 'weapon_choose':
            ToggleLoading();
            window.location.href = '/skins/';
            break;

        case 'weapon_change': {
            const weapon = target.getAttribute('data-weapon');
            const paint = target.getAttribute('data-paint');

            if (!weapon || paint === null || paint === '') return;

            animateSelection(target, () => {
                window.location.href = `/skins/${encodeURIComponent(weapon)}/${encodeURIComponent(paint)}/`;
            });

            break;
        }

        /* =========================
           AGENTS
        ========================= */

        case 'agent_picked': {
            let team = target.getAttribute('data-team');
            if (!team) return;

            window.location.href = `/skins/${team}/`;
            break;
        }

        case 'agent_change': {
            let agentModel = target.getAttribute('data-agent');
            if (!agentModel) return;

            agentModel = agentModel.replaceAll('/', '-');

            window.location.href = `${window.location.pathname}${agentModel}/`;
            break;
        }

        /* =========================
           TEAM SELECT
        ========================= */

        case 'team_select': {
    let selectedTeam = target.getAttribute('data-team');
    if (!selectedTeam) return;

    localStorage.setItem("selectedTeam", selectedTeam);
    document.cookie = `selectedTeam=${selectedTeam}; path=/; max-age=2592000`;

    setTimeout(() => {
        window.location.href = `/skins/${selectedTeam}`;
    }, 300);

    break;
}

        case 'team_back':
            localStorage.removeItem("selectedTeam");
            document.cookie = "selectedTeam=; path=/; max-age=0";

            document.getElementById("team-screen")?.style.setProperty("display", "flex");
            document.getElementById("back-to-team")?.style.setProperty("display", "none");
            break;

        /* =========================
           MVP
        ========================= */

        case 'mvp_change': {
            let mvpid = target.getAttribute('data-id');
            if (!mvpid) return;

            window.location.href = `${window.location.pathname}${mvpid}/`;
            break;
        }

        /* =========================
           STICKERS / KEYCHAIN
        ========================= */

case 'sticker_select': {
    const slot = target.getAttribute('data-slot');
    if (slot === null || slot === '') return;

    selectedStickerSlot = slot;

    document.querySelectorAll('.stickers button').forEach(btn => {
        btn.classList.toggle('active-slot', btn.getAttribute('data-slot') === slot);
    });

    document.querySelector('.addon-picker-section[data-picker="stickers"]')?.classList.add('active');
    document.querySelector('.addon-picker-section[data-picker="keychains"]')?.classList.remove('active');

    break;
}

case 'keychain_select': {
    document.querySelector('.addon-picker-section[data-picker="keychains"]')?.classList.add('active');
    document.querySelector('.addon-picker-section[data-picker="stickers"]')?.classList.remove('active');

    break;
}

case 'sticker_change': {
    const sticker = target.getAttribute('data-sticker');
    const image = target.querySelector('img')?.getAttribute('src');

    if (selectedStickerSlot === null || !sticker || !image) return;

    selectedStickers[selectedStickerSlot] = sticker;

    const slotButton = document.querySelector(
        `.stickers button[data-slot="${selectedStickerSlot}"]`
    );

    if (slotButton) {
        slotButton.innerHTML = `<img src="${image}" loading="lazy">`;
        slotButton.setAttribute('data-id', sticker);
        slotButton.classList.add('filled');
    }

    break;
}

case 'keychain_change': {
    const keychain = target.getAttribute('data-keychain');
    const image = target.querySelector('img')?.getAttribute('src');

    if (!keychain || !image) return;

    selectedKeychain = keychain;

    const keychainButton = document.querySelector('.keychains button');

    if (keychainButton) {
        keychainButton.innerHTML = `<img src="${image}" loading="lazy">`;
        keychainButton.setAttribute('data-id', keychain);
        keychainButton.classList.add('filled');
    }

    break;
}

        /* =========================
           CATEGORY
        ========================= */

        case 'category':
            if (target.classList.contains('selected')) {
                SendFormPost([['category', 'none']]);
                return;
            }

            SendFormPost([
                ['category', target.getAttribute('data-category')]
            ]);
            break;

        /* =========================
           MENU
        ========================= */

        case 'toggle_menu': {
            let menu = target.parentElement;
            if (!menu) return;

            menu.hasAttribute('data-open')
                ? menu.removeAttribute('data-open')
                : menu.setAttribute('data-open', true);
            break;
        }

        case 'menu_back':
            if (currentMenu) {
                target.parentElement.innerHTML = currentMenu;
                currentMenu = false;
            }
            break;

        /* =========================
           LANGUAGE
        ========================= */

        case 'language_select': {
            currentMenu = target.parentElement.innerHTML;

            let langs = target.getAttribute('data-langs');
            if (!langs) return;

            let templateLang = `<button data-action="menu_back">←</button>`;

            JSON.parse(langs).forEach(lang => {
                lang = lang.replaceAll('.json', '');
                const languageNames = new Intl.DisplayNames([lang], { type: 'language' });

                templateLang += `
                <button data-action="language_change" data-language="${lang}">
                    ${languageNames.of(lang)}
                </button>`;
            });

            target.parentElement.innerHTML = templateLang;
            break;
        }

        case 'language_change': {
            let lang = target.getAttribute('data-language');
            if (!lang) return;

            ToggleLoading();

            let date = new Date();
            date.setTime(date.getTime() + 90 * 24 * 60 * 60 * 1000);

            document.cookie = `cs2weaponpaints_lielxd_language=${lang}; path=/;expires=${date}`;
            window.location.reload();
            break;
        }

        /* =========================
           PREVIEW
        ========================= */

        case 'toggle_preview':
            if (target.children.length != 2 || stoptimeout) return;

            stoptimeout = true;
            setTimeout(() => stoptimeout = false, 500);

            for (const elem of target.children) {
                elem.style.top = getComputedStyle(elem).top === '0px' ? '100%' : '0px';
            }
            break;
    }
});

/* =========================
   HOVER CONTROLADO
========================= */
document.addEventListener('mouseover', (e) => {
    const card = e.target.closest('.card');
    if (!card || card === lastHover) return;

    lastHover = card;
    playSound('cs-hover');
});

/* =========================
   INIT
========================= */
document.addEventListener("DOMContentLoaded", () => {

    /* =========================
   TEAM THEME TR / CT
========================= */
const pathTeam = window.location.pathname.toLowerCase().includes('/skins/tr')
    ? 'TR'
    : window.location.pathname.toLowerCase().includes('/skins/ct')
        ? 'CT'
        : localStorage.getItem("selectedTeam");

document.body.classList.remove('team-tr', 'team-ct');

if (pathTeam === 'TR') {
    document.body.classList.add('team-tr');
}

if (pathTeam === 'CT') {
    document.body.classList.add('team-ct');
}
    const teamScreen = document.getElementById("team-screen");
    const backBtn = document.getElementById("back-to-team");

    const saved = localStorage.getItem("selectedTeam");

    if (saved) {
        if (teamScreen) teamScreen.style.display = "none";
        if (backBtn) backBtn.style.display = "block";
    } else {
        if (teamScreen) teamScreen.style.display = "flex";
        if (backBtn) backBtn.style.display = "none";
    }
});

/* =========================
   ANIMAÇÃO
========================= */
function animateSelection(element, callback) {
    const card = element.closest('.card, .skin-card');

    if (!card) {
        if (callback) callback();
        return;
    }

    card.classList.add('selecting');

    playSound('cs-open');

    setTimeout(() => {
        if (callback) callback();
    }, 250);
}

/* =========================
   HELPERS
========================= */
function ToggleLoading(stop) {
    const loading = document.getElementById('loading');
    if (!loading) return;

    if (stop) {
        loading.removeAttribute('data-loading');
    } else {
        loading.setAttribute('data-loading', true);
    }
}

function SendFormPost(data) {
    if (!data || !data.length) return;

    let form = document.createElement('form');
    form.method = 'POST';
    form.style.display = 'none';

    data.forEach(field => {
        let input = document.createElement('input');
        input.name = field[0];
        input.value = field[1];
        form.append(input);
    });

    document.body.append(form);
    form.submit();
}