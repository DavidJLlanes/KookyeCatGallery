#!/usr/bin/env node
/* =============================================================================
   Ejecutor de toda la batería de pruebas. Es la «puerta» de cada fase del plan de reestructuración:
   una fase solo se cierra con este ejecutor en verde.

   Uso:
     node tests/run-all.cjs               puerta normal (~5 min): sintaxis, PHP, navegador y matriz visual rápida.
                                          Omite las de tests/known-failures.json (las que ya fallaban y las lentas).
     node tests/run-all.cjs --fast        solo sintaxis y pruebas PHP (segundos)
     node tests/run-all.cjs --no-visual   como la normal pero sin la matriz visual (la integración continua: las capturas de
                                          referencia pesan demasiado para git y se generan en local con visual-matrix.cjs --update)
     node tests/run-all.cjs --all         todo: también las pruebas conocidas y lentas y la matriz visual completa (~30 min)
     node tests/run-all.cjs --only=micro  solo las pruebas cuyo nombre contenga «micro»
     node tests/run-all.cjs --json=ruta   además guarda el resultado en JSON

   Variables de entorno:
     PHP            ejecutable de PHP (por defecto «php»)
     TEST_BROWSER   ejecutable de Chromium para las pruebas de navegador (opcional)
   ============================================================================= */
'use strict';

const fs = require('fs');
const path = require('path');
const { spawnSync } = require('child_process');

const root = path.join(__dirname, '..');
const php = process.env.PHP || 'php';
const args = process.argv.slice(2);
const fast = args.includes('--fast');
const all = args.includes('--all');
const noVisual = args.includes('--no-visual');
const only = (args.find(a => a.startsWith('--only=')) || '').slice(7);
const jsonOut = (args.find(a => a.startsWith('--json=')) || '').slice(7);

// Pruebas que ya fallaban antes de la reestructuración (tests/known-failures.json): no bloquean la puerta.
// Por defecto ni se ejecutan (no aportan información y son las más lentas); con --all sí, y se avisa si alguna vuelve a funcionar.
let known = {};
let slow = {};
try {
    const list = JSON.parse(fs.readFileSync(path.join(__dirname, 'known-failures.json'), 'utf8'));
    known = list.pruebas || {};
    slow = list.lentas || {};
} catch (_) { /* sin lista */ }

function walk(dir, filter, out = []) {
    for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
        if (['.git', 'node_modules', 'img', 'imagenes', 'microblog', 'log', 'data', 'var', 'subirweb'].includes(entry.name)) continue;
        const full = path.join(dir, entry.name);
        if (entry.isDirectory()) walk(full, filter, out);
        else if (filter(full)) out.push(full);
    }
    return out;
}

const rel = file => path.relative(root, file).split(path.sep).join('/');
const jobs = [];
const skipped = [];

// 1. Sintaxis de todos los PHP y JS.
jobs.push({ group: 'sintaxis', name: 'php -l (todos los .php)', run: () => {
    const failures = [];
    for (const file of walk(root, f => f.endsWith('.php'))) {
        const r = spawnSync(php, ['-l', file], { encoding: 'utf8' });
        if (r.status !== 0) failures.push(rel(file) + ': ' + (r.stdout + r.stderr).trim().split('\n')[0]);
    }
    return { ok: failures.length === 0, output: failures.join('\n') };
} });
jobs.push({ group: 'sintaxis', name: 'node --check (todos los .js)', run: () => {
    const failures = [];
    for (const file of walk(root, f => /\.(js|cjs|mjs)$/.test(f))) {
        const r = spawnSync(process.execPath, ['--check', file], { encoding: 'utf8' });
        if (r.status !== 0) failures.push(rel(file) + ': ' + r.stderr.trim().split('\n')[0]);
    }
    return { ok: failures.length === 0, output: failures.join('\n') };
} });

// 2. Pruebas PHP (tests/*.php).
for (const file of fs.readdirSync(path.join(root, 'tests')).filter(f => f.endsWith('.php')).sort()) {
    jobs.push({ group: 'php', name: 'tests/' + file, run: () => {
        const r = spawnSync(php, [path.join('tests', file)], { cwd: root, encoding: 'utf8', timeout: 180000 });
        return { ok: r.status === 0, output: (r.stdout + r.stderr).trim().split('\n').slice(-6).join('\n') };
    } });
}

// 3. Pruebas de navegador (tests/*.cjs) y matriz visual.
if (!fast) {
    for (const file of fs.readdirSync(path.join(root, 'tests')).filter(f => f.endsWith('.cjs') && f !== 'run-all.cjs' && f !== 'visual-matrix.cjs').sort()) {
        const name = 'tests/' + file;
        if (!all && !only && (name in known || name in slow)) { skipped.push(name); continue; }
        jobs.push({ group: 'navegador', name, run: () => {
            const r = spawnSync(process.execPath, [path.join('tests', file)], { cwd: root, encoding: 'utf8', timeout: 600000 });
            return { ok: r.status === 0, output: (r.stdout + r.stderr).trim().split('\n').slice(-8).join('\n') };
        } });
    }
    if (!noVisual) jobs.push({ group: 'navegador', name: 'tests/visual-matrix.cjs' + (all ? '' : ' (rápida)'), run: () => {
        const r = spawnSync(process.execPath, [path.join('tests', 'visual-matrix.cjs'), ...(all ? ['--full'] : [])], { cwd: root, encoding: 'utf8', timeout: 2400000 });
        return { ok: r.status === 0, output: (r.stdout + r.stderr).trim().split('\n').filter(l => !l.includes('openssl')).slice(-8).join('\n') };
    } });
}

const selected = jobs.filter(job => !only || job.name.includes(only));
const results = [];
for (const job of selected) {
    const started = Date.now();
    process.stdout.write('· ' + job.name.padEnd(46));
    let outcome;
    try { outcome = job.run(); } catch (error) { outcome = { ok: false, output: String(error) }; }
    const seconds = ((Date.now() - started) / 1000).toFixed(1);
    console.log((outcome.ok ? 'OK    ' : 'FALLO ') + seconds + ' s');
    if (!outcome.ok && outcome.output) console.log(outcome.output.split('\n').map(l => '    ' + l).join('\n'));
    results.push({ group: job.group, name: job.name, ok: outcome.ok, seconds: Number(seconds), output: outcome.ok ? '' : outcome.output });
}

const failed = results.filter(r => !r.ok);
const unexpected = failed.filter(r => !(r.name in known));
const knownFailing = failed.filter(r => r.name in known);
const fixed = results.filter(r => r.ok && r.name in known);
console.log('\n' + (results.length - failed.length) + '/' + results.length + ' correctas');
if (skipped.length) console.log('Omitidas (' + skipped.length + ', conocidas o lentas; --all o --only=nombre las ejecutan): ' + skipped.join(', '));
if (knownFailing.length) console.log('Fallos conocidos (' + knownFailing.length + ', ya fallaban en la línea base): ' + knownFailing.map(f => f.name).join(', '));
if (fixed.length) console.log('¡Han vuelto a funcionar! Quítalas de tests/known-failures.json: ' + fixed.map(f => f.name).join(', '));
if (unexpected.length) console.log('FALLOS NUEVOS (' + unexpected.length + '): ' + unexpected.map(f => f.name).join(', '));
else console.log('Puerta superada: no hay fallos nuevos.');
if (jsonOut) fs.writeFileSync(jsonOut, JSON.stringify({ date: new Date().toISOString(), results }, null, 2));
process.exit(unexpected.length ? 1 : 0);
