const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
const assert = require('node:assert/strict');

(async () => {
    const root = path.join(__dirname, '..');
    const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
    let count = 0;
    let liked = false;
    try {
        const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        await page.route('**/hearts.php**', async route => {
            const request = route.request();
            if (request.method() === 'POST') {
                const form = new URLSearchParams(request.postData() || '');
                if (form.get('slug') !== 'test-photo') throw new Error('Unexpected slug');
                if (form.get('action') === 'like' && !liked) { liked = true; count++; }
                if (form.get('action') === 'unlike' && liked) { liked = false; count--; }
                await route.fulfill({ status: 200, contentType: 'application/json',
                    body: JSON.stringify({ ok: true, slug: 'test-photo', count, liked }) });
                return;
            }
            await route.fulfill({ status: 200, contentType: 'application/json',
                body: JSON.stringify({ ok: true, counts: { 'test-photo': count }, liked: { 'test-photo': liked } }) });
        });
        await page.route('https://davidjimenezllanes.es/foto/test-photo', route => route.fulfill({
            status: 200, contentType: 'text/html',
            body: '<!doctype html><html lang="es"><head><meta charset="utf-8"></head><body>' +
                '<section class="photo-detail"><div class="photo-detail__info"><div class="photo-app-actions">' +
                '<button class="photo-app-action" type="button" data-favorite-photo="test-photo" aria-pressed="false">♡ Favorita</button>' +
                '<button class="photo-app-action photo-heart" type="button" data-heart-photo="test-photo" aria-pressed="false">♡ <span data-heart-count>0</span></button>' +
                '</div></div></section>' +
                '<span class="card__heart-count" data-heart-display="test-photo">♡ <span data-heart-count>0</span></span>' +
                '</body></html>'
        }));
        await page.goto('https://davidjimenezllanes.es/foto/test-photo');
        await page.addStyleTag({ content: fs.readFileSync(path.join(root, 'assets/css/style.css'), 'utf8') });
        const initialCounts = page.waitForResponse(response => response.url().includes('/hearts.php?slugs='));
        await page.addScriptTag({ content: fs.readFileSync(path.join(root, 'assets/js/main.js'), 'utf8') });
        await initialCounts;
        await page.waitForFunction(() => document.querySelector('[data-heart-photo] [data-heart-count]')?.textContent === '0');
        await page.locator('[data-favorite-photo]').click();
        await page.waitForFunction(() => document.querySelector('[data-heart-photo] [data-heart-count]')?.textContent === '1');
        assert.equal(await page.locator('[data-heart-display] [data-heart-count]').textContent(), '1');
        assert.equal(await page.locator('[data-favorite-photo]').getAttribute('aria-pressed'), 'true');
        assert.equal(await page.locator('[data-heart-photo]').getAttribute('aria-pressed'), 'true');
        assert.equal(await page.locator('[data-heart-display]').evaluate(el => getComputedStyle(el).color), 'rgb(229, 57, 53)');
        const position = await page.locator('.photo-app-actions').evaluate(el => {
            const box = el.getBoundingClientRect();
            const children = [...el.querySelectorAll('button')].map(button => button.getBoundingClientRect());
            return { center: box.left + box.width / 2,
                controls: (children[0].left + children[1].right) / 2 };
        });
        assert.ok(Math.abs(position.center - position.controls) < 2, 'Desktop actions are not centered');
        await page.locator('[data-heart-photo]').click();
        await page.waitForFunction(() => document.querySelector('[data-heart-photo] [data-heart-count]')?.textContent === '0');
        assert.equal(await page.locator('[data-heart-display] [data-heart-count]').textContent(), '0');
        assert.equal(await page.locator('[data-favorite-photo]').getAttribute('aria-pressed'), 'false');
        assert.equal(await page.locator('[data-heart-photo]').getAttribute('aria-pressed'), 'false');
        assert.deepEqual(errors, []);
        console.log('Photo likes and favorites: passed');
    } finally {
        await browser.close();
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
