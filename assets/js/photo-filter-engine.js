/* Photographic development shared by thumbnails, preview and export. */
(function (root, factory) {
    'use strict';
    const engine = factory();
    if (typeof module === 'object' && module.exports) module.exports = engine;
    else root.PhotoFilterEngine = engine;
})(typeof globalThis !== 'undefined' ? globalThis : this, function () {
    'use strict';
    const clamp = (v, lo = 0, hi = 1) => Math.max(lo, Math.min(hi, v));
    const lerp = (a, b, t) => a + (b - a) * t;
    const smooth = (a, b, v) => { const t = clamp((v - a) / (b - a)); return t * t * (3 - 2 * t); };
    const identity = [0, .25, .5, .75, 1];
    const cache = new WeakMap();
    const rgb = hex => [1, 3, 5].map(i => parseInt(hex.slice(i, i + 2), 16) / 255);

    // A smooth curve, including non-monotonic curves used for solarisation.
    function curve(stops, value) {
        const x = clamp(value) * (stops.length - 1), i = Math.min(stops.length - 2, Math.floor(x)), t = x - i;
        const p0 = stops[Math.max(0, i - 1)], p1 = stops[i], p2 = stops[i + 1], p3 = stops[Math.min(stops.length - 1, i + 2)];
        const m1 = i === 0 ? p2 - p1 : (p2 - p0) * .5;
        const m2 = i === stops.length - 2 ? p2 - p1 : (p3 - p1) * .5;
        return clamp((2 * t ** 3 - 3 * t ** 2 + 1) * p1 + (t ** 3 - 2 * t ** 2 + t) * m1 + (-2 * t ** 3 + 3 * t ** 2) * p2 + (t ** 3 - t ** 2) * m2, Math.min(p1, p2), Math.max(p1, p2));
    }
    function prepare(grade) {
        if (cache.has(grade)) return cache.get(grade);
        const p = { ...grade, exposureScale: 2 ** (grade.exposure || 0), contrast: grade.contrast ?? 1, saturation: grade.saturation ?? 1 };
        p.luts = [0, 1, 2].map(c => {
            const lut = new Float32Array(4096), stops = grade.channels?.[c] || identity;
            for (let i = 0; i < lut.length; i++) lut[i] = curve(stops, curve(grade.curve || identity, i / 4095));
            return lut;
        });
        if (grade.hsl) {
            const keys = ['red', 'orange', 'yellow', 'green', 'cyan', 'blue', 'purple', 'magenta'];
            const centers = [0, 30, 60, 120, 180, 240, 270, 300];
            p.hueTable = new Float32Array(360 * 3);
            for (let hue = 0; hue < 360; hue++) {
                let shift = 0, saturation = 1, lightness = 0;
                keys.forEach((key, i) => {
                    if (!grade.hsl[key]) return;
                    const distance = Math.min(Math.abs(hue - centers[i]), 360 - Math.abs(hue - centers[i]));
                    const weight = 1 - smooth(0, key === 'orange' ? 30 : 55, distance), edit = grade.hsl[key];
                    shift += edit[0] * weight; saturation += (edit[1] - 1) * weight; lightness += edit[2] * weight;
                });
                p.hueTable.set([shift, Math.max(0, saturation), lightness], hue * 3);
            }
        }
        if (grade.split) p.splitColors = [rgb(grade.split.shadows), rgb(grade.split.highlights)];
        if (grade.duotone) p.toneColors = grade.duotone.map(rgb);
        if (grade.vignette) p.vignetteColor = rgb(grade.vignette.color || '#000000');
        p.leaks = (grade.leaks || []).map(leak => ({ ...leak, rgb: rgb(leak.color) }));
        cache.set(grade, p);
        return p;
    }
    function lookup(lut, v) {
        const x = clamp(v) * 4095, i = Math.floor(x);
        return lerp(lut[i], lut[Math.min(4095, i + 1)], x - i);
    }
    function gradePixel(r, g, b, p, out) {
        r /= 255; g /= 255; b /= 255;
        if (p.mixer) {
            const a = r, c = b;
            r = a * p.mixer[0] + g * p.mixer[1] + c * p.mixer[2];
            b = a * p.mixer[6] + g * p.mixer[7] + c * p.mixer[8];
            g = a * p.mixer[3] + g * p.mixer[4] + c * p.mixer[5];
        }
        if (p.mono) {
            const m = p.monoMix || [.2126, .7152, .0722];
            r = g = b = r * m[0] + g * m[1] + b * m[2];
        }
        r *= p.exposureScale; g *= p.exposureScale; b *= p.exposureScale;
        const temperature = (p.temperature || 0) * .14, tint = (p.tint || 0) * .10;
        r += temperature + tint * .5; g -= tint; b -= temperature - tint * .5;
        const luma = r * .2126 + g * .7152 + b * .0722;
        r = luma + (r - luma) * p.saturation; g = luma + (g - luma) * p.saturation; b = luma + (b - luma) * p.saturation;
        if (p.hueTable) {
            const max = Math.max(r, g, b), min = Math.min(r, g, b), delta = max - min, light = (max + min) * .5;
            if (delta > .0001) {
                let h = max === r ? ((g - b) / delta + (g < b ? 6 : 0)) : max === g ? ((b - r) / delta + 2) : ((r - g) / delta + 4);
                h *= 60;
                const index = Math.min(359, Math.floor(h)) * 3, table = p.hueTable;
                const editedH = ((h + table[index]) % 360 + 360) % 360 / 60;
                const editedL = clamp(light + table[index + 2]);
                const sat = clamp(delta / Math.max(.0001, 1 - Math.abs(2 * light - 1)) * table[index + 1]);
                const chroma = (1 - Math.abs(2 * editedL - 1)) * sat, x = chroma * (1 - Math.abs(editedH % 2 - 1)), m = editedL - chroma * .5;
                if (editedH < 1) { r = chroma; g = x; b = 0; }
                else if (editedH < 2) { r = x; g = chroma; b = 0; }
                else if (editedH < 3) { r = 0; g = chroma; b = x; }
                else if (editedH < 4) { r = 0; g = x; b = chroma; }
                else if (editedH < 5) { r = x; g = 0; b = chroma; }
                else { r = chroma; g = 0; b = x; }
                r += m; g += m; b += m;
            }
        }
        r = lookup(p.luts[0], (r - .5) * p.contrast + .5);
        g = lookup(p.luts[1], (g - .5) * p.contrast + .5);
        b = lookup(p.luts[2], (b - .5) * p.contrast + .5);
        if (p.split) {
            const lum = r * .2126 + g * .7152 + b * .0722, balance = p.split.balance ?? .5;
            const shadows = (1 - smooth(0, balance + .25, lum)) * p.split.amount;
            const highlights = smooth(balance - .25, 1, lum) * p.split.amount;
            const dark = p.splitColors[0], light = p.splitColors[1];
            r += (dark[0] - .5) * shadows + (light[0] - .5) * highlights;
            g += (dark[1] - .5) * shadows + (light[1] - .5) * highlights;
            b += (dark[2] - .5) * shadows + (light[2] - .5) * highlights;
        }
        if (p.bleach) {
            const gray = clamp(((r * .2126 + g * .7152 + b * .0722) - .5) * 1.8 + .5);
            const overlay = v => v < .5 ? 2 * v * gray : 1 - 2 * (1 - v) * (1 - gray);
            r = lerp(r, overlay(r), p.bleach); g = lerp(g, overlay(g), p.bleach); b = lerp(b, overlay(b), p.bleach);
        }
        if (p.toneColors) {
            const lum = clamp(r * .2126 + g * .7152 + b * .0722) ** (p.toneGamma || 1), t = lum < .5 ? lum * 2 : (lum - .5) * 2;
            const a = p.toneColors[lum < .5 ? 0 : 1], c = p.toneColors[lum < .5 ? 1 : 2];
            r = lerp(a[0], c[0], t); g = lerp(a[1], c[1], t); b = lerp(a[2], c[2], t);
        }
        if (p.posterize) { const n = p.posterize - 1; r = Math.round(r * n) / n; g = Math.round(g * n) / n; b = Math.round(b * n) / n; }
        if (p.invert) { r = 1 - r; g = 1 - g; b = 1 - b; }
        out[0] = clamp(r) * 255; out[1] = clamp(g) * 255; out[2] = clamp(b) * 255;
    }
    function blurPlane(source, w, h, radius) {
        const temp = new Float32Array(source.length), out = new Float32Array(source.length), r = Math.max(1, radius), diameter = r * 2 + 1;
        for (let y = 0; y < h; y++) {
            const row = y * w; let sum = 0;
            for (let x = -r; x <= r; x++) sum += source[row + clamp(x, 0, w - 1)];
            for (let x = 0; x < w; x++) {
                temp[row + x] = sum / diameter;
                sum += source[row + Math.min(w - 1, x + r + 1)] - source[row + Math.max(0, x - r)];
            }
        }
        for (let x = 0; x < w; x++) {
            let sum = 0;
            for (let y = -r; y <= r; y++) sum += temp[clamp(y, 0, h - 1) * w + x];
            for (let y = 0; y < h; y++) {
                out[y * w + x] = sum / diameter;
                sum += temp[Math.min(h - 1, y + r + 1) * w + x] - temp[Math.max(0, y - r) * w + x];
            }
        }
        return out;
    }
    function lightMap(pixels, w, h, effect, threshold, fullImage = false) {
        const scale = Math.min(1, 256 / Math.max(w, h)), mw = Math.max(1, Math.round(w * scale)), mh = Math.max(1, Math.round(h * scale));
        const planes = [new Float32Array(mw * mh), new Float32Array(mw * mh), new Float32Array(mw * mh)];
        for (let y = 0; y < mh; y++) for (let x = 0; x < mw; x++) {
            // Average four samples so small lamps survive when exporting large photos.
            for (const offset of [.25, .75]) for (const offsetY of [.25, .75]) {
                const sx = Math.min(w - 1, Math.floor((x + offset) * w / mw)), sy = Math.min(h - 1, Math.floor((y + offsetY) * h / mh)), i = (sy * w + sx) * 4;
                const r = pixels[i] / 255, g = pixels[i + 1] / 255, b = pixels[i + 2] / 255;
                const weight = fullImage ? .25 : clamp((r * .2126 + g * .7152 + b * .0722 - threshold) / (1 - threshold)) * .25;
                const k = y * mw + x; planes[0][k] += r * weight; planes[1][k] += g * weight; planes[2][k] += b * weight;
            }
        }
        const radius = Math.max(1, Math.round(Math.max(mw, mh) * effect.radius));
        return { w: mw, h: mh, planes: planes.map(plane => blurPlane(blurPlane(plane, mw, mh, radius), mw, mh, radius)) };
    }
    function sample(plane, w, h, u, v) {
        const fx = clamp(u * w - .5, 0, w - 1), fy = clamp(v * h - .5, 0, h - 1), x = Math.floor(fx), y = Math.floor(fy);
        const x1 = Math.min(w - 1, x + 1), y1 = Math.min(h - 1, y + 1);
        return lerp(lerp(plane[y * w + x], plane[y * w + x1], fx - x), lerp(plane[y1 * w + x], plane[y1 * w + x1], fx - x), fy - y);
    }
    function noise(x, y, seed) {
        let n = Math.imul(x ^ seed, 374761393) ^ Math.imul(y, 668265263);
        n = Math.imul(n ^ (n >>> 13), 1274126177);
        return ((n ^ (n >>> 16)) >>> 0) / 4294967295 - .5;
    }
    function dust(pixels, w, h, settings, seed) {
        const edge = Math.max(w, h);
        for (let n = 0; n < settings.count; n++) {
            const cx = Math.floor((noise(n, 17, seed) + .5) * w), cy = Math.floor((noise(n, 39, seed) + .5) * h);
            const radius = Math.max(.7, edge / 1200 * (settings.size || 2)), color = n % 3 ? 240 : 24;
            for (let y = Math.max(0, Math.floor(cy - radius)); y <= Math.min(h - 1, cy + radius); y++) for (let x = Math.max(0, Math.floor(cx - radius)); x <= Math.min(w - 1, cx + radius); x++) {
                const amount = clamp(1 - Math.hypot(x - cx, y - cy) / radius) * settings.amount, i = (y * w + x) * 4;
                for (let c = 0; c < 3; c++) pixels[i + c] = lerp(pixels[i + c], color, amount);
            }
        }
    }
    function apply(pixels, w, h, grade, intensity = 1, seed = 1) {
        const weight = clamp(intensity);
        if (!weight || !grade) return pixels;
        const p = prepare(grade), original = weight < 1 ? pixels.slice() : null;
        const halo = p.halation ? lightMap(pixels, w, h, p.halation, p.halation.threshold ?? .58) : null;
        const out = new Float64Array(3);
        for (let i = 0; i < pixels.length; i += 4) {
            gradePixel(pixels[i], pixels[i + 1], pixels[i + 2], p, out);
            pixels[i] = out[0]; pixels[i + 1] = out[1]; pixels[i + 2] = out[2];
        }
        const glow = p.bloom ? lightMap(pixels, w, h, p.bloom, p.bloom.threshold ?? .55) : null;
        const soft = p.softness ? lightMap(pixels, w, h, { radius: .004 }, 0, true) : null;
        const aberrated = p.aberration ? pixels.slice() : null;
        const edge = Math.max(w, h), grainScale = p.grain ? (p.grain.amount * 90 * Math.min(1, Math.sqrt(edge / 750))) : 0;
        const grainCell = p.grain ? 1200 / edge / (p.grain.size || 1) : 0;
        const needsSpatial = p.vignette || grainScale || halo || glow || soft || aberrated || p.leaks.length;
        if (needsSpatial) for (let y = 0; y < h; y++) for (let x = 0; x < w; x++) {
            const i = (y * w + x) * 4, u = (x + .5) / w, v = (y + .5) / h;
            let r = pixels[i] / 255, g = pixels[i + 1] / 255, b = pixels[i + 2] / 255;
            if (aberrated) {
                const shift = p.aberration * edge / 1200, dx = (u - .5) * shift, dy = (v - .5) * shift;
                const ri = (clamp(Math.round(y + dy), 0, h - 1) * w + clamp(Math.round(x + dx), 0, w - 1)) * 4;
                const bi = (clamp(Math.round(y - dy), 0, h - 1) * w + clamp(Math.round(x - dx), 0, w - 1)) * 4;
                r = aberrated[ri] / 255; b = aberrated[bi + 2] / 255;
            }
            if (soft) {
                r = lerp(r, sample(soft.planes[0], soft.w, soft.h, u, v), p.softness);
                g = lerp(g, sample(soft.planes[1], soft.w, soft.h, u, v), p.softness);
                b = lerp(b, sample(soft.planes[2], soft.w, soft.h, u, v), p.softness);
            }
            if (glow) {
                r = 1 - (1 - r) * (1 - sample(glow.planes[0], glow.w, glow.h, u, v) * p.bloom.amount);
                g = 1 - (1 - g) * (1 - sample(glow.planes[1], glow.w, glow.h, u, v) * p.bloom.amount);
                b = 1 - (1 - b) * (1 - sample(glow.planes[2], glow.w, glow.h, u, v) * p.bloom.amount);
            }
            if (halo) {
                const amount = sample(halo.planes[0], halo.w, halo.h, u, v) * p.halation.amount;
                r = 1 - (1 - r) * (1 - amount); g = 1 - (1 - g) * (1 - amount * .22); b = 1 - (1 - b) * (1 - amount * .04);
            }
            for (const leak of p.leaks) {
                const distance = Math.hypot((u - leak.position[0]) / (leak.stretch || 1), v - leak.position[1]);
                const amount = clamp(1 - distance / leak.radius) ** 2 * leak.amount;
                r = 1 - (1 - r) * (1 - leak.rgb[0] * amount); g = 1 - (1 - g) * (1 - leak.rgb[1] * amount); b = 1 - (1 - b) * (1 - leak.rgb[2] * amount);
            }
            if (grainScale) {
                const gx = Math.floor(x * grainCell), gy = Math.floor(y * grainCell), density = .35 + .65 * (1 - Math.abs((r + g + b) / 3 - .5) * 2);
                const grain = noise(gx, gy, seed) * grainScale * density / 255, color = noise(gx, gy, seed + 71) * grainScale * (p.grain.color || 0) / 255;
                r += grain + color; g += grain; b += grain - color;
            }
            if (p.vignette) {
                const distance = Math.hypot((u - .5) * 2, (v - .5) * 2), radius = p.vignette.radius ?? .35;
                const amount = smooth(radius, radius + (p.vignette.feather || .9), distance) * p.vignette.amount, c = p.vignetteColor;
                r = lerp(r, c[0], amount); g = lerp(g, c[1], amount); b = lerp(b, c[2], amount);
            }
            pixels[i] = clamp(r) * 255; pixels[i + 1] = clamp(g) * 255; pixels[i + 2] = clamp(b) * 255;
        }
        if (p.dust) dust(pixels, w, h, p.dust, seed);
        if (original) for (let i = 0; i < pixels.length; i += 4) for (let c = 0; c < 3; c++) pixels[i + c] = lerp(original[i + c], pixels[i + c], weight);
        return pixels;
    }
    return { prepare, gradePixel, apply };
});
