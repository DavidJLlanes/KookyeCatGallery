// Exercise the real form, the exported PHP contract and admin-ui.js together.
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const fs = require('fs');
const path = require('path');
const assert = require('assert/strict');
const root = path.join(__dirname, '..');
const html = execFileSync('php', ['-r', 'require "inc/site-settings.php"; echo site_settings_form("test", site_settings_defaults(), "design");'], { cwd: root, encoding: 'utf8' });

(async () => {
    const browser = await chromium.launch({ headless: true, executablePath: process.env.TEST_BROWSER || undefined, args: ['--no-sandbox'] });
    try {
        const page = await browser.newPage();
        const errors = [];
        page.on('pageerror', e => errors.push(e.message));
        await page.setContent(html);
        await page.addScriptTag({ content: fs.readFileSync(path.join(root, 'assets/js/admin-ui.js'), 'utf8') });
        const result = await page.evaluate(() => {
            const el = key => document.getElementById('setting-' + key);
            const change = (key, value) => { el(key).value = value; el(key).dispatchEvent(new Event('change', { bubbles: true })); };
            const layouts = [...el('gallery_mobile').options].map(o => o.value);
            const failures = [];
            const check = (key, enabled, label) => {
                if (el(key).disabled === enabled) failures.push(label + '/' + key);
                const submitted = new FormData(el(key).form).has(key);
                if (submitted !== enabled) failures.push('submitted: ' + label + '/' + key);
            };
            for (const mobile of layouts) for (const desktop of layouts) {
                change('gallery_mobile', mobile); change('gallery_desktop', desktop);
                check('grid', [mobile, desktop].some(d => ['grid', 'contact-sheet'].includes(d)), mobile + '+' + desktop);
                for (const [device, design] of [['mobile', mobile], ['desktop', desktop]]) {
                    check('columns_' + device, !['category-rails', 'exhibition', 'triptych'].includes(design), design);
                    check('photos_' + device, design !== 'category-rails', design);
                }
            }
            change('photos_mobile', '0'); change('photos_desktop', '0');
            check('pagination_shape', false, 'all');
            const keys = [...document.querySelectorAll('[data-gallery-control]')].map(e => e.dataset.galleryControl);
            for (const premium of [...el('gallery_premium').options].map(o => o.value).filter(v => v !== 'none')) {
                change('gallery_premium', premium);
                for (const key of keys) check(key, ['bubbles', 'squares'].includes(premium) && key.startsWith('photos_'), premium);
                check('hover', true, premium);
            }
            change('gallery_premium', 'none'); change('gallery_mobile', 'grid'); change('photos_mobile', '8');
            check('pagination_shape', true, 'mobile paged');
            return { failures, mobile: el('photos_mobile').value, desktop: el('photos_desktop').value };
        });
        assert.deepEqual(result.failures, []);
        assert.equal(result.desktop, '0', 'Switching premium must not erase dormant settings.');
        assert.deepEqual(errors, []);
        console.log('Admin options passed: all standard pairs, eight premium galleries, submitted controls and preserved values.');
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
