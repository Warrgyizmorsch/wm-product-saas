/**
 * Opens the app in the scratch browser profile, logged in.
 *
 *   import { openApp } from './browser/session.mjs';
 *   const app = await openApp();              // logs in by itself if the session is missing or expired
 *   const page = await app.newTab('/production/routing/create');
 *   ...
 *   await app.close();
 *
 * The profile is a separate Chrome user-data folder (.chrome-profile/) started as its own Chrome process —
 * it never touches your normal Chrome profiles or Guest windows.
 */
import { chromium } from 'playwright-core';
import { config } from './config.mjs';

export function url(path = '/') {
    return config.baseUrl + (path.startsWith('/') ? path : '/' + path);
}

/** True when the page ended up on the login screen. */
export function isLoginPage(page) {
    return /\/login\/?$/.test(new URL(page.url()).pathname);
}

export async function launch({ headless = config.headless } = {}) {
    try {
        return await chromium.launchPersistentContext(config.profileDir, {
            channel: config.channel,
            headless,
            viewport: { width: 1440, height: 900 },
            args: ['--no-first-run', '--no-default-browser-check'],
        });
    } catch (e) {
        if (/ProcessSingleton|user data directory is already in use|lock/i.test(String(e))) {
            throw new Error(`Another scratch script is using the profile (${config.profileDir}). Wait for it to finish, then run again.`);
        }
        throw e;
    }
}

/** Is the profile logged in? */
export async function isLoggedIn(context) {
    const page = await context.newPage();
    try {
        await page.goto(url(config.checkPath), { waitUntil: 'domcontentloaded' });
        return !isLoginPage(page);
    } finally {
        await page.close();
    }
}

/** Fills the real login form with the account from login.local.json / env. */
export async function login(context) {
    if (!config.email || !config.password) {
        throw new Error('No login details — fill scratch_production/browser/login.local.json (email, password).');
    }
    const page = await context.newPage();
    try {
        await page.goto(url('/login'), { waitUntil: 'domcontentloaded' });
        if (!isLoginPage(page)) return;   // already logged in
        await page.fill('input[name="email"]', config.email);
        await page.fill('input[name="password"]', config.password);
        await Promise.all([
            page.waitForNavigation({ waitUntil: 'domcontentloaded' }).catch(() => {}),
            page.click('button[type="submit"]'),
        ]);
        if (isLoginPage(page)) {
            const msg = (await page.locator('.invalid-feedback, .alert-danger, .text-danger').allInnerTexts()).join(' ').trim();
            throw new Error('Login failed' + (msg ? `: ${msg}` : ' — check login.local.json'));
        }
    } finally {
        await page.close();
    }
    if (!(await isLoggedIn(context))) {
        throw new Error('Login submitted, but the session check failed.');
    }
}

export async function openApp(options = {}) {
    const context = await launch(options);

    try {
        if (!(await isLoggedIn(context))) {
            await login(context);
            console.log(`(logged in as ${config.email})`);
        }
    } catch (e) {
        await context.close();
        throw e;
    }

    // Close the blank tab Chrome opens with a persistent profile.
    for (const p of context.pages()) {
        if (p.url() === 'about:blank') await p.close().catch(() => {});
    }

    return {
        context,
        /** Opens a new tab at an app path; logs in again if the session expired mid-run. */
        async newTab(path = '/') {
            const page = await context.newPage();
            await page.goto(url(path), { waitUntil: 'domcontentloaded' });
            if (isLoginPage(page)) {
                await login(context);
                await page.goto(url(path), { waitUntil: 'domcontentloaded' });
            }
            return page;
        },
        close: () => context.close(),
    };
}
