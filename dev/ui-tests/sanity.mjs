/**
 * Browser run through a shop with the module installed.
 *
 * With the native menu: every menu page opens without a PHP message, and on
 * PrestaShop 8 each one is drawn with the PrestaShop 9 stylesheets the module
 * ships, with the fixes it adds (mobile notice hidden, no icon name written out,
 * space under the page header). On PrestaShop 9 the module leaves the look alone.
 * Then the simplified menu is switched on from the module configuration, and the
 * menu and the settings page are checked.
 *
 * Usage: node sanity.mjs <admin url> <email> <password> <browser> <shot dir> <PrestaShop major> [results file]
 */
import { appendFileSync, mkdirSync } from 'node:fs';
import { chromium, devices, firefox } from 'playwright';

const [adminUrl, email, password, browserName = 'chromium', shots = 'ui-shots', major = '8', results = ''] = process.argv.slice(2);
if (!adminUrl || !email || !password) {
    console.error('usage: node sanity.mjs <admin url> <email> <password> <browser> <shot dir> <PrestaShop major> [results file]');
    process.exit(2);
}
const isPs8 = major === '8';

// PHP's own format, file and line included: PrestaShop pages write "Warning:"
// in their help texts too.
const PHP_MESSAGES = /((Fatal error|Parse error|Warning|Notice|Deprecated): .{0,400}? in \S+\.php on line \d+|Stack trace:)/;

mkdirSync(shots, { recursive: true });
const browser = await (browserName === 'firefox' ? firefox : chromium).launch();
const page = await browser.newPage({ viewport: { width: 1400, height: 900 } });
const errors = [];
let step = 'start';

// What the module's own scripts write to the console fails the run too: they run
// on every back office page, where nobody opens the console to notice.
page.on('console', (message) => {
    if (['error', 'warning'].includes(message.type()) && (message.location().url || '').includes('/modules/ps_edition_basic/')) {
        errors.push(`${step}: console ${message.type()}: ${message.text().slice(0, 200)}`);
    }
});

// A script error fails the run when it comes from the module's own files.
page.on('pageerror', (error) => {
    const frame = String(error.stack || '').split('\n').find((line) => /https?:\/\//.test(line)) || '';
    const where = (frame.match(/https?:\/\/[^\s)@]+?\.js\b[^\s)]*/) || [''])[0];
    if (!where.includes('/modules/ps_edition_basic/')) {
        console.log(`::warning title=Script error outside the module::${step}: ${error.message} (${where || 'inline'})`);
        return;
    }
    errors.push(`${step}: ${error.message} (${where})`);
});

const check = async (name, run) => {
    step = name;
    const before = errors.length;
    try {
        await run();
    } catch (error) {
        errors.push(`${name}: ${error.message.split('\n')[0]}`);
    }
    const failed = errors.length > before;
    if (results !== '') {
        appendFileSync(results, `${failed ? 'FAIL' : 'PASS'} ${name}\n`);
    }
    if (failed) {
        await page.screenshot({ path: `${shots}/${name.replace(/[^a-z0-9]+/gi, '-').slice(0, 80)}.png`, fullPage: true }).catch(() => {});
        console.log(`[FAIL] ${name}`);
        return false;
    }
    console.log(`[PASS] ${name}`);
    return true;
};

// Views of the back office kept with the run, to look at the result rather than
// only read it. On a manual run they are also written to
// the log, which can be read where the run's files cannot be downloaded.
// The simplified menu, drawn by the server and moved into the navigation bar by
// the module's script: its entries, sections and their pages, in order.
const menuReady = () => document.querySelectorAll('#peb-side-bar .peb-side-bar__row').length > 5;
const menuEntries = () => [...document.querySelectorAll('#peb-side-bar .peb-side-bar__row, #peb-side-bar .peb-side-bar__links a')]
    .map((entry) => ({
        text: (entry.querySelector('.peb-side-bar__label') || entry).textContent.replace(/\s+/g, ' ').trim(),
        href: (entry.closest('a') || {}).href || '',
    }));

const view = async (name, on = page) => {
    await on.waitForTimeout(1500);
    const file = `${shots}/view-${name}.jpg`;
    const image = await on.screenshot({ path: file, type: 'jpeg', quality: 80 });
    if (process.env.PRINT_VIEWS === '1') {
        const data = image.toString('base64');
        for (let i = 0; i < data.length; i += 4000) {
            console.log(`VIEW ${name} ${data.slice(i, i + 4000)}`);
        }
    }
};

const open = async (url) => {
    // load, not networkidle: some PrestaShop modules' pages (Facebook & Instagram)
    // keep calling outside services, and the network never goes quiet.
    const response = await page.goto(url, { waitUntil: 'load', timeout: 90000 });
    if (!response || response.status() >= 400) {
        throw new Error(`answered ${response ? response.status() : 'nothing'}`);
    }
    const text = await page.locator('body').innerText();
    const found = text.match(PHP_MESSAGES);
    if (found) {
        throw new Error(`PHP message on the page: "${found[0]}"`);
    }
};

