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

// What the module must have done to the page in front of the browser.
const lookChecks = async () => {
    const state = await page.evaluate(() => {
        const sheets = [...document.querySelectorAll('link[rel="stylesheet"]')].map((link) => link.getAttribute('href') || '');
        const notice = document.getElementById('non-responsive');
        const wordIcons = [...document.querySelectorAll('.material-icons')]
            .filter((icon) => icon.offsetParent && icon.children.length === 0
                && icon.getBoundingClientRect().width > parseFloat(getComputedStyle(icon).fontSize) * 1.6)
            .map((icon) => icon.textContent.trim());
        const head = document.querySelector('#content.bootstrap .page-head');
        let gap = null;
        if (head && getComputedStyle(head).position === 'fixed' && !document.body.classList.contains('display-modal')) {
            const bottom = head.getBoundingClientRect().bottom;
            const blocks = [...document.querySelectorAll('#content.bootstrap .panel, #content.bootstrap .alert, #content.bootstrap form, #content.bootstrap .row')]
                .filter((block) => !block.closest('.page-head') && block.getBoundingClientRect().height > 10)
                .map((block) => block.getBoundingClientRect().top);
            if (blocks.length) {
                gap = Math.round(Math.min(...blocks) - bottom);
            }
        }
        return { sheets, notice: notice ? getComputedStyle(notice).display : 'none', wordIcons, gap };
    });
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
            throw new Error(`the first block starts ${state.gap}px under the page header`);
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
        await page.locator('wc-side-bar').waitFor({ state: 'attached', timeout: 30000 });
    });

    let settingsUrl = '';
    let helpUrl = '';
    await check('the simplified menu', async () => {
        await page.waitForFunction(() => {
            const bar = document.querySelector('wc-side-bar');
            return bar && bar.shadowRoot && bar.shadowRoot.querySelectorAll('a').length > 5;
        }, null, { timeout: 30000 });
        const entries = await page.evaluate(() => [...document.querySelector('wc-side-bar').shadowRoot.querySelectorAll('a')]
            .map((anchor) => ({ text: anchor.textContent.replace(/\s+/g, ' ').trim(), href: anchor.href })));
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
        const orders = entries.find((entry) => /Orders$/.test(entry.text));
        if (orders && orders.href) {
            await open(orders.href);
            await view('3-orders-simplified-menu');
        }
    });

    await check('the settings page', async () => {
        await open(settingsUrl);
        await page.locator('#vue-app').waitFor({ state: 'attached', timeout: 30000 });
        await page.waitForFunction(() => document.querySelectorAll('#vue-app a[href]').length >= 7, null, { timeout: 30000 });
        const titles = await page.evaluate(() => JSON.parse(document.querySelector('#vue-app').dataset.context).tabs.map((tab) => tab.class_name));
        if (titles[0] !== 'AdminParentModulesSf' || titles[1] !== 'AdminParentThemes') {
            throw new Error(`the cards start with ${titles.slice(0, 2).join(', ')} instead of Modules and Design`);
        }
        await view('4-settings-page');
        await lookChecks();
    });

    // The help blocks of PrestaShop's original homepage, links only.
    await check('the help center', async () => {
        await open(helpUrl);
        await page.waitForFunction(() => document.querySelectorAll('#vue-app a[href*="care-center.prestashop.com"]').length >= 5, null, { timeout: 30000 });
        const outside = await page.evaluate(() => [...document.querySelectorAll('#vue-app a[href^="http"]')]
            .map((anchor) => new URL(anchor.href).hostname)
            .filter((host) => host !== location.hostname && !/(^|\.)prestashop\.com$/.test(host)));
        if (outside.length > 0) {
            throw new Error(`links outside PrestaShop: ${[...new Set(outside)].join(', ')}`);
        }
        await view('6-help-center');
    });

    // Module pages are off the menu until switched on from their settings card.
    await check('a module page switched on shows in the menu', async () => {
        const cards = page.locator('#peb-menu-modules [data-class-name]');
        await page.waitForFunction(() => {
            const section = document.getElementById('peb-menu-modules');
            return !section || (!section.hidden && section.closest('#vue-app'));
        }, null, { timeout: 30000 });
        if (await cards.count() === 0) {
            console.log('no module page on this shop: nothing to switch');
            return;
        }
        const card = cards.first();
        const name = (await card.locator('h1').innerText()).trim();
        // An entry with sub-pages also holds its arrow icon's name: matched by inclusion.
        const inMenu = async () => page.evaluate((label) => [...document.querySelector('wc-side-bar').shadowRoot.querySelectorAll('a')]
            .some((anchor) => anchor.textContent.replace(/\s+/g, ' ').includes(label)), name);
        if (await inMenu()) {
            throw new Error(`${name} is in the menu before its switch is on`);
        }
        const toggle = card.locator('[role="switch"]');
        await toggle.click();
        await page.waitForFunction((el) => el.getAttribute('aria-checked') === 'true' && !el.hasAttribute('aria-busy'), await toggle.elementHandle(), { timeout: 30000 });
        await page.reload({ waitUntil: 'load' });
        await page.waitForFunction(() => document.querySelector('wc-side-bar')?.shadowRoot?.querySelectorAll('a').length > 5, null, { timeout: 30000 });
        if (!await inMenu()) {
            throw new Error(`${name} is not in the menu once switched on`);
        }
        await page.waitForFunction(() => !document.getElementById('peb-menu-modules').hidden, null, { timeout: 30000 });
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
            await small.waitForFunction(() => document.querySelector('wc-side-bar')?.shadowRoot?.querySelectorAll('a').length > 5, null, { timeout: 30000 });

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

            const settings = await small.evaluate(() => [...document.querySelector('wc-side-bar').shadowRoot.querySelectorAll('a')]
                .map((anchor) => anchor.href).find((href) => /pseditionbasic\/settings/.test(href)));
            if (!settings) {
                throw new Error('no Settings entry in the menu');
            }
            await small.goto(settings, { waitUntil: 'load' });
            await small.waitForFunction(() => document.querySelectorAll('#vue-app a[href]').length >= 7, null, { timeout: 30000 });
            const overflow = await small.evaluate(() => [...document.querySelectorAll('#vue-app .puik-card')]
                .filter((card) => card.getBoundingClientRect().right > innerWidth + 1).length);
            if (overflow > 0) {
                throw new Error(`${overflow} settings card(s) wider than the screen`);
            }
            await view(`${device}-2-settings`, small);

            const help = await small.evaluate(() => [...document.querySelector('wc-side-bar').shadowRoot.querySelectorAll('a')]
                .map((anchor) => anchor.href).find((href) => /pseditionbasic\/help/.test(href)));
            if (!help) {
                throw new Error('no Help Center entry in the menu');
            }
            await small.goto(help, { waitUntil: 'load' });
            await small.waitForFunction(() => document.querySelectorAll('#vue-app a[href*="care-center.prestashop.com"]').length >= 5, null, { timeout: 30000 });
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
