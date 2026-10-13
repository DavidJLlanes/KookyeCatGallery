#!/usr/bin/env node
/* =============================================================================
   Caracterización del panel de administración (admin.php): recorre todas sus pantallas y acciones con una copia
   temporal del sitio y compara cada respuesta con un archivo «dorado» de tests/golden/admin/.

     node tests/admin-characterization.cjs            compara con lo guardado (falla si algo cambia)
     node tests/admin-characterization.cjs --update   reescribe lo guardado (solo si el cambio es intencionado)

   Qué congela: estado HTTP, redirección y cuerpo (HTML, JSON o bytes de imagen) de cada paso, más el estado que dejan
   las acciones (ajustes, colecciones, fotos y sidecars, posts del microblog). No usa navegador: habla HTTP con el
   servidor PHP incorporado, con su propia sesión. Los datos son los de tests/fixtures; nunca toca los reales.
   Variables: PHP (ejecutable con GD), ADMIN_PORT (puerto, por defecto 8771).
   ============================================================================= */
'use strict';

const fs = require('fs');
const os = require('os');
const path = require('path');
const crypto = require('crypto');
const { execFileSync, spawn } = require('child_process');

const root = path.join(__dirname, '..');
const php = process.env.PHP || 'php';
const port = Number(process.env.ADMIN_PORT || 8771);
const update = process.argv.includes('--update');
const goldenDir = path.join(__dirname, 'golden', 'admin');
const tempRoot = path.join(os.tmpdir(), 'djl-admin-characterization');
const siteDir = path.join(tempRoot, 'site');
const configDir = path.join(tempRoot, 'config');
const base = 'http://127.0.0.1:' + port;
const PASSWORD = 'clave-de-prueba-123';

