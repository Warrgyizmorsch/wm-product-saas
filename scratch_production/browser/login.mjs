/**
 * login.mjs — optional. Data scripts log in by themselves (openApp() in session.mjs); use this only to check
 * or reset the scratch session by hand.
 *
 *   node scratch_production/browser/login.mjs            log in now (skips if the session is still valid)
 *   node scratch_production/browser/login.mjs --check    only report whether the session is valid
 *   node scratch_production/browser/login.mjs --fresh    forget the session and log in again
 *   node scratch_production/browser/login.mjs --logout   forget the session only
 *
 * Which account: login.local.json (git-ignored) or SCRATCH_LOGIN_EMAIL / SCRATCH_LOGIN_PASSWORD.
 */
import { config } from './config.mjs';
import { launch, isLoggedIn, login } from './session.mjs';

const args = new Set(process.argv.slice(2));

async function main() {
    const context = await launch({ headless: true });
    try {
        if (args.has('--fresh') || args.has('--logout')) {
            await context.clearCookies();
            if (args.has('--logout')) {
                console.log('Scratch session cleared.');
                return;
            }
        }

        const valid = await isLoggedIn(context);
        if (args.has('--check')) {
            console.log(valid ? `Session valid for ${config.baseUrl}` : `Not logged in at ${config.baseUrl} (scripts will log in by themselves)`);
            process.exitCode = valid ? 0 : 1;
            return;
        }
        if (valid) {
            console.log(`Already logged in at ${config.baseUrl}.`);
            return;
        }

        await login(context);
        console.log(`Logged in as ${config.email} at ${config.baseUrl}.`);
    } finally {
        await context.close();
    }
}

main().catch((e) => {
    console.error(e.message || e);
    process.exit(1);
});
