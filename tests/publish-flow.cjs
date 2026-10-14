// Flujo completo de publicar una foto contra una instalación nueva (copia exportada del núcleo): subir, editar, publicar, volver a la
// biblioteca, ver la portada y la ficha, y la carga por lotes. Ninguna respuesta puede ser un error del servidor y la foto debe quedar
// publicada en WebP con el lado mayor en 1800 px como máximo. Lo que se ve en el navegador (pantalla en blanco, error 500) sale de aquí.
'use strict';
const fs = require('fs');
const os = require('os');
const path = require('path');
const assert = require('node:assert/strict');
const { spawn, execFileSync } = require('child_process');

const root = path.join(__dirname, '..');
const php = process.env.PHP || 'php';
const tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'publish-flow-'));
const site = path.join(tmp, 'site');
const privateDir = path.join(tmp, 'private');
const errorLog = path.join(tmp, 'php-errors.log');
const port = 8300 + Math.floor(Math.random() * 90);
const base = `http://127.0.0.1:${port}`;
let server = null;
let cookie = '';

async function send(url, { method = 'GET', form = null, multipart = null } = {}) {
    const headers = cookie ? { cookie } : {};
    let body;
    if (form) { headers['content-type'] = 'application/x-www-form-urlencoded'; body = new URLSearchParams(form).toString(); }
    if (multipart) body = multipart;
    const response = await fetch(base + url, { method, redirect: 'manual', headers, body });
    const set = (response.headers.getSetCookie ? response.headers.getSetCookie() : []).map(c => c.split(';')[0]).join('; ');
    if (set) cookie = set;
    return { status: response.status, location: response.headers.get('location') || '', text: await response.text() };
}
const csrfOf = html => (html.match(/name="csrf" value="([^"]+)"/) || html.match(/csrf['"]?\s*[:=]\s*['"]([a-f0-9]{20,})/) || [])[1];
const phpErrors = () => (fs.existsSync(errorLog) ? fs.readFileSync(errorLog, 'utf8') : '').split('\n').filter(line => line && !/openssl/i.test(line)).join('\n');

(async () => {
    try {
        execFileSync(php, [path.join(root, 'scripts/export-core.php'), site, 'prueba', '--produccion'], { stdio: 'pipe' });
        const jpeg = (name, w, h) => {
            const file = path.join(tmp, name).split(path.sep).join('/');
            execFileSync(php, ['-r', `$i=imagecreatetruecolor(${w},${h}); imagefill($i,0,0,imagecolorallocate($i,90,120,200)); imagejpeg($i,'${file}',90);`], { stdio: 'pipe' });
            return new Blob([fs.readFileSync(file)], { type: 'image/jpeg' });
        };
        const env = { ...process.env, GALLERY_PRIVATE_DIR: privateDir, SITE_CONFIG_DIR: '', MICROBLOG_DATA_DIR: '', GALLERY_PUBLIC_URL: '' };
        server = spawn(php, ['-d', 'display_errors=0', '-d', 'log_errors=1', '-d', 'error_log=' + errorLog.split(path.sep).join('/'), '-S', `127.0.0.1:${port}`, '-t', site], { env, stdio: 'ignore' });
        for (let i = 0; i < 60; i++) { try { if ((await fetch(base + '/install.php')).status) break; } catch (_) { /* arrancando */ } await new Promise(r => setTimeout(r, 250)); }

        let page = await send('/install.php');
        await send('/install.php', { method: 'POST', form: { csrf: csrfOf(page.text), url: 'https://prueba.example', username: 'admin', password: 'contrasena-de-prueba-1' } });
        cookie = '';
        page = await send('/admin.php');
        const login = await send('/admin.php', { method: 'POST', form: { action: 'login', csrf: csrfOf(page.text), username: 'admin', password: 'contrasena-de-prueba-1' } });
        assert.equal(login.status, 303, 'El inicio de sesión redirige.');
        page = await send('/admin.php');
        const csrf = csrfOf(page.text);
        assert.ok(csrf, 'Con sesión el panel trae su token.');

        // Publicar una foto: inspeccionar, guardar la versión editada y completar.
        const photo = jpeg('foto.jpg', 3000, 2000);
        let form = new FormData(); form.append('action', 'inspect'); form.append('csrf', csrf); form.append('photo', photo, 'foto.jpg');
        let r = await send('/admin.php', { method: 'POST', multipart: form });
        assert.equal(r.status, 200, 'inspect: ' + r.text.slice(0, 160));
        form = new FormData(); form.append('action', 'save_edit'); form.append('csrf', csrf); form.append('edited_photo', photo, 'foto-editada.jpg');
        r = await send('/admin.php', { method: 'POST', multipart: form });
        assert.equal(r.status, 200, 'save_edit: ' + r.text.slice(0, 160));
        r = await send('/admin.php', { method: 'POST', form: { action: 'complete', csrf, title: 'Mi foto de prueba', category: 'Viajes', description: '', latitude: '', longitude: '', pinned: '1', featured: '1' } });
        assert.equal(r.status, 303, 'complete debe redirigir (salió ' + r.status + '): ' + r.text.slice(0, 200));
        assert.match(r.location, /library=1&published=/, 'Tras publicar se vuelve a la biblioteca.');
        const library = await send(r.location);
        assert.equal(library.status, 200, 'La biblioteca tras publicar debe abrir (salió ' + library.status + ').');
        assert.ok(library.text.includes('Mi foto de prueba') && library.text.includes('is-pinned') && library.text.includes('is-featured'), 'La foto sale en la biblioteca, fijada y destacada.');

        // Carga por lotes.
        form = new FormData(); form.append('action', 'batch_upload'); form.append('csrf', csrf); form.append('category', 'Lote'); form.append('draft', '0'); form.append('photo', jpeg('lote.jpg', 2400, 1600), 'lote.jpg');
        r = await send('/admin.php', { method: 'POST', multipart: form });
        assert.equal(r.status, 200, 'batch_upload: ' + r.text.slice(0, 200));

        // Portada, ficha y versiones publicadas.
        const home = await send('/');
        assert.equal(home.status, 200, 'La portada abre.');
        assert.ok(home.text.includes('Mi foto de prueba') && home.text.includes('card--pinned') && home.text.includes('card__badge'), 'La portada muestra la foto, fijada y con distintivo.');
        const desktop = fs.readdirSync(path.join(site, 'imagenes/desktop')).filter(f => f.endsWith('.webp'));
        assert.ok(desktop.length >= 2, 'Las versiones WebP existen desde que se publica (hay ' + desktop.length + ').');
        for (const file of desktop) {
            const info = execFileSync(php, ['-r', `$s=getimagesize('${path.join(site, 'imagenes/desktop', file).split(path.sep).join('/')}'); echo $s[0].' '.$s[1].' '.$s[2];`]).toString().match(/(\d+) (\d+) (\d+)\s*$/).slice(1).map(Number);
            assert.equal(info[2], 18, file + ' es WebP.');
            assert.ok(Math.max(info[0], info[1]) <= 1800, file + ' no pasa de 1800 px.');
        }
        const slug = (home.text.match(/\/foto\/([a-z0-9-]+)"/) || [])[1];
        assert.ok(slug, 'La portada enlaza a la ficha.');
        const detail = await send('/foto/' + slug);
        assert.equal(detail.status, 200, 'La ficha abre.');
        // Todas las pantallas del panel abren y todo lo que enlazan (scripts, estilos) existe en el paquete.
        const firstPhoto = fs.readdirSync(path.join(site, 'img')).find(f => f.endsWith('.jpg'));
        const pages = ['', '?settings=1', '?settings=1&section=content', '?settings=1&section=design', '?settings=1&section=texts', '?settings=1&section=social',
            '?settings=1&section=publication', '?settings=1&section=page&doc=project', '?settings=1&section=page&doc=legal_notice', '?ai=1', '?library=1', '?categories=1',
            '?microblog=1', '?edit=' + encodeURIComponent(firstPhoto)];
        const assets = new Set();
        for (const query of pages) {
            const screen = await send('/admin.php' + query);
            assert.equal(screen.status, 200, 'La pantalla del panel «' + (query || 'inicio') + '» debe abrir (salió ' + screen.status + ').');
            for (const match of screen.text.matchAll(/(?:src|href)="(\/assets\/[^"?#]+)/g)) assets.add(match[1]);
        }
        for (const match of home.text.matchAll(/(?:src|href)="(\/assets\/[^"?#]+)/g)) assets.add(match[1]);
        for (const asset of assets) assert.ok(fs.existsSync(path.join(site, asset)), 'El paquete no incluye ' + asset + ' y alguna pantalla lo enlaza.');
        assert.equal(phpErrors(), '', 'Sin errores de PHP en el registro:\n' + phpErrors());
    } finally {
        if (server) server.kill();
        try { fs.rmSync(tmp, { recursive: true, force: true }); } catch (_) { /* el servidor aún suelta archivos */ }
    }
    console.log('Publish flow tests passed.');
})().catch(error => { console.error(error.message || error); if (server) server.kill(); process.exit(1); });
