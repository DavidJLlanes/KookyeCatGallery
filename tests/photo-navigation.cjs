const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const fs = require('fs');
const os = require('os');
const path = require('path');
const zlib = require('zlib');
const assert = require('assert/strict');

// Local fixtures and route interception exercise the actual PHP and JS without external services.
function testImage() {
    const crc = (bytes) => {
        let value = 0xffffffff;
        for (const byte of bytes) {
            value ^= byte;
            for (let i = 0; i < 8; i++) value = (value >>> 1) ^ ((value & 1) ? 0xedb88320 : 0);
        }
        return (value ^ 0xffffffff) >>> 0;
    };
    const chunk = (type, data) => {
        const bytes = Buffer.concat([Buffer.from(type), data]);
        const size = Buffer.alloc(4); size.writeUInt32BE(data.length);
        const checksum = Buffer.alloc(4); checksum.writeUInt32BE(crc(bytes));
        return Buffer.concat([size, bytes, checksum]);
    };
    const header = Buffer.alloc(13);
    header.writeUInt32BE(640, 0); header.writeUInt32BE(400, 4); header[8] = 8; header[9] = 2;
    const pixels = Buffer.alloc((640 * 3 + 1) * 400, 150);
    for (let i = 0; i < 400; i++) pixels[i * (640 * 3 + 1)] = 0;
    return Buffer.concat([Buffer.from('89504e470d0a1a0a', 'hex'), chunk('IHDR', header), chunk('IDAT', zlib.deflateSync(pixels)), chunk('IEND', Buffer.alloc(0))]);
}