// ---------------------------------------------------------------------------------------------------------------
// Copia temporal del sitio y servidor
// ---------------------------------------------------------------------------------------------------------------
function prepare() {
    fs.rmSync(tempRoot, { recursive: true, force: true });
    const skip = new Set(['.git', 'node_modules', 'img', 'imagenes', 'data', 'microblog', 'log', 'var', 'visual', 'subirweb']);
    fs.cpSync(root, siteDir, { recursive: true, filter: source => !skip.has(path.basename(source)) || source === root });
    for (const dir of ['data', 'imagenes/desktop', 'imagenes/mobile']) fs.mkdirSync(path.join(siteDir, dir), { recursive: true });
    fs.mkdirSync(configDir, { recursive: true });
    execFileSync(php, [path.join(siteDir, 'tests/fixtures/make-site-fixtures.php'), siteDir, configDir], { stdio: 'pipe' });
    const hash = execFileSync(php, ['-r', 'echo password_hash($argv[1], PASSWORD_BCRYPT);', PASSWORD], { encoding: 'utf8' }).split('\n').pop().trim();
    fs.writeFileSync(path.join(configDir, 'upload-auth.php'), "<?php\nreturn ['username' => 'admin', 'password_hash' => '" + hash.replace(/'/g, '') + "'];\n");
}

async function startServer() {
    const env = { ...process.env, SITE_CONFIG_DIR: configDir, GALLERY_PRIVATE_DIR: configDir, MICROBLOG_DATA_DIR: path.join(configDir, 'microblog') };
    const server = spawn(php, ['-d', 'display_errors=0', '-d', 'upload_max_filesize=16M', '-d', 'post_max_size=20M', '-S', '127.0.0.1:' + port, '-t', siteDir], { env, stdio: 'ignore' });
    for (let i = 0; i < 60; i++) {
        try { const r = await fetch(base + '/aviso-legal.php'); if (r.ok) return server; } catch (_) { /* arrancando */ }
        await new Promise(resolve => setTimeout(resolve, 250));
    }
    server.kill();
    throw new Error('El servidor PHP no arrancó.');
}

// ---------------------------------------------------------------------------------------------------------------
// Cliente HTTP con sesión
// ---------------------------------------------------------------------------------------------------------------
let cookie = '';
const postedActions = new Set();
async function request(method, pathname, { form, multipart, auth = true } = {}) {
    const headers = {};
    if (form && form.action) postedActions.add(form.action);
    if (multipart && multipart.action) postedActions.add(multipart.action);
    if (cookie) headers.cookie = cookie;
    let body;
    if (form) {
        const params = new URLSearchParams();
        for (const [key, value] of Object.entries(form)) {
            if (Array.isArray(value)) value.forEach(v => params.append(key + '[]', v));
            else params.append(key, value);
        }
        body = params;
    } else if (multipart) {
        body = new FormData();
        for (const [key, value] of Object.entries(multipart)) {
            if (value && value.bytes) body.append(key, new Blob([value.bytes], { type: value.type }), value.name);
            else body.append(key, value);
        }
    }
    const response = await fetch(base + pathname, { method, headers, body, redirect: 'manual' });
    const setCookies = response.headers.getSetCookie ? response.headers.getSetCookie() : [];
    for (const line of setCookies) {
        const pair = line.split(';')[0];
        if (/^djl_upload_session=/.test(pair)) cookie = pair;
    }
    const bytes = Buffer.from(await response.arrayBuffer());
    return { status: response.status, location: response.headers.get('location') || '', type: (response.headers.get('content-type') || '').split(';')[0], bytes, text: bytes.toString('utf8') };
}

// ---------------------------------------------------------------------------------------------------------------
// Normalización: quita lo que cambia entre ejecuciones (tokens, versiones, nombres al azar, fechas)
// ---------------------------------------------------------------------------------------------------------------
function normalize(text) {
    return text
        .replace(/[0-9a-f]{64}/g, 'HEX64')
        .replace(/\?v=[A-Za-z0-9._-]+/g, '?v=V')
        .replace(/content="[0-9a-f]{7,40}"/g, 'content="APPVER"')
        .replace(/([a-z0-9-]+)-[0-9a-f]{6}\.(jpg|png|txt|webp)/g, '$1-XXXXXX.$2')
        .replace(/([a-z0-9-]+)-[0-9a-f]{6}\.edit\.jpg/g, '$1-XXXXXX.edit.jpg')
        .replace(/data-photo-mtime="\d+"/g, 'data-photo-mtime="MTIME"')
        .replace(/"mtime":\d+/g, '"mtime":MTIME')
        .replace(/[?&]t=\d{9,}/g, '?t=TS')
        // Identificadores al azar de los posts nuevos (10 caracteres hexadecimales con letras y cifras; los de prueba son aaaaaaaaNN).
        .replace(/\b(?!aaaaaaaa)(?=[0-9a-f]*[a-f])(?=[0-9a-f]*[0-9])[0-9a-f]{10}\b/g, 'POSTID')
        // Fechas de los posts del microblog: los creados en el recorrido llevan la hora actual.
        .replace(/<time datetime="[^"]*">[^<]*<\/time>/g, '<time>TIME</time>');
}
function pretty(html) {
    return normalize(html).replace(/\s+/g, ' ').replace(/>\s*</g, '>\n<').trim();
}

const results = [];
let step = 0;
function record(name, response, { raw = false } = {}) {
    const number = String(++step).padStart(2, '0');
    let body;
    if (raw) body = 'sha256:' + crypto.createHash('sha256').update(response.bytes).digest('hex') + ' bytes:' + response.bytes.length;
    else if (response.type === 'application/json') {
        let parsed;
        try { parsed = JSON.parse(response.text); } catch (_) { parsed = response.text; }
        body = normalize(JSON.stringify(parsed, null, 2));
    } else body = pretty(response.text);
    results.push({ file: number + '-' + name + '.txt', text: 'status: ' + response.status + '\nlocation: ' + normalize(response.location) + '\ntype: ' + response.type + '\n---\n' + body + '\n' });
}
function recordState(name) {
    const number = String(++step).padStart(2, '0');
    const read = file => { try { return JSON.parse(fs.readFileSync(file, 'utf8')); } catch (_) { return null; } };
    const imgDir = path.join(siteDir, 'img');
    const listing = fs.readdirSync(imgDir).sort().map(normalize);
    const sidecars = {};
    for (const file of fs.readdirSync(imgDir).filter(f => f.endsWith('.txt')).sort()) {
        const text = fs.readFileSync(path.join(imgDir, file), 'utf8');
        // Solo las que han cambiado respecto al fixture (las nuevas y las editadas), para no repetir 24 sidecars en cada estado.
        if (/foto-de-prueba-(02|03)\.txt$/.test(file) || !/^foto-de-prueba-/.test(file)) sidecars[normalize(file)] = normalize(text);
    }
    // Los posts viven en SQLite: se leen con el propio almacén de la web (que también importa el posts.json de los datos de prueba).
    const dump = "$d = $argv[1]; require_once $d . '/inc/helpers.php'; require_once $d . '/inc/site-settings.php'; require_once $d . '/inc/microblog.php'; echo json_encode(array_values(microblog_load()));";
    let posts = [];
    try { posts = JSON.parse(execFileSync(php, ['-r', dump, siteDir], { env: { ...process.env, SITE_CONFIG_DIR: configDir, GALLERY_PRIVATE_DIR: configDir, MICROBLOG_DATA_DIR: path.join(configDir, 'microblog') }, stdio: ['ignore', 'pipe', 'ignore'] }).toString().trim().split(/\r?\n/).pop()); } catch (_) { posts = []; }
    const state = {
        settings: read(path.join(configDir, 'site-settings.json')),
        collections: read(path.join(configDir, 'collections.json')),
        photoFiles: listing.filter(f => !/^foto-de-prueba-\d+\.(jpg|txt)$/.test(f)).length + ' archivos fuera de las 24 fotos de prueba (' + listing.length + ' en total)',
        sidecars,
        microblogTexts: posts.map(p => String(p.text).slice(0, 40)).sort(),
    };
    results.push({ file: number + '-estado-' + name + '.txt', text: normalize(JSON.stringify(state, null, 2)) + '\n' });
}

// ---------------------------------------------------------------------------------------------------------------
// Recorrido
// ---------------------------------------------------------------------------------------------------------------
function csrfOf(html) {
    const match = html.match(/name="csrf" value="([0-9a-f]{64})"/);
    if (!match) throw new Error('No encuentro el token CSRF en la página.');
    return match[1];
}

async function walk() {
    const jpeg = fs.readFileSync(path.join(siteDir, 'img', 'foto-de-prueba-05.jpg'));
    const photo = { bytes: jpeg, type: 'image/jpeg', name: 'Prueba de lote.jpg' };
    const edited = { bytes: jpeg, type: 'image/jpeg', name: 'editada.jpg' };

    // Sin sesión.
    let r = await request('GET', '/admin.php'); record('acceso-formulario', r);
    r = await request('GET', '/admin.php?library=1'); record('acceso-biblioteca-sin-sesion', r);
    r = await request('POST', '/admin.php', { form: { action: 'save_site_settings', csrf: 'x' } }); record('post-sin-csrf', r);
    let token = csrfOf((await request('GET', '/admin.php')).text);
    r = await request('POST', '/admin.php', { form: { action: 'save_site_settings', csrf: token } }); record('post-sin-sesion', r);
    r = await request('POST', '/admin.php', { form: { action: 'login', csrf: token, username: 'admin', password: 'mala' } }); record('login-incorrecto', r);
    token = csrfOf((await request('GET', '/admin.php')).text);
    r = await request('POST', '/admin.php', { form: { action: 'login', csrf: token, username: 'admin', password: PASSWORD } }); record('login-correcto', r);

    // Pantallas (GET).
    const pages = [
        ['subir', '/admin.php'], ['lotes', '/admin.php?batch=1'], ['vista-movil', '/admin.php?mobile_preview=1'],
        ['ajustes-contenido', '/admin.php?settings=1&section=content'], ['ajustes-diseno', '/admin.php?settings=1&section=design'],
        ['ajustes-redes', '/admin.php?settings=1&section=social'], ['ajustes-publicacion', '/admin.php?settings=1&section=publication'],
        ['ajustes-textos', '/admin.php?settings=1&section=texts'], ['ajustes-pagina', '/admin.php?settings=1&section=page&doc=legal_notice'],
        ['ajustes-seccion-invalida', '/admin.php?settings=1&section=nada'], ['categorias', '/admin.php?categories=1'], ['biblioteca', '/admin.php?library=1'],
        ['editar-foto', '/admin.php?edit=foto-de-prueba-01.jpg'], ['editar-foto-inexistente', '/admin.php?edit=no-existe.jpg'], ['microblog', '/admin.php?microblog=1'],
    ];
    for (const [name, url] of pages) { r = await request('GET', url); record('pantalla-' + name, r); }
    r = await request('GET', '/admin.php?source=foto-de-prueba-01.jpg&variant=published'); record('foto-original', r, { raw: true });
    token = csrfOf((await request('GET', '/admin.php?settings=1&section=design')).text);
    recordState('inicial');

    // Ajustes.
    const settingsForm = (extra = {}) => ({ action: 'save_site_settings', csrf: token, section: 'design', palette: 'ocean', font_pair: 'classic', grid: 'adaptive',
        header_mobile: 'current', header_desktop: 'current', gallery_mobile: 'standard', gallery_desktop: 'standard', hover: 'zoom', pagination_shape: 'circle',
        gallery_premium: 'none', columns_mobile: '3', columns_desktop: '4', photos_mobile: '12', photos_desktop: '20', show_header_mobile: '1', show_header_desktop: '1',
        show_categories: '1', show_map: '1', show_project: '1', show_microblog: '1', show_social: '1',
        section_order: ['gallery', 'categories', 'map', 'project', 'microblog', 'social'], ...extra });
    r = await request('POST', '/admin.php', { form: settingsForm() }); record('guardar-ajustes', r);
    r = await request('GET', '/admin.php?settings=1&section=design&saved=1'); record('pantalla-ajustes-guardados', r);
    recordState('ajustes');
    r = await request('POST', '/admin.php', { form: settingsForm({ palette: 'no-existe' }) }); record('guardar-ajustes-invalidos', r);
    r = await request('POST', '/admin.php', { form: settingsForm({ section: 'social', palette: 'forest', columns_desktop: '99' }) }); record('guardar-ajustes-columnas-invalidas', r);
    r = await request('POST', '/admin.php', { multipart: { ...settingsForm({ section: 'content' }), section_order: 'x', profile_image_file: { bytes: Buffer.from('no es una imagen'), type: 'text/plain', name: 'perfil.txt' } } }); record('guardar-ajustes-imagen-invalida', r);
    r = await request('POST', '/admin.php', { form: { action: 'save_site_page', csrf: token, page: 'legal_notice', content: '<p>Aviso de prueba</p><script>alert(1)</script>' } }); record('guardar-pagina', r);
    r = await request('GET', '/admin.php?settings=1&section=page&doc=legal_notice&saved=1'); record('pantalla-pagina-guardada', r);
    r = await request('POST', '/admin.php', { form: { action: 'save_site_page', csrf: token, page: 'no-existe', content: 'x' } }); record('guardar-pagina-invalida', r);
    recordState('paginas');

    // Categorías y colecciones.
    r = await request('POST', '/admin.php', { form: { action: 'create_collection', csrf: token, category: 'Retratos' } }); record('crear-coleccion', r);
    r = await request('POST', '/admin.php', { form: { action: 'create_collection', csrf: token, category: '' } }); record('crear-coleccion-vacia', r);
    r = await request('POST', '/admin.php', { form: { action: 'save_collection', csrf: token, collection: 'Catedral', description: 'Piedra y luz', cover: 'foto-de-prueba-06.jpg' } }); record('guardar-coleccion', r);
    r = await request('POST', '/admin.php', { form: { action: 'save_collection', csrf: token, collection: 'Catedral', description: 'x', cover: 'foto-de-prueba-01.jpg' } }); record('guardar-coleccion-portada-ajena', r);
    r = await request('GET', '/admin.php?categories=1&status=Hecho'); record('pantalla-categorias-despues', r);
    r = await request('POST', '/admin.php', { form: { action: 'manage_category', csrf: token, source: 'Calles', operation: 'rename', target: 'Callejeo', confirm: '' } }); record('renombrar-categoria', r);
    r = await request('POST', '/admin.php', { form: { action: 'manage_category', csrf: token, source: 'Callejeo', operation: 'delete_photos', target: '', confirm: 'no' } }); record('borrar-categoria-sin-confirmar', r);
    r = await request('POST', '/admin.php', { form: { action: 'delete_empty_collection', csrf: token, collection: 'Retratos' } }); record('borrar-coleccion-vacia', r);
    r = await request('POST', '/admin.php', { form: { action: 'delete_empty_collection', csrf: token, collection: 'Catedral' } }); record('borrar-coleccion-no-vacia', r);
    recordState('categorias');

    // Biblioteca.
    r = await request('POST', '/admin.php', { form: { action: 'update_existing', csrf: token, file: 'foto-de-prueba-02.jpg', title: 'Título editado', category: 'Paisajes', description: 'Descripción editada.', slug: 'titulo-editado', featured: '1', draft: '', latitude: '42.6', longitude: '-5.57' } }); record('editar-foto', r);
    r = await request('POST', '/admin.php', { form: { action: 'update_existing', csrf: token, file: 'foto-de-prueba-02.jpg', title: '', category: 'Paisajes' } }); record('editar-foto-invalida', r);
    r = await request('POST', '/admin.php', { form: { action: 'update_existing', csrf: token, file: 'foto-de-prueba-03.jpg', title: 'Otra', category: 'Agua', slug: 'titulo-editado' } }); record('editar-foto-enlace-repetido', r);
    r = await request('POST', '/admin.php', { form: { action: 'reorder_photos', csrf: token, order: JSON.stringify(Array.from({ length: 24 }, (_, i) => 'foto-de-prueba-' + String(i + 1).padStart(2, '0') + '.jpg').sort((a, b) => (/-0[1-3]\./.test(b) ? 1 : 0) - (/-0[1-3]\./.test(a) ? 1 : 0) || b.localeCompare(a))) } }); record('reordenar', r);
    r = await request('POST', '/admin.php', { form: { action: 'reorder_photos', csrf: token, order: JSON.stringify(['foto-de-prueba-03.jpg']) } }); record('reordenar-incompleto', r);
    r = await request('POST', '/admin.php', { form: { action: 'reorder_photos', csrf: token, order: 'no-json' } }); record('reordenar-invalido', r);
    r = await request('POST', '/admin.php', { form: { action: 'delete_existing', csrf: token, file: 'foto-de-prueba-24.jpg', confirm: 'borrar' } }); record('eliminar-sin-confirmar', r);
    r = await request('POST', '/admin.php', { form: { action: 'delete_existing', csrf: token, file: 'foto-de-prueba-24.jpg', confirm: 'ELIMINAR' } }); record('eliminar-foto', r);
    r = await request('GET', '/admin.php?library=1'); record('pantalla-biblioteca-despues', r);
    recordState('biblioteca');

    // Subidas.
    r = await request('POST', '/admin.php', { multipart: { action: 'batch_upload', csrf: token, category: 'Catedral', draft: '1', photo } }); record('subida-lote', r);
    r = await request('POST', '/admin.php', { multipart: { action: 'batch_upload', csrf: token, category: '', photo } }); record('subida-lote-sin-categoria', r);
    r = await request('POST', '/admin.php', { multipart: { action: 'batch_upload', csrf: token, category: 'Catedral', photo: { bytes: Buffer.from('x'), type: 'text/plain', name: 'a.txt' } } }); record('subida-lote-formato-invalido', r);
    r = await request('POST', '/admin.php', { multipart: { action: 'inspect', csrf: token, photo } }); record('subida-paso1-inspeccionar', r);
    r = await request('POST', '/admin.php', { multipart: { action: 'inspect', csrf: token, photo: { bytes: Buffer.from('x'), type: 'text/plain', name: 'a.txt' } } }); record('subida-paso1-formato-invalido', r);
    r = await request('POST', '/admin.php', { multipart: { action: 'complete', csrf: token, title: 'Sin editar', category: 'Agua' } }); record('subida-paso3-sin-version-editada', r);
    r = await request('POST', '/admin.php', { multipart: { action: 'inspect', csrf: token, photo } }); record('subida-paso1-otra-vez', r);
    r = await request('POST', '/admin.php', { multipart: { action: 'save_edit', csrf: token, edited_photo: edited } }); record('subida-paso2-guardar-edicion', r);
    r = await request('POST', '/admin.php', { multipart: { action: 'complete', csrf: token, title: 'Foto subida en tres pasos', description: 'Una descripción.', category: 'Agua', latitude: '42.5', longitude: '-5.6', featured: '1' } }); record('subida-paso3-publicar', r);
    r = await request('POST', '/admin.php', { multipart: { action: 'inspect', csrf: token, photo } }); record('subida-paso1-para-cancelar', r);
    r = await request('POST', '/admin.php', { form: { action: 'cancel_edit', csrf: token } }); record('subida-cancelar', r);
    r = await request('GET', '/admin.php?library=1'); record('pantalla-biblioteca-tras-subidas', r);
    recordState('subidas');

    // Microblog.
    r = await request('POST', '/admin.php', { form: { action: 'microblog_save', csrf: token, text: 'Post de prueba del recorrido', level: '0' } }); record('microblog-crear', r);
    r = await request('POST', '/admin.php', { form: { action: 'microblog_save', csrf: token, text: '', level: '0' } }); record('microblog-crear-vacio', r);
    r = await request('POST', '/admin.php', { form: { action: 'microblog_save', csrf: token, text: 'Post por ajax', level: '0', ajax: '1' } }); record('microblog-crear-ajax', r);
    r = await request('POST', '/admin.php', { form: { action: 'microblog_save', csrf: token, id: 'aaaaaaaa03', text: 'Editado desde el panel', level: '1' } }); record('microblog-editar', r);
    r = await request('POST', '/admin.php', { form: { action: 'microblog_delete', csrf: token, id: 'aaaaaaaa03' } }); record('microblog-borrar', r);
    r = await request('POST', '/admin.php', { form: { action: 'microblog_delete', csrf: token, id: '!!' } }); record('microblog-borrar-invalido', r);
    r = await request('GET', '/admin.php?microblog=1&status=Hecho'); record('pantalla-microblog-despues', r);
    recordState('microblog');

    // Inteligencia artificial: pantalla, validaciones, guardado (la clave no vuelve a mostrarse) y prueba sin clave.
    const fakeKey = 'clave-de-prueba-NO-REAL-0123456789';
    r = await request('GET', '/admin.php?ai=1'); record('pantalla-ia', r);
    r = await request('POST', '/admin.php', { form: { action: 'ai_save', csrf: token, enabled: '1', first: 'gemini', max_per_request: '5' } }); record('ia-activar-sin-clave', r);
    r = await request('POST', '/admin.php', { form: { action: 'ai_save', csrf: token, first: 'gemini', max_per_request: '99' } }); record('ia-limite-invalido', r);
    r = await request('POST', '/admin.php', { form: { action: 'ai_save', csrf: token, enabled: '1', first: 'openrouter', gemini_api_key: fakeKey, gemini_model: 'gemini-2.5-flash', openrouter_model: 'openai/gpt-4o-mini', max_per_request: '3', style: 'Tono cercano y poético, frases cortas.', context: 'Fotografía de paisaje.' } }); record('ia-guardar', r);
    r = await request('GET', '/admin.php?ai=1&status=Hecho'); record('pantalla-ia-guardada', r);
    if (r.text.includes(fakeKey)) throw new Error('La pantalla de IA no debe mostrar la clave guardada.');
    r = await request('POST', '/admin.php', { form: { action: 'ai_test', csrf: token, provider: 'openrouter' } }); record('ia-probar-sin-clave', r);
    r = await request('POST', '/admin.php', { form: { action: 'ai_test', csrf: token, provider: 'otro' } }); record('ia-probar-proveedor-invalido', r);
    const stored = JSON.parse(fs.readFileSync(path.join(configDir, 'ai-settings.json'), 'utf8'));
    if (stored.gemini_api_key !== fakeKey || stored.first !== 'openrouter' || stored.max_per_request !== 3 || stored.style.indexOf('poético') < 0) throw new Error('ai-settings.json no tiene lo guardado.');
    r = await request('POST', '/admin.php', { form: { action: 'ai_save', csrf: token, enabled: '1', first: 'openrouter', max_per_request: '3', style: '' } }); record('ia-guardar-conserva-clave', r);
    if (JSON.parse(fs.readFileSync(path.join(configDir, 'ai-settings.json'), 'utf8')).gemini_api_key !== fakeKey) throw new Error('Una clave vacía debe conservar la guardada.');
    r = await request('POST', '/admin.php', { form: { action: 'ai_save', csrf: token, first: 'openrouter', max_per_request: '3', clear_gemini_api_key: '1' } }); record('ia-borrar-clave', r);
    if (JSON.parse(fs.readFileSync(path.join(configDir, 'ai-settings.json'), 'utf8')).gemini_api_key !== '') throw new Error('«Borrar la clave» debe vaciarla.');

    // Acción desconocida y cierre de sesión.
    r = await request('POST', '/admin.php', { form: { action: 'no-existe', csrf: token } }); record('accion-desconocida', r);
    r = await request('POST', '/admin.php', { form: { action: 'logout', csrf: token } }); record('cerrar-sesion', r);
    r = await request('GET', '/admin.php?library=1'); record('tras-cerrar-sesion', r);
}

(async () => {
    prepare();
    const server = await startServer();
    try { await walk(); } finally { server.kill(); }
    // Todas las acciones que registran los módulos del panel tienen que estar cubiertas por el recorrido.
    const registered = execFileSync(php, ['-r', 'require "core/bootstrap.php"; foreach (Kcg\\Core\\Admin\\Router::modules() as $m) foreach (array_keys($m->actions()) as $a) echo $a, "\\n";'], { cwd: root, encoding: 'utf8' })
        .split('\n').map(line => line.trim()).filter(line => /^[a-z_]+$/.test(line));
    const uncovered = registered.filter(action => !postedActions.has(action));
    if (uncovered.length) { console.log('Caracterización del panel: acciones sin cobertura en el recorrido: ' + uncovered.join(', ') + '\n  Añade un paso a walk() en tests/admin-characterization.cjs y ejecútalo con --update.'); process.exit(1); }
    fs.mkdirSync(goldenDir, { recursive: true });
    if (update) {
        for (const file of fs.readdirSync(goldenDir)) fs.unlinkSync(path.join(goldenDir, file));
        for (const result of results) fs.writeFileSync(path.join(goldenDir, result.file), result.text);
        console.log('Archivos dorados del panel actualizados: ' + results.length);
        return;
    }
    const failures = [];
    const expectedFiles = new Set(fs.readdirSync(goldenDir));
    for (const result of results) {
        expectedFiles.delete(result.file);
        const target = path.join(goldenDir, result.file);
        if (!fs.existsSync(target)) { failures.push(result.file + ': falta el archivo dorado (ejecuta con --update)'); continue; }
        const expected = fs.readFileSync(target, 'utf8').replace(/\r\n/g, '\n');
        if (expected === result.text) continue;
        const a = expected.split('\n'), b = result.text.split('\n');
        let i = 0;
        while (i < a.length && i < b.length && a[i] === b[i]) i++;
        failures.push(result.file + ': difiere en la línea ' + (i + 1) + '\n      esperado: ' + (a[i] || '(fin)').slice(0, 170) + '\n      actual:   ' + (b[i] || '(fin)').slice(0, 170));
    }
    for (const missing of expectedFiles) failures.push(missing + ': el recorrido ya no genera este paso');
    if (failures.length) { console.log('Caracterización del panel: ' + failures.length + ' diferencias\n  - ' + failures.join('\n  - ')); process.exit(1); }
    console.log('Caracterización del panel verificada: ' + results.length + ' pasos sin cambios.');
})().catch(error => { console.error(error); process.exit(1); });