// Text drawn over other text: what a layout missing a spacing looks like, in a
// page or in a window another module opens.
const overlaps = (scope) => page.evaluate((selector) => {
    const root = document.querySelector(selector);
    if (!root) {
        return [];
    }
    const boxes = [];
    for (const element of root.querySelectorAll('*')) {
        const text = [...element.childNodes].filter((node) => node.nodeType === 3).map((node) => node.textContent.trim()).join(' ').trim();
        const style = getComputedStyle(element);
        const box = element.getBoundingClientRect();
        if (text.length < 2 || box.width < 4 || box.height < 4 || style.visibility === 'hidden' || parseFloat(style.opacity) < 0.05) {
            continue;
        }
        // Only text actually painted: inside the window, not clipped by a scrolled
        // frame, and not behind an opaque layer. Text under other text that has no
        // background of its own is still seen: that is the fault looked for.
        const x = box.left + Math.min(box.width / 2, 6);
        const y = box.top + box.height / 2;
        if (x < 0 || y < 0 || x >= innerWidth || y >= innerHeight) {
            continue;
        }
        let clipped = false;
        for (let frame = element.parentElement; frame && frame !== document.body; frame = frame.parentElement) {
            const overflow = getComputedStyle(frame);
            if (overflow.overflowX !== 'visible' || overflow.overflowY !== 'visible') {
                const edge = frame.getBoundingClientRect();
                if (x < edge.left || x > edge.right || y < edge.top || y > edge.bottom) {
                    clipped = true;
                    break;
                }
            }
        }
        const top = document.elementFromPoint(x, y);
        const opaque = (layer) => {
            for (let node = layer; node && !node.contains(element); node = node.parentElement) {
                const colour = (getComputedStyle(node).backgroundColor.match(/[\d.]+/g) || []).map(Number);
                if ((colour.length === 3) || (colour.length === 4 && colour[3] > 0.5) || getComputedStyle(node).backgroundImage !== 'none') {
                    return true;
                }
            }
            return false;
        };
        if (clipped || !top || (!(element === top || element.contains(top)) && opaque(top))) {
            continue;
        }
        boxes.push({ element, text, box });
    }
    const found = [];
    for (let i = 0; i < boxes.length && found.length < 5; ++i) {
        for (let j = i + 1; j < boxes.length; ++j) {
            const [a, b] = [boxes[i], boxes[j]];
            if (a.element.contains(b.element) || b.element.contains(a.element)) {
                continue;
            }
            const width = Math.min(a.box.right, b.box.right) - Math.max(a.box.left, b.box.left);
            const height = Math.min(a.box.bottom, b.box.bottom) - Math.max(a.box.top, b.box.top);
            const smaller = Math.min(a.box.width * a.box.height, b.box.width * b.box.height);
            if (width > 0 && height > 0 && width * height > smaller * 0.3) {
                found.push(`"${a.text.slice(0, 25)}" over "${b.text.slice(0, 25)}"`);
                break;
            }
        }
    }
    return found;
}, scope);

// What the module must have done to the page in front of the browser.
// Every box of the page that scrolls on its own, with its size and the style
// rules that set its height or its overflow: enough to aim one rule at it.
const scrollingBoxes = () => {
    const name = (element) => `${element.tagName.toLowerCase()}${element.id ? '#' + element.id : ''}${[...element.classList].slice(0, 3).map((c) => '.' + c).join('')}`;
    const rules = (element) => {
        const found = [];
        for (const sheet of document.styleSheets) {
            let list;
            try {
                list = sheet.cssRules;
            } catch (error) {
                continue;
            }
            for (const rule of list || []) {
                try {
                    if (rule.selectorText && element.matches(rule.selectorText) && /height|overflow/.test(rule.style.cssText)) {
                        found.push(`${rule.selectorText} { ${rule.style.cssText} } (${(sheet.href || 'inline').split('/').pop()})`);
                    }
                } catch (error) {
                    // A selector this browser cannot read.
                }
            }
        }
        return found.slice(0, 6);
    };
    // Apps such as Checkout's draw inside shadow roots, which querySelectorAll
    // does not enter; the page itself is looked at too, from the root down.
    const all = [];
    const walk = (root) => {
        for (const element of root.querySelectorAll('*')) {
            all.push(element);
            if (element.shadowRoot) {
                walk(element.shadowRoot);
            }
        }
    };
    walk(document);
    return JSON.stringify([...all]
        .filter((element) => {
            const style = getComputedStyle(element);
            // A frame scrolls its own document, which this page cannot measure:
            // it is listed with its size and rules whatever it holds.
            return element.getBoundingClientRect().height > 0 && (element.tagName === 'IFRAME'
                || (/(auto|scroll|hidden|clip)/.test(style.overflowY) && element.scrollHeight > element.clientHeight + 1));
        })
        .sort((a, b) => b.scrollHeight - b.clientHeight - (a.scrollHeight - a.clientHeight))
        .slice(0, 8)
        .map((element) => {
            const chain = [];
            for (let node = element; node && chain.length < 6; node = node.parentElement || (node.getRootNode() && node.getRootNode().host)) {
                chain.unshift(name(node));
            }
            const style = getComputedStyle(element);
            const box = element.getBoundingClientRect();
            return {
                box: chain.join(' > '),
                top: Math.round(box.top),
                bottom: Math.round(box.bottom),
                src: (element.getAttribute('src') || '').slice(0, 80),
                client: element.clientHeight,
                scroll: element.scrollHeight,
                height: style.height,
                maxHeight: style.maxHeight,
                overflowY: style.overflowY,
                inline: element.getAttribute('style') || '',
                rules: rules(element),
                // A frame at 100% takes its height from a parent: each one up
                // to the page content, with what sizes it.
                parents: element.tagName !== 'IFRAME' ? [] : (() => {
                    const list = [];
                    for (let node = element.parentElement; node && node.id !== 'content' && list.length < 5; node = node.parentElement) {
                        list.push({ box: name(node), height: getComputedStyle(node).height, inline: node.getAttribute('style') || '', rules: rules(node) });
                    }
                    return list;
                })(),
            };
        }));
};

