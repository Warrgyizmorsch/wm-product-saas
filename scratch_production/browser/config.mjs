/**
 * Browser settings shared by every headless scratch script.
 *
 * Override any value with an environment variable, e.g.
 *   SCRATCH_BASE_URL=http://localhost/wm-product-saas/public node scratch_production/browser/login.mjs
 *
 * Login details live in login.local.json (git-ignored) or SCRATCH_LOGIN_EMAIL / SCRATCH_LOGIN_PASSWORD.
 * The browser profile (and with it the logged-in session) lives in .chrome-profile/ (git-ignored).
 */
import { existsSync, readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));

function localLogin() {
    const file = join(here, 'login.local.json');
    if (!existsSync(file)) return {};
    try {
        return JSON.parse(readFileSync(file, 'utf8'));
    } catch {
        return {};
    }
}

const login = localLogin();

export const config = {
    /** The URL you open the app at. The session is stored per URL, so log in at the same one. */
    baseUrl: (process.env.SCRATCH_BASE_URL || 'http://localhost:8000').replace(/\/+$/, ''),

    /** Your installed Chrome ("chrome") or Edge ("msedge") — no separate browser download. */
    channel: process.env.SCRATCH_BROWSER || 'chrome',

    /** Dedicated profile folder: log in once, every later script reuses the session. */
    profileDir: process.env.SCRATCH_PROFILE_DIR || join(here, '.chrome-profile'),

    /** Data scripts run hidden unless SCRATCH_HEADED=1 (useful to watch a run). */
    headless: process.env.SCRATCH_HEADED !== '1',

    /** A page that needs a login; used to check the session is still valid. */
    checkPath: '/production/work-centers',

    email: process.env.SCRATCH_LOGIN_EMAIL || login.email || '',
    password: process.env.SCRATCH_LOGIN_PASSWORD || login.password || '',
};
