// CSS de las pruebas de navegador: lee las hojas de assets/css y, tras site-design.css, añade el CSS de todas las paletas.
// En la web real, cada página recibe inline solo el de la paleta elegida (themes/Palettes.php); aquí hacen falta todas
// porque cada prueba recorre varias paletas con el mismo CSS.
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

const PHP_CODE = 'require "core/bootstrap.php"; use Kcg\\Themes\\Palettes; foreach (Palettes::registry()->ids() as $id) echo Palettes::css($id), "\\n";';

let palettes = null;
function paletteCss(root) {
    palettes ??= execFileSync(process.env.PHP || 'php', ['-r', PHP_CODE], { cwd: root, encoding: 'utf8' })
        .split('\n').filter(line => !line.startsWith('PHP Warning:') && !line.startsWith('Warning:')).join('\n');
    return palettes;
}

// Las cabeceras especiales tienen cada una su hoja (assets/css/worlds/); en la web real solo se carga la elegida, pero las
// pruebas recorren varias cabeceras con el mismo CSS, así que aquí se cargan todas (antes eran un solo interface-worlds.css).
const WORLDS = ['canvas', 'mobile-reel', 'mobile-atlas', 'mobile-studio', 'mobile-orbit', 'mobile-scrapbook', 'mobile-common',
    'desktop-museum', 'desktop-observatory', 'desktop-newsroom', 'desktop-noir', 'desktop-blueprint', 'desktop-common'];

/** Contenido de assets/css/<name>.css; para site-design, precedido del CSS de las paletas; «interface-worlds» = todas las cabeceras. */
function cssFile(root, name) {
    if (name === 'style') {
        // El antiguo style.css, ahora en componentes: todos los de la página «full», en su orden.
        const manifest = JSON.parse(fs.readFileSync(path.join(root, 'assets/css/components/manifest.json'), 'utf8'));
        return manifest.components.filter(c => c.profiles.includes('full')).map(c => fs.readFileSync(path.join(root, 'assets/css/components', c.id + '.css'), 'utf8')).join('\n');
    }
    if (name === 'interface-worlds') return WORLDS.map(file => fs.readFileSync(path.join(root, 'assets/css/worlds', file + '.css'), 'utf8')).join('\n');
    const css = fs.readFileSync(path.join(root, 'assets/css', name + '.css'), 'utf8');
    return name === 'site-design' ? paletteCss(root) + '\n' + css : css;
}

module.exports = { cssFile, paletteCss };