const lookChecks = async () => {
    const state = await page.evaluate(() => {
        const sheets = [...document.querySelectorAll('link[rel="stylesheet"]')].map((link) => link.getAttribute('href') || '');
        const notice = document.getElementById('non-responsive');
        const wordIcons = [...document.querySelectorAll('.material-icons')]
            .filter((icon) => icon.offsetParent && icon.children.length === 0
                && icon.getBoundingClientRect().width > parseFloat(getComputedStyle(icon).fontSize) * 1.6)
            .map((icon) => icon.textContent.trim());
        // A module's configuration page has two page headers, only the last shown.
        const head = [...document.querySelectorAll('#content:is(.bootstrap, .nobootstrap) .page-head')].filter((element) => element.getBoundingClientRect().height > 0).pop();
        let gap = null;
        let gapBlock = '';
        if (head && !document.body.classList.contains('display-modal')) {
            const bottom = head.getBoundingClientRect().bottom;
            const blocks = [...document.querySelectorAll('#content .panel, #content .alert, #content form, #content .row, #content #app')]
                .filter((block) => !block.closest('.page-head') && block.getBoundingClientRect().height > 10)
                .sort((a, b) => a.getBoundingClientRect().top - b.getBoundingClientRect().top);
            if (blocks.length) {
                gap = Math.round(blocks[0].getBoundingClientRect().top - bottom);
                gapBlock = `${blocks[0].tagName.toLowerCase()}.${[...blocks[0].classList].join('.')} "${blocks[0].textContent.trim().slice(0, 40)}"`;
            }
        }
        // Whatever the page, nothing readable may sit under the fixed header, and no
        // text may be drawn in the colour of what is behind it: pages of other
        // modules are built for the PrestaShop 8 look and can clash with the 9 one.
        const shown = (element) => {
            const box = element.getBoundingClientRect();
            const style = getComputedStyle(element);
            return box.width > 0 && box.height > 0 && style.visibility !== 'hidden' && parseFloat(style.opacity) > 0.05;
        };
        // The top of everything painted in the page (text, field, image, background,
        // border), not only the middle of a text: a block whose top edge slips under
        // the header is caught too.
        const header = [...document.querySelectorAll('.header-toolbar, .page-head, .main-header, #header')]
            .filter((element) => shown(element) && ['fixed', 'sticky'].includes(getComputedStyle(element).position));
        const headerBottom = Math.max(0, ...header.map((element) => element.getBoundingClientRect().bottom));
        const covered = [];
        // Desktop only: on a phone the menu is a panel that opens over the page.
        const menu = document.querySelector('.nav-bar');
        const menuBox = menu && shown(menu) ? menu.getBoundingClientRect() : null;
        const menuRight = menuBox && menuBox.left >= 0 && innerWidth >= 1024 ? menuBox.right : 0;
        const underMenu = [];
        const area = document.querySelector('.content-div') || document.querySelector('#content');
        for (const element of area ? area.querySelectorAll('*') : []) {
            if (covered.length > 4 || !shown(element) || element.closest('.header-toolbar, .page-head, .main-header, #header, .nav-bar, .modal, .dropdown-menu, [role="dialog"], .growl, #ajax_confirmation, script, style')) {
                continue;
            }
            // The help panel and other fixed layers slide in from outside the page.
            let layer = false;
            for (let node = element; node && node !== area; node = node.parentElement) {
                if (getComputedStyle(node).position === 'fixed') {
                    layer = true;
                    break;
                }
            }
            const box = element.getBoundingClientRect();
            if (layer || box.left >= innerWidth || box.right <= 0 || box.bottom <= 0) {
                continue;
            }
            const style = getComputedStyle(element);
            const painted = [...element.childNodes].some((node) => node.nodeType === 3 && node.textContent.trim())
                || ['IMG', 'INPUT', 'SELECT', 'TEXTAREA', 'BUTTON', 'svg', 'CANVAS', 'IFRAME'].includes(element.tagName)
                || !['rgba(0, 0, 0, 0)', 'transparent'].includes(style.backgroundColor)
                || parseFloat(style.borderTopWidth) > 0;
            if (painted && box.top < headerBottom - 1) {
                covered.push(`${element.tagName.toLowerCase()} "${element.textContent.trim().slice(0, 30)}" (${Math.round(headerBottom - box.top)}px)`);
            }
            if (painted && menuRight > 0 && box.left < menuRight - 1 && underMenu.length < 5) {
                underMenu.push(`${element.tagName.toLowerCase()} "${element.textContent.trim().slice(0, 30)}" (${Math.round(menuRight - box.left)}px)`);
            }
        }
        // The page header spans the whole width behind the menu: only its texts count.
        for (const element of document.querySelectorAll('.page-head *, .header-toolbar *')) {
            const box = element.getBoundingClientRect();
            const text = [...element.childNodes].some((node) => node.nodeType === 3 && node.textContent.trim());
            if (text && shown(element) && menuRight > 0 && box.left < menuRight - 1 && box.right > menuRight && underMenu.length < 5) {
                underMenu.push(`${element.tagName.toLowerCase()} "${element.textContent.trim().slice(0, 30)}" (${Math.round(menuRight - box.left)}px)`);
            }
        }
        // Scroll bars the page should not have: sideways on the whole page, or on a
        // block whose content does not fit, other than the ones made to scroll.
        const sideways = Math.round(document.documentElement.scrollWidth - innerWidth);
        let widest = '';
        if (sideways > 1) {
            let right = 0;
            for (const element of document.querySelectorAll('body *')) {
                const box = element.getBoundingClientRect();
                if (box.width > 0 && box.right > right && getComputedStyle(element).position !== 'fixed') {
                    right = box.right;
                    widest = `${element.tagName.toLowerCase()}${element.id ? '#' + element.id : ''}.${[...element.classList].slice(0, 3).join('.')} up to ${Math.round(box.right)}px`;
                }
            }
        }
        const scrollBars = [...document.querySelectorAll('#main *')]
            .filter((element) => {
                if (!shown(element) || element.closest('.nav-bar, .modal, .dropdown-menu, .table-responsive, textarea, select, pre, code, .select2-results, .CodeMirror, .tree-panel-heading-controls')) {
                    return false;
                }
                const style = getComputedStyle(element);
                const across = /(auto|scroll)/.test(style.overflowX) && element.scrollWidth > element.clientWidth + 1;
                const down = /(auto|scroll)/.test(style.overflowY) && element.scrollHeight > element.clientHeight + 1;
                return (across || down) && (element.offsetWidth - element.clientWidth > 0 || element.offsetHeight - element.clientHeight > 0);
            })
            .slice(0, 5)
            .map((element) => `${element.tagName.toLowerCase()}${element.id ? '#' + element.id : ''}.${[...element.classList].slice(0, 2).join('.')}`);
        // PrestaShop 9 fills btn-outline-primary in grey; PrestaShop 8 secondary
        // actions use it and must stay white, as btn-outline-secondary in 9.
        const grey = [...document.querySelectorAll('.btn-outline-primary')]
            .filter((button) => shown(button) && !button.matches(':hover, :focus, :active') && !button.disabled && !button.classList.contains('disabled')
                && getComputedStyle(button).backgroundColor !== 'rgb(255, 255, 255)')
            .map((button) => `"${button.textContent.trim().slice(0, 30)}"`);
        const rgb = (value) => (value.match(/[\d.]+/g) || []).map(Number);
        const luminance = ([r, g, b]) => [r, g, b].map((c) => { c /= 255; return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4; })
            .reduce((sum, c, i) => sum + c * [0.2126, 0.7152, 0.0722][i], 0);
        const background = (element) => {
            for (let node = element; node; node = node.parentElement) {
                const style = getComputedStyle(node);
                if (style.backgroundImage !== 'none') {
                    return null;
                }
                const colour = rgb(style.backgroundColor);
                if (colour.length === 3 || (colour.length === 4 && colour[3] > 0.5)) {
                    return colour;
                }
            }
            return [255, 255, 255];
        };
        const invisible = [];
        for (const element of document.querySelectorAll('body *')) {
            if (invisible.length > 4 || !shown(element) || element.closest('.nav-bar, .modal:not(.show), [aria-hidden="true"]')) {
                continue;
            }
            const text = [...element.childNodes].find((node) => node.nodeType === 3 && node.textContent.trim().length > 1);
            const behind = text ? background(element) : null;
            const colour = rgb(getComputedStyle(element).color);
            if (!behind || (colour.length === 4 && colour[3] < 0.3)) {
                continue;
            }
            const [light, dark] = [luminance(colour), luminance(behind)].sort((a, b) => b - a);
            if ((light + 0.05) / (dark + 0.05) < 1.35) {
                invisible.push(`"${text.textContent.trim().slice(0, 30)}"`);
            }
        }
        return { sheets, notice: notice ? getComputedStyle(notice).display : 'none', wordIcons, gap, gapBlock, covered, invisible, grey, underMenu, sideways, widest, scrollBars };
    });
    const clashes = [
        ...state.covered.map((what) => `under the header: ${what}`),
        ...state.underMenu.map((what) => `under the menu: ${what}`),
        ...(state.sideways > 1 ? [`the page scrolls sideways by ${state.sideways}px (${state.widest})`] : []),
        ...state.scrollBars.map((what) => `a scroll bar on ${what}`),
        ...state.invisible.map((what) => `text the colour of its background: ${what}`),
    ];
    if (clashes.length > 0 && !isPs8) {
        // PrestaShop 9 draws these pages itself: reported, not held against the module.
        console.log(`::warning title=Look on PrestaShop 9::${step}: ${clashes.join('; ')}`);
    }
    const core = state.sheets.filter((href) => /\/themes\/(new-theme|default)\/public\/theme\.css/.test(href));
    const ours = state.sheets.filter((href) => href.includes('/modules/ps_edition_basic/views/ps9/'));
    if (isPs8) {
        if (ours.length === 0) {
            throw new Error('the PrestaShop 9 stylesheet of the module is not loaded');
        }
        if (core.length > 0) {
            throw new Error(`the PrestaShop 8 stylesheet is still loaded: ${core.join(', ')}`);
        }
        if (state.notice !== 'none') {
            throw new Error('the mobile notice shows on a desktop screen');
        }
        if (state.wordIcons.length > 0) {
            throw new Error(`icon names written out: ${[...new Set(state.wordIcons)].join(', ')}`);
        }
        if (state.gap !== null && state.gap < 8) {
            throw new Error(`the first block starts ${state.gap}px under the page header: ${state.gapBlock}`);
        }
        if (state.grey.length > 0) {
            throw new Error(`secondary buttons filled in grey: ${state.grey.join(', ')}`);
        }
        if (clashes.length > 0) {
            // The boxes of the page frame, to tell which rule placed what.
            console.log(`LAYOUT ${step}: ${await page.evaluate(() => JSON.stringify([
                document.querySelector('#main'), document.querySelector('#content'),
                ...document.querySelectorAll('#content > *, .page-head, .page-head > *'),
            ].filter(Boolean).map((element) => {
                const box = element.getBoundingClientRect();
                const style = getComputedStyle(element);
                return [`${element.tagName.toLowerCase()}${element.id ? '#' + element.id : ''}.${[...element.classList].join('.')}`,
                    [box.left, box.top, box.right, box.bottom].map(Math.round), style.display, style.position,
                    style.margin, style.padding, style.width, style.minWidth, style.boxSizing];
            })))}`);
            throw new Error(clashes.join('; '));
        }
        const stacked = await overlaps('body');
        if (stacked.length > 0) {
            throw new Error(`text over text: ${stacked.join('; ')}`);
        }
    } else if (ours.length > 0) {
        throw new Error('the module changes the stylesheets of PrestaShop 9');
    }
};

