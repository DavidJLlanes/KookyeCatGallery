// Prueba del instalador (install.php del paquete del núcleo): deja la web lista, no deja registrar un segundo administrador,
// rechaza datos mal formados y se borra al terminar. Trabaja en una copia exportada del núcleo; no toca los datos reales.
'use strict';
const fs = require('fs');
const os = require('os');
const path = require('path');
const { spawn, execFileSync } = require('child_process');

const root = path.join(__dirname, '..');
const php = process.env.PHP || 'php';
const tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'install-test-'));
const site = path.join(tmp, 'site');
const privateDir = path.join(tmp, 'private');
const port = 8900 + Math.floor(Math.random() * 90);
const base = `http://127.0.0.1:${port}`;
let server = null;

function check(condition, message) { if (!condition) throw new Error(message); }

async function send(url, { method = 'GET', cookie = '', form = null } = {}) {
    const response = await fetch(base + url, {
        method, redirect: 'manual',
        headers: { ...(cookie ? { cookie } : {}), ...(form ? { 'content-type': 'application/x-www-form-urlencoded' } : {}) },
        body: form ? new URLSearchParams(form).toString() : undefined,
    });
    const setCookie = (response.headers.getSetCookie ? response.headers.getSetCookie() : []).map(c => c.split(';')[0]).join('; ');
    return { status: response.status, text: await response.text(), cookie: setCookie || cookie };
}
const csrfOf = html => (html.match(/name="csrf" value="([^"]+)"/) || [])[1];

(async () => {
    try {
        execFileSync(php, [path.join(root, 'scripts/export-core.php'), site, 'prueba'], { stdio: 'pipe' });
        check(fs.existsSync(path.join(site, 'install.php')), 'El paquete del núcleo debe incluir install.php.');
        const env = { ...process.env, GALLERY_PRIVATE_DIR: privateDir, SITE_CONFIG_DIR: '', MICROBLOG_DATA_DIR: '', GALLERY_PUBLIC_URL: '' };
        server = spawn(php, ['-d', 'display_errors=0', '-S', `127.0.0.1:${port}`, '-t', site], { env, stdio: 'ignore' });
        for (let i = 0; i < 60; i++) { try { if ((await fetch(base + '/install.php')).status) break; } catch (_) { /* arrancando */ } await new Promise(r => setTimeout(r, 250)); }

        // 1. El formulario se muestra mientras no hay administrador y avisa de que es mejor borrarlo.
        let page = await send('/install.php');
        check(page.status === 200 && csrfOf(page.text), 'Sin administrador debe mostrarse el formulario.');
        check(/es mejor borrarlo/i.test(page.text), 'El formulario debe decir que es mejor borrar install.php.');
        const cookie = page.cookie;
        const token = csrfOf(page.text);
        const good = { csrf: token, url: 'https://prueba.example', username: 'admin', password: 'una-contrasena-larga-1' };

        // 2. Rechazos: sin token válido, URL no https, usuario o contraseña inválidos. No se crea nada.
        for (const [bad, status] of [[{ ...good, csrf: 'falso' }, 403], [{ ...good, url: 'http://prueba.example' }, 422], [{ ...good, username: 'a b' }, 422], [{ ...good, password: 'corta' }, 422]]) {
            const answer = await send('/install.php', { method: 'POST', cookie, form: bad });
            check(answer.status === status, `Se esperaba ${status} y salió ${answer.status}.`);
        }
        check(!fs.existsSync(path.join(privateDir, 'upload-auth.php')), 'Una petición rechazada no debe crear el acceso.');

        // 3. Instalación correcta: deja la web lista y se borra.
        const done = await send('/install.php', { method: 'POST', cookie, form: good });
        check(done.status === 200 && /Instalación completada/.test(done.text), 'La instalación válida debe completarse: ' + done.text.slice(-400));
        const auth = fs.readFileSync(path.join(privateDir, 'upload-auth.php'), 'utf8');
        const hash = (auth.match(/'password_hash' => '([^']+)'/) || [])[1];
        check(hash && execFileSync(php, ['-r', 'echo password_verify($argv[1], $argv[2]) ? "si" : "no";', good.password, hash]).toString().endsWith('si'), 'El acceso guardado debe verificar la contraseña.');
        check(/'username' => 'admin'/.test(auth), 'El acceso guarda el usuario.');
        check(fs.readFileSync(path.join(privateDir, 'public-url.txt'), 'utf8').trim() === 'https://prueba.example', 'Debe guardarse la dirección pública.');
        check(fs.existsSync(path.join(privateDir, 'site-settings.json')), 'Debe dejar los ajustes iniciales.');
        check(fs.existsSync(path.join(privateDir, 'microblog', 'microblog.sqlite')), 'Debe crear la base del microblog.');
        for (const dir of ['img', 'imagenes/desktop', 'imagenes/mobile', 'microblog']) check(fs.statSync(path.join(site, dir)).isDirectory(), `Debe preparar ${dir}/.`);
        check(!fs.existsSync(path.join(site, 'install.php')), 'El instalador debe borrarse al terminar.');
        check(/pdo_sqlite|Base del microblog creada/.test(done.text) && /borrarlo|eliminado/.test(done.text), 'La página final detalla lo preparado y recuerda comprobar el borrado.');

        // 4. Con un administrador ya registrado no se puede registrar otro, aunque install.php siga ahí (copia olvidada).
        const before = fs.readFileSync(path.join(privateDir, 'upload-auth.php'), 'utf8');
        fs.copyFileSync(path.join(root, 'scripts/defaults/install.php'), path.join(site, 'install.php'));
        const again = await send('/install.php');
        check(again.status === 409 && /ya tiene un administrador registrado/.test(again.text) && !csrfOf(again.text), 'Con administrador no se muestra el formulario.');
        check(!fs.existsSync(path.join(site, 'install.php')), 'Si puede, el instalador se borra al encontrar un administrador.');
        fs.copyFileSync(path.join(root, 'scripts/defaults/install.php'), path.join(site, 'install.php'));
        const forced = await send('/install.php', { method: 'POST', cookie, form: { ...good, username: 'otro', password: 'otra-contrasena-larga-2' } });
        check(forced.status === 409, 'Un envío directo tampoco debe registrar a otro administrador.');
        check(fs.readFileSync(path.join(privateDir, 'upload-auth.php'), 'utf8') === before, 'El administrador original no debe cambiar.');
        console.log('Instalador verificado: deja la web lista y no admite un segundo administrador.');
    } finally {
        if (server) server.kill();
        try { fs.rmSync(tmp, { recursive: true, force: true }); } catch (_) { /* Windows puede tardar en soltar archivos */ }
    }
})().catch(error => { console.error(error.message); process.exit(1); });
