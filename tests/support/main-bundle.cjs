// assets/js/main.js es un módulo ES con sus dependencias en assets/js/app/. Varias pruebas montan la página a mano
// (sin servidor) e inyectan el script como texto; este ayudante lo convierte en un script clásico equivalente:
// los módulos en el orden en que los evalúa el navegador (primero las dependencias, en orden de import), sin las
// líneas import ni la palabra export, dentro de un solo ámbito. Como todos los nombres de primer nivel son únicos
// (venían de un mismo IIFE), el resultado ejecuta exactamente el mismo código que la web real.
const fs = require('fs');
const path = require('path');

const IMPORT = /^import (?:\{[^}]*\} from )?'([^']+)';\s*$/;

function bundle(root, entry) {
    const seen = new Set();
    const order = [];
    const visit = file => {
        const full = path.resolve(file);
        if (seen.has(full)) return;
        seen.add(full);
        const text = fs.readFileSync(full, 'utf8');
        for (const line of text.split('\n')) {
            const match = line.match(IMPORT);
            if (match) visit(path.resolve(path.dirname(full), match[1]));
        }
        order.push({ full, text });
    };
    visit(path.join(root, entry));
    const body = order.map(({ text }) => text.split('\n').filter(line => !IMPORT.test(line)).map(line => line.replace(/^export (?=(const|let|function) )/, '')).join('\n')).join('\n');
    return "(() => {\n'use strict';\n" + body + '\n})();\n';
}

/** main.js real (módulos de assets/js/app/ incluidos) como un solo script clásico. */
function mainJs(root) {
    return bundle(root, 'assets/js/main.js');
}

/** Contenido de un script de assets/: main.js se monta con sus módulos; el resto se lee tal cual. */
function readScript(root, relative) {
    const normalized = relative.replace(/\\/g, '/');
    if (/^(assets\/js\/)?main(\.js)?$/.test(normalized)) return mainJs(root);
    const withFolder = /\//.test(normalized) ? normalized : 'assets/js/' + normalized;
    return fs.readFileSync(path.join(root, /\.js$/.test(withFolder) ? withFolder : withFolder + '.js'), 'utf8');
}

module.exports = { mainJs, readScript };
