import fs from 'node:fs';
import path from 'node:path';
import puppeteer from 'puppeteer-core';

const BASE = process.env.APP_URL || 'http://sgc-smarttrack-prod.test';
const OUT = path.resolve('public/assets/docs');
const CHROME = process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const PASSWORD = process.env.DOCS_CAPTURE_PASSWORD || 'SmartTrack2026';

const accounts = {
    super: 'romer.necesario@sgcsmarttrack.gov.ph',
    division: 'jovel.oberio@deped.gov.ph',
    school_head: 'school.head@deped.gov.ph',
    school: 'school.encoder@deped.gov.ph',
};

fs.mkdirSync(OUT, { recursive: true });

const delay = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

const shot = async (page, name) => {
    if (page.url().includes('/login')) {
        throw new Error(`Refusing to snapshot login as ${name}`);
    }

    await page.waitForSelector('.app', { timeout: 20000 });
    await delay(600);
    const file = path.join(OUT, `${name}.png`);
    await page.screenshot({ path: file, fullPage: true, type: 'png' });
    console.log('saved', name);
};

const fillInput = async (page, selector, value) => {
    await page.waitForSelector(selector);
    await page.$eval(
        selector,
        (el, next) => {
            const setter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value')?.set;
            setter?.call(el, next);
            el.dispatchEvent(new Event('input', { bubbles: true }));
            el.dispatchEvent(new Event('change', { bubbles: true }));
        },
        value,
    );
};

const login = async (page, email) => {
    await page.goto(`${BASE}/login`, { waitUntil: 'networkidle0' });
    await fillInput(page, '#email', email);
    await fillInput(page, '#password', PASSWORD);
    await page.click('button[type="submit"]');
    await page.waitForSelector('.app', { timeout: 20000 });

    if (page.url().includes('/login')) {
        throw new Error(`Login failed for ${email}`);
    }
};

const open = async (page, href) => {
    const url = href.startsWith('http') ? href : `${BASE}${href}`;
    await page.goto(url, { waitUntil: 'networkidle0' });
    await delay(400);
};

const roles = {
    super: async (page) => {
        await shot(page, 'super-overview');
        await open(page, '/super/divisions');
        await shot(page, 'super-divisions');
        await open(page, '/super/users');
        await shot(page, 'super-users');
        await open(page, '/super/cycles');
        await shot(page, 'super-cycles');
        await open(page, '/account');
        await shot(page, 'account');
    },
    division: async (page) => {
        await shot(page, 'division-overview');
        await open(page, '/division/queue');
        await shot(page, 'division-queue');
        const reviewHref = await page
            .$eval('a.btn[href*="/division/queue/"]', (el) => el.getAttribute('href') || '')
            .catch(() => '');
        if (reviewHref) {
            await open(page, reviewHref);
            await shot(page, 'division-review');
        }
        await open(page, '/division/schools');
        await shot(page, 'division-schools');
        await open(page, '/division/registrations');
        await shot(page, 'division-registrations');
        await open(page, '/division/alerts');
        await shot(page, 'division-alerts');
    },
    school_head: async (page) => {
        await shot(page, 'head-dashboard');
        await open(page, '/school/assessment');
        await shot(page, 'head-assessment');
        await open(page, '/school/movs');
        await shot(page, 'head-movs');
        await open(page, '/school/submit');
        await shot(page, 'head-submit');
        await open(page, '/school/encoders');
        await shot(page, 'head-encoders');
        await open(page, '/school/notifications');
        await shot(page, 'head-notifications');
    },
    school: async (page) => {
        await shot(page, 'encoder-dashboard');
        await open(page, '/school/assessment');
        await shot(page, 'encoder-assessment');
        await open(page, '/school/movs');
        await shot(page, 'encoder-movs');
        await open(page, '/school/submit');
        await shot(page, 'encoder-submit');
        await open(page, '/school/notifications');
        await shot(page, 'encoder-notifications');
    },
};

const withRole = async (browser, role) => {
    const context = await browser.createBrowserContext();
    const page = await context.newPage();
    await page.setViewport({ width: 1440, height: 900 });
    await login(page, accounts[role]);
    await roles[role](page);
    await context.close();
};

const browser = await puppeteer.launch({
    executablePath: CHROME,
    headless: 'new',
    defaultViewport: { width: 1440, height: 900 },
    args: ['--window-size=1440,900'],
});

const selected = process.argv.slice(2);
const queue = selected.length ? selected : Object.keys(roles);

try {
    for (const role of queue) {
        if (!roles[role]) {
            throw new Error(`Unknown role ${role}`);
        }
        await withRole(browser, role);
    }
} finally {
    await browser.close();
}