const signedIn = await check('sign in to the back office', async () => {
    await page.goto(adminUrl);
    // The login page skips the back office media: on PrestaShop 8 the module
    // gives it the PrestaShop 9 look with a stylesheet of its own.
    const loginStyle = await page.locator('link[href*="/modules/ps_edition_basic/views/css/login.css"]').count();
    if (isPs8 !== (loginStyle > 0)) {
        throw new Error(isPs8 ? 'the login page misses the PrestaShop 9 look' : 'the module styles the PrestaShop 9 login page');
    }
    await view('0-login');
    await page.locator('input[type="email"]').first().fill(email);
    await page.locator('input[type="password"]').first().fill(password);
    await Promise.all([
        page.waitForLoadState('load'),
        page.locator('form:has(input[type="password"]) button[type="submit"]').first().click(),
    ]);
    await page.locator('.nav-bar a').first().waitFor({ state: 'attached', timeout: 60000 });
});

if (signedIn) {
    // Every back office page of the menu: PrestaShop 9 drops index.php from its
    // URLs, so a link is kept for pointing inside the back office folder.
    const adminRoot = new URL('.', adminUrl).toString();
    const links = await page.$$eval('.nav-bar a', (anchors, root) => [...new Map(anchors
        .filter((anchor) => anchor.href.startsWith(root) && !/logout|AdminLogin/i.test(anchor.href))
        .map((anchor) => [anchor.href, (anchor.textContent || '').trim().replace(/\s+/g, ' ')])).entries()], adminRoot);
    console.log(`${links.length} menu pages`);
    await view('1-native-menu');

    for (const [href, label] of links) {
        await check(`page ${label}`, async () => {
            await open(href);
            await lookChecks();
        });
    }

    // The configuration page of every installed module: the module's look reaches
    // pages written by others, which the menu alone does not list.
    const manager = links.find(([href]) => /improve\/modules\/manage|controller=AdminModulesSf/.test(href));
    if (manager) {
        await open(manager[0]).catch(() => {});
        const configurePages = await page.$$eval('a[href*="/action/configure/"], a[href*="controller=AdminModules"][href*="configure="]', (anchors) => [...new Map(anchors
            .map((anchor) => [anchor.href, (anchor.href.match(/configure[/=]([a-z0-9_]+)/i) || [])[1] || anchor.href])).entries()]);
        console.log(`${configurePages.length} module configuration pages`);
        for (const [href, name] of configurePages.slice(0, 120)) {
            await check(`module ${name}`, async () => {
                await open(href);
                // Checkout draws its page with an app loaded after the page: the
                // view and the checks wait for it, as the merchant does.
                if (name === 'ps_checkout') {
                    await page.locator('#app > *').first().waitFor({ state: 'visible', timeout: 30000 }).catch(() => {});
                    await page.waitForTimeout(3000);
                    await view('8-checkout');
                }
                await lookChecks();
            });
            // On a short window, as on a laptop, Checkout's app had a box of its
            // own that scrolled inside the page. What scrolls is written to the
            // log with the height rules that reach it, then the usual checks
            // run at that size.
            if (name === 'ps_checkout') {
                await check('module ps_checkout, short window', async () => {
                    await page.setViewportSize({ width: 1220, height: 490 });
                    try {
                        await page.waitForTimeout(1500);
                        await view('8-checkout-short');
                        console.log(`CHECKOUT ${await page.evaluate(scrollingBoxes)}`);
                        console.log(`CHECKOUT frames ${page.frames().map((frame) => frame.url().slice(0, 100)).join(' | ')}`);
                        await lookChecks();
                    } finally {
                        await page.setViewportSize({ width: 1400, height: 900 });
                    }
                });
            }
        }
    }

    // PrestaShop's module catalogue adds a window of recommended modules to pages
    // such as the orders list; its content comes from Addons when it opens.
    const ordersPage = links.find(([href]) => /sell\/orders\/(\?|$)|controller=AdminOrders/.test(href));
    if (ordersPage) {
        await check('recommended modules window', async () => {
            await open(ordersPage[0]);
            const button = page.locator('#recommended-modules-button');
            if (await button.count() === 0) {
                console.log('no recommended modules button on this shop');
                return;
            }
            await button.first().click();
            await page.locator('#modules_list_container .modal-content').waitFor({ state: 'visible', timeout: 30000 });
            await page.waitForTimeout(8000);
            await view('7-recommended-modules');
            if (isPs8) {
                const stacked = await overlaps('#modules_list_container .modal-content');
                if (stacked.length > 0) {
                    throw new Error(`text over text in the window: ${stacked.join('; ')}`);
                }
            }
            await page.keyboard.press('Escape');
        });
    }

    await check('switch the simplified menu on', async () => {
        const manager = links.find(([href]) => /improve\/modules\/manage|controller=AdminModulesSf/.test(href));
        if (!manager) {
            throw new Error('no link to the module manager in the menu');
        }
        // The module manager's configure route, with the token of the manager link:
        // it redirects to the module configuration page on PrestaShop 8 and 9.
        const configure = new URL(manager[0]);
        if (configure.pathname.includes('/improve/modules/manage')) {
            configure.pathname = configure.pathname.replace(/\/improve\/modules\/manage.*$/, '/improve/modules/manage/action/configure/ps_edition_basic');
        } else {
            configure.searchParams.set('controller', 'AdminModules');
            configure.searchParams.set('configure', 'ps_edition_basic');
        }
        await open(configure.toString());
        // The switch draws its own toggle over the radio, which a click on the
        // hidden input does not reach on every theme build: the radio is set
        // directly, and the form itself is submitted as a merchant would.
        await page.locator('#SMB_IS_NEW_MENU_ENABLED_on').evaluate((radio) => { radio.checked = true; });
        await Promise.all([
            page.waitForLoadState('load'),
            page.locator('button[name="submitSmbConfiguration"]').click(),
        ]);
        await page.locator('#peb-side-bar').waitFor({ state: 'attached', timeout: 30000 });
    });

    let settingsUrl = '';
    let helpUrl = '';
    await check('the simplified menu', async () => {
        await page.waitForFunction(menuReady, null, { timeout: 30000 });
        const entries = await page.evaluate(menuEntries);
        const has = (word) => entries.some((entry) => entry.text.includes(word));
        for (const word of ['Dashboard', 'Help Center', 'Orders', 'Catalog', 'Settings']) {
            if (!has(word)) {
                throw new Error(`no ${word} entry`);
            }
        }
        if (entries.some((entry) => /\bModule Manager\b|\bTheme & Logo\b/.test(entry.text))) {
            throw new Error('Modules or Design are still in the menu');
        }
        // The Help Center sits right under the dashboard, in place of PrestaShop's Care Center.
        const labels = entries.map((entry) => entry.text);
        if (labels.findIndex((text) => text.includes('Help Center')) !== labels.findIndex((text) => text.includes('Dashboard')) + 1) {
            throw new Error(`the Help Center is not right under the dashboard: ${labels.slice(0, 4).join(', ')}`);
        }
        if (labels.some((text) => text.includes('Care Center'))) {
            throw new Error("PrestaShop's Care Center is still in the menu");
        }
        helpUrl = (entries.find((entry) => entry.text.includes('Help Center')) || {}).href || '';
        settingsUrl = (entries.find((entry) => /Settings$/.test(entry.text)) || {}).href || '';
        if (!settingsUrl) {
            throw new Error('the Settings entry has no link');
        }
        await view('2-simplified-menu');

        // A section opens on all its pages, however many modules add: the menu
        // component this one replaced cut them off at 24rem.
        const longest = await page.evaluate(() => {
            let best = null;
            for (const group of document.querySelectorAll('#peb-side-bar .peb-side-bar__group')) {
                if (!best || group.querySelectorAll('a').length > best.querySelectorAll('a').length) {
                    best = group;
                }
            }
            return best ? best.querySelector('[data-test]').getAttribute('data-test') : null;
        });
        if (longest) {
            const row = page.locator(`#peb-side-bar [data-test="${longest}"]`);
            if (await row.getAttribute('aria-expanded') !== 'true') {
                await row.click();
            }
            const cut = await page.evaluate((test) => {
                const links = document.querySelector(`#peb-side-bar [data-test="${test}"]`).closest('.peb-side-bar__group').querySelector('.peb-side-bar__links');
                const box = links.getBoundingClientRect();
                return [...links.querySelectorAll('a')].filter((a) => a.getBoundingClientRect().bottom > box.bottom + 1 || a.getBoundingClientRect().height === 0).map((a) => a.textContent.trim());
            }, longest);
            if (cut.length > 0) {
                throw new Error(`pages cut off in the open section: ${cut.join(', ')}`);
            }
        }

        const orders = entries.find((entry) => /Orders$/.test(entry.text));
        if (orders && orders.href) {
            await open(orders.href);
            await view('3-orders-simplified-menu');
        }
    });

    await check('the settings page', async () => {
        await open(settingsUrl);
        await page.locator('#peb-settings').waitFor({ state: 'attached', timeout: 30000 });
        const titles = await page.evaluate(() => [...document.querySelectorAll('#peb-settings .peb-card:not([data-class-name]) [data-test$="-block"]')]
            .map((block) => block.getAttribute('data-test'))
            .filter((test) => test !== 'title-block' && test !== 'description-block')
            .map((test) => test.replace(/-block$/, '')));
        if (titles[0] !== 'AdminParentModulesSf' || titles[1] !== 'AdminParentThemes') {
            throw new Error(`the cards start with ${titles.slice(0, 2).join(', ')} instead of Modules and Design`);
        }
        await view('4-settings-page');
        await lookChecks();
    });

    // The help blocks of PrestaShop's original homepage, links only.
    await check('the help center', async () => {
        await open(helpUrl);
        await page.waitForFunction(() => document.querySelectorAll('#peb-help a[href*="care-center.prestashop.com"]').length >= 5, null, { timeout: 30000 });
        const outside = await page.evaluate(() => [...document.querySelectorAll('#peb-help a[href^="http"]')]
            .map((anchor) => new URL(anchor.href).hostname)
            .filter((host) => host !== location.hostname && !/(^|\.)prestashop\.com$/.test(host)));
        if (outside.length > 0) {
            throw new Error(`links outside PrestaShop: ${[...new Set(outside)].join(', ')}`);
        }
        await view('6-help-center');
    });

    // Module pages are off the menu until switched on from their settings card.
    await check('a module page switched on shows in the menu', async () => {
        await open(settingsUrl);
        const cards = page.locator('#peb-menu-modules [data-class-name]');
        if (await cards.count() === 0) {
            console.log('no module page on this shop: nothing to switch');
            return;
        }
        // A card whose module has pages of its own opens on their links in place.
        const opening = page.locator('#peb-menu-modules .peb-card__header[aria-expanded]').first();
        if (await opening.count() > 0) {
            const before = page.url();
            await opening.click();
            const pages = opening.locator('xpath=ancestor::*[@data-class-name]').locator('.peb-card__pages a').first();
            if (page.url() !== before || !await pages.isVisible() || (await pages.boundingBox()).height === 0) {
                throw new Error('a module card with pages does not open on them');
            }
            await opening.click();
        }

        const card = cards.first();
        const name = (await card.locator('h1').innerText()).trim();
        // An entry with sub-pages also holds its arrow icon's name: matched by inclusion.
        const inMenu = async () => page.evaluate((label) => [...document.querySelectorAll('#peb-side-bar .peb-side-bar__label')]
            .some((entry) => entry.textContent.replace(/\s+/g, ' ').includes(label)), name);
        if (await inMenu()) {
            throw new Error(`${name} is in the menu before its switch is on`);
        }
        const toggle = card.locator('[role="switch"]');
        await toggle.click();
        await page.waitForFunction((el) => el.getAttribute('aria-checked') === 'true' && !el.hasAttribute('aria-busy'), await toggle.elementHandle(), { timeout: 30000 });
        await page.reload({ waitUntil: 'load' });
        await page.waitForFunction(menuReady, null, { timeout: 30000 });
        if (!await inMenu()) {
            throw new Error(`${name} is not in the menu once switched on`);
        }
        await view('5-module-switched-on');
    });
}

