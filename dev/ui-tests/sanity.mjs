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
import { chromium, firefox } from 'playwright';

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
    await check('the simplified menu', async () => {
        await page.waitForFunction(() => {
            const bar = document.querySelector('wc-side-bar');
            return bar && bar.shadowRoot && bar.shadowRoot.querySelectorAll('a').length > 5;
        }, null, { timeout: 30000 });
        const entries = await page.evaluate(() => [...document.querySelector('wc-side-bar').shadowRoot.querySelectorAll('a')]
            .map((anchor) => ({ text: anchor.textContent.replace(/\s+/g, ' ').trim(), href: anchor.href })));
        const has = (word) => entries.some((entry) => entry.text.includes(word));
        for (const word of ['Dashboard', 'Orders', 'Catalog', 'Settings']) {
            if (!has(word)) {
                throw new Error(`no ${word} entry`);
            }
        }
        if (entries.some((entry) => /\bModule Manager\b|\bTheme & Logo\b/.test(entry.text))) {
            throw new Error('Modules or Design are still in the menu');
        }
        settingsUrl = (entries.find((entry) => /Settings$/.test(entry.text)) || {}).href || '';
        if (!settingsUrl) {
            throw new Error('the Settings entry has no link');
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
        await lookChecks();
    });
}

await browser.close();

if (errors.length > 0) {
    console.log('');
    for (const error of errors) {
        console.log(`::error title=UI check::${error}`);
    }
    process.exit(1);
}