(async () => {
    const root = path.join(__dirname, '..');
    const snapshot = fs.mkdtempSync(path.join(os.tmpdir(), 'photo-navigation-'));
    let browser;
    try {
        fs.cpSync(root, snapshot, { recursive: true, filter: (source) => !/(?:^|\/)(?:\.git|node_modules)(?:\/|$)/.test(source) });
        for (const directory of ['img', 'imagenes/desktop', 'imagenes/mobile', 'data']) fs.mkdirSync(path.join(snapshot, directory), { recursive: true });
        const image = testImage();
        const cache = {};
        for (const [i, slug] of ['first', 'middle', 'last'].entries()) {
            const filename = `${slug}.png`;
            const original = path.join(snapshot, 'img', filename);
            fs.writeFileSync(original, image);
            const mtime = Math.floor(Date.now() / 1000) - i * 10;
            fs.utimesSync(original, mtime, mtime);
            for (const size of ['desktop', 'mobile']) fs.writeFileSync(path.join(snapshot, 'imagenes', size, `${slug}.webp`), image);
            fs.writeFileSync(path.join(snapshot, 'img', `${slug}.txt`), `${slug}\n---\nDescription ${slug}\n# Slug: ${slug}\n# Categoría: Test\n`);
            cache[filename] = { mtime, edit_mtime: 0, edit_size: 0, desktop_w: 640, desktop_h: 400, mobile_w: 640, mobile_h: 400, aspect: 1.6 };
        }
        fs.writeFileSync(path.join(snapshot, 'data/cache.json'), JSON.stringify(cache));
        const render = (url) => execFileSync('php', ['-r', '$_SERVER["REQUEST_URI"]=$argv[1]; require "index.php";', url], { cwd: snapshot, encoding: 'utf8' });
        browser = await chromium.launch({ headless: true });
        const origin = 'http://photo.test';
        const makePage = async (options) => {
            const context = await browser.newContext(options);
            const page = await context.newPage();
            const errors = [];
            page.on('pageerror', (error) => errors.push(error.message));
            await page.route('**/*', async (route) => {
                const url = new URL(route.request().url());
                if (url.origin !== origin) return route.abort();
                if (url.pathname.startsWith('/foto/') || url.pathname === '/') return route.fulfill({ contentType: 'text/html', body: render(url.pathname + url.search) });
                const file = path.join(snapshot, url.pathname);
                if (!fs.existsSync(file) || !fs.statSync(file).isFile()) return route.fulfill({ status: 404, body: '' });
                const contentType = file.endsWith('.css') ? 'text/css' : file.endsWith('.js') ? 'application/javascript' : file.endsWith('.webp') ? 'image/png' : 'application/octet-stream';
                return route.fulfill({ contentType, body: fs.readFileSync(file) });
            });
            return { context, page, errors };
        };
        const title = async (page, expected) => {
            assert.equal(await page.locator('.photo-detail__title').textContent(), expected);
            assert.equal(await page.locator('link[rel="canonical"]').getAttribute('href'), `https://davidjimenezllanes.es/foto/${expected}`);
            assert.equal(await page.locator('.photo-detail__desc').textContent(), `Description ${expected}`);
        };
        const waitPhoto = async (page, slug) => {
            await page.waitForURL((url) => url.pathname === `/foto/${slug}`);
            await page.waitForLoadState('load');
            await title(page, slug);
        };
        const desktop = await makePage({ viewport: { width: 1280, height: 900 }, reducedMotion: 'reduce' });
        await desktop.page.goto(`${origin}/foto/middle`);
        await desktop.page.locator('.photo-detail__nav--next').click();
        await waitPhoto(desktop.page, 'last');
        assert.equal(await desktop.page.locator('.photo-detail__nav--next').getAttribute('aria-disabled'), 'true');
        await desktop.page.locator('.photo-detail__nav--previous').click();
        await waitPhoto(desktop.page, 'middle');
        // A mouse drag goes to the previous photo and does not open the zoom viewer.
        const imageBounds = await desktop.page.locator('.photo-detail__img').boundingBox();
        const center = { x: imageBounds.x + imageBounds.width / 2, y: imageBounds.y + imageBounds.height / 2 };
        await desktop.page.mouse.move(center.x, center.y);
        await desktop.page.mouse.down();
        await desktop.page.mouse.move(center.x + 100, center.y, { steps: 5 });
        await desktop.page.mouse.up();
        await waitPhoto(desktop.page, 'first');
        assert.equal(await desktop.page.locator('#lightbox').getAttribute('aria-hidden'), 'true');
        assert.equal(await desktop.page.locator('.photo-detail__nav--previous').getAttribute('aria-disabled'), 'true');
        await desktop.page.keyboard.press('ArrowRight');
        await waitPhoto(desktop.page, 'middle');
        await desktop.page.evaluate(() => { document.fullscreenEnabled = false; document.getElementById('lightbox').requestFullscreen = undefined; });
        await desktop.page.locator('.photo-detail__zoom').click();
        assert.equal(await desktop.page.locator('#lightbox').getAttribute('aria-hidden'), 'false');
        await desktop.page.locator('.lightbox__nav--next').click();
        await waitPhoto(desktop.page, 'last');
        assert.equal(new URL(desktop.page.url()).searchParams.get('viewer'), '1');
        assert.equal(await desktop.page.locator('#lightbox').getAttribute('aria-hidden'), 'false');
        assert.equal(await desktop.page.locator('.lightbox__nav--next').isDisabled(), true);
        await desktop.page.locator('.lightbox__close').click();
        assert.equal(await desktop.page.locator('#lightbox').getAttribute('aria-hidden'), 'true');
        assert.equal(new URL(desktop.page.url()).searchParams.has('viewer'), false);

        const mobile = await makePage({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true, reducedMotion: 'reduce' });
        const cdp = await mobile.context.newCDPSession(mobile.page);
        const swipe = async (dx, dy, fullscreen = false) => {
            const box = await mobile.page.locator(fullscreen ? '.lightbox__img' : '.photo-detail__img').boundingBox();
            const x = box.x + box.width / 2, y = box.y + box.height / 2;
            await cdp.send('Input.dispatchTouchEvent', { type: 'touchStart', touchPoints: [{ x, y }] });
            await cdp.send('Input.dispatchTouchEvent', { type: 'touchMove', touchPoints: [{ x: x + dx, y: y + dy }] });
            await cdp.send('Input.dispatchTouchEvent', { type: 'touchEnd', touchPoints: [] });
        };
        await mobile.page.goto(`${origin}/foto/middle`);
        await swipe(-100, 2);
        await waitPhoto(mobile.page, 'last');
        await swipe(100, 2);
        await waitPhoto(mobile.page, 'middle');
        await swipe(-5, 80);
        assert.equal(new URL(mobile.page.url()).pathname, '/foto/middle', 'Vertical scroll navigated');
        await mobile.page.goto(`${origin}/foto/middle?viewer=1`);
        await swipe(-100, 2, true);
        await waitPhoto(mobile.page, 'last');
        assert.equal(await mobile.page.locator('#lightbox').getAttribute('aria-hidden'), 'false');
        await swipe(100, 2, true);
        await waitPhoto(mobile.page, 'middle');
        assert.equal(await mobile.page.locator('#lightbox').getAttribute('aria-hidden'), 'false');
        assert.deepEqual(desktop.errors, []);
        assert.deepEqual(mobile.errors, []);
        console.log('Photo navigation passed: mouse buttons/drag, keyboard, real touch gestures, fullscreen continuity, boundaries and fresh page metadata.');
    } finally {
        if (browser) await browser.close();
        fs.rmSync(snapshot, { recursive: true, force: true });
    }
})().catch((error) => { console.error(error); process.exit(1); });