// The same back office on a phone and a tablet: the login page, the menu opened
// from its button, and the settings page with the module cards. Chromium only,
// which is the browser that emulates touch devices.
if (signedIn && browserName === 'chromium') {
    for (const [device, profile] of [['phone', devices['iPhone 13']], ['tablet', devices['iPad (gen 7)']]]) {
        const context = await browser.newContext({ ...profile });
        const small = await context.newPage();
        await check(`${device}: login, menu and settings page`, async () => {
            await small.goto(adminUrl, { waitUntil: 'load' });
            await view(`${device}-0-login`, small);
            await small.locator('input[type="email"]').first().fill(email);
            await small.locator('input[type="password"]').first().fill(password);
            await Promise.all([
                small.waitForLoadState('load'),
                small.locator('form:has(input[type="password"]) button[type="submit"]').first().click(),
            ]);
            await small.waitForFunction(menuReady, null, { timeout: 30000 });

            const wide = await small.evaluate(() => document.documentElement.scrollWidth - innerWidth);
            if (wide > 1) {
                throw new Error(`the page scrolls sideways by ${wide}px`);
            }

            // The button only works once PrestaShop's scripts have bound it, after load.
            const menuIsOpen = () => small.evaluate(() => {
                const menu = document.querySelector('.nav-bar');
                return menu.classList.contains('expanded') && menu.getBoundingClientRect().left >= 0;
            });
            for (let attempt = 0; attempt < 10 && !await menuIsOpen(); ++attempt) {
                await small.waitForTimeout(1000);
                if (!await menuIsOpen()) {
                    await small.locator('.js-mobile-menu').first().click();
                }
            }
            await view(`${device}-1-menu-open`, small);
            if (!await menuIsOpen()) {
                throw new Error('the menu does not open from its button');
            }

            const settings = (await small.evaluate(menuEntries)).map((entry) => entry.href).find((href) => /pseditionbasic\/settings/.test(href));
            if (!settings) {
                throw new Error('no Settings entry in the menu');
            }
            await small.goto(settings, { waitUntil: 'load' });
            await small.locator('#peb-settings').waitFor({ state: 'attached', timeout: 30000 });
            const overflow = await small.evaluate(() => [...document.querySelectorAll('#peb-settings .puik-card')]
                .filter((card) => card.getBoundingClientRect().right > innerWidth + 1).length);
            if (overflow > 0) {
                throw new Error(`${overflow} settings card(s) wider than the screen`);
            }
            await view(`${device}-2-settings`, small);

            const help = (await small.evaluate(menuEntries)).map((entry) => entry.href).find((href) => /pseditionbasic\/help/.test(href));
            if (!help) {
                throw new Error('no Help Center entry in the menu');
            }
            await small.goto(help, { waitUntil: 'load' });
            await small.waitForFunction(() => document.querySelectorAll('#peb-help a[href*="care-center.prestashop.com"]').length >= 5, null, { timeout: 30000 });
            const tooWide = await small.evaluate(() => document.documentElement.scrollWidth - innerWidth);
            if (tooWide > 1) {
                throw new Error(`the help center scrolls sideways by ${tooWide}px`);
            }
            await view(`${device}-3-help-center`, small);
        });
        await context.close();
    }
}

await browser.close();

if (errors.length > 0) {
    console.log('');
    for (const error of errors) {
        console.log(`::error title=UI check::${error}`);
    }
    process.exit(1);
}
