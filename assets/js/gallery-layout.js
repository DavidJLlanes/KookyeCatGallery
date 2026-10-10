/* Geometry has one owner. Reserve space from metadata, never from lazy image height. */
(() => {
    'use strict';
    window.layoutPhotoGallery = (gallery, design) => {
        if (!gallery) return;
        gallery.dataset.layout = design;
        const mobile = matchMedia('(max-width:768px)').matches;
        const width = gallery.clientWidth;
        if (!width) return;
        const cards = [...gallery.querySelectorAll('.card:not(.is-hidden):not([hidden])')];
        const gap = mobile ? 6 : 14;
        const columns = Math.max(1, Math.min(mobile ? 4 : 10, Math.floor(Number(getComputedStyle(document.body)
            .getPropertyValue(mobile ? '--columns-mobile' : '--columns-desktop')) || 3)));
        const aspect = card => {
            const value = Number(card.style.getPropertyValue('--aspect'));
            return Number.isFinite(value) && value > 0 ? Math.max(.1, value) : 1.5;
        };
        let bottom = 0;
        const place = (card, x, y, w, h) => {
            // Float rounding at narrow breakpoints must not push a justified row
            // a fraction of a pixel beyond the gallery's measured width.
            const left = Math.max(0, Math.min(width, x));
            const right = Math.max(left, Math.min(width, x + w));
            x = left;
            w = right - left;
            for (const [key, value] of Object.entries({x, y, w, h})) card.style.setProperty(`--layout-${key}`, `${value}px`);
            bottom = Math.max(bottom, y + h);
        };
        if (design === 'category-rails') {
            gallery.style.removeProperty('height');
            gallery.querySelectorAll('.category-rail').forEach(row => row.dispatchEvent(new Event('rail:resize')));
            return;
        }
        if (design === 'exhibition') {
            // A quiet gallery wall: generous, centered photographs with their native proportions.
            const lane = Math.min(width, mobile ? width : 1024);
            let y = 0;
            cards.forEach(card => {
                const h = lane / aspect(card);
                place(card, (width - lane) / 2, y, lane, h);
                y += h + gap * 2.5;
            });
        } else if (design === 'contact-sheet') {
            // Dense inventory of photographs; selected columns and aspect preset stay in control.
            const w = (width - gap * (columns - 1)) / columns;
            const preset = ({square: 1, landscape: 4/3, portrait: 3/4}[document.body.dataset.grid] || null);
            const heights = Array(columns).fill(0);
            cards.forEach(card => {
                const column = heights.indexOf(Math.min(...heights));
                const h = w / (preset || Math.max(.7, Math.min(1.4, aspect(card))));
                place(card, column * (w + gap), heights[column], w, h);
                heights[column] += h + gap;
            });
        } else if (design === 'narrative') {
            // One full-width opening image followed by a selected-column sequence.
            let y = 0, index = 0;
            while (index < cards.length) {
                const hero = cards[index++];
                const h = width / aspect(hero);
                place(hero, 0, y, width, h);
                y += h + gap;
                const batch = cards.slice(index, index + columns);
                if (batch.length) {
                    const w = (width - gap * (batch.length - 1)) / batch.length;
                    let x = 0, rowHeight = 0;
                    batch.forEach(card => {
                        const cardHeight = w / aspect(card);
                        place(card, x, y, w, cardHeight);
                        rowHeight = Math.max(rowHeight, cardHeight);
                        x += w + gap;
                    });
                    y += rowHeight + gap;
                    index += batch.length;
                }
            }
        } else if (design === 'triptych') {
            // Repeat three-photo compositions: a large image beside two smaller images.
            let y = 0, index = 0;
            while (index < cards.length) {
                const batch = cards.slice(index, index + 3);
                if (batch.length === 1) {
                    const h = width / aspect(batch[0]);
                    place(batch[0], 0, y, width, h);
                    y += h + gap;
                } else {
                    const heroWidth = (width - gap) * .58;
                    const sideWidth = width - heroWidth - gap;
                    const rowHeight = Math.max(heroWidth / aspect(batch[0]),
                        sideWidth / aspect(batch[1]) * (batch.length - 1) + (batch.length === 3 ? gap : 0));
                    const sideHeight = batch.length === 2 ? rowHeight : (rowHeight - gap) / 2;
                    place(batch[0], 0, y, heroWidth, rowHeight);
                    place(batch[1], heroWidth + gap, y, sideWidth, sideHeight);
                    if (batch[2]) place(batch[2], heroWidth + gap, y + sideHeight + gap, sideWidth, sideHeight);
                    y += rowHeight + gap;
                }
                index += batch.length;
            }
        } else if (design === 'mosaic' || design === 'asymmetric') {
            // Justified rows: exact native ratios, shared edges, no empty cell or crop.
            // Editorial alternates quieter wide rows with denser contact-sheet rows.
            let y = 0, start = 0, row = 0;
            while (start < cards.length) {
                const target = design === 'asymmetric'
                    ? Math.max(1, columns + (row % 3 === 0 ? -1 : 0)) : columns;
                const count = Math.min(cards.length - start, target);
                const batch = cards.slice(start, start + count);
                const ratios = batch.map(aspect);
                const h = (width - gap * (count - 1)) / ratios.reduce((a, b) => a + b, 0);
                let x = 0;
                batch.forEach((card, i) => { const w = h * ratios[i]; place(card, x, y, w, h); x += w + gap; });
                y += h + gap; start += count; row++;
            }
        } else if (design === 'scattered') {
            // Dense square packing: search the first free cell, including earlier holes.
            const cols = columns;
            const inset = Math.max(4, width * .01);
            const unit = (width - inset * 2 - (cols - 1) * gap) / cols;
            const occupied = [];
            const free = (x, y, size) => {
                for (let r = y; r < y + size; r++) for (let c = x; c < x + size; c++) if (occupied[r]?.[c]) return false;
                return true;
            };
            cards.forEach((card, i) => {
                const size = cols > 1 && i % 4 === 0 ? 2 : 1;
                let x = 0, y = 0;
                search: for (;; y++) for (x = 0; x <= cols - size; x++) if (free(x, y, size)) break search;
                for (let r = y; r < y + size; r++) { occupied[r] ||= []; for (let c = x; c < x + size; c++) occupied[r][c] = true; }
                const side = size * unit + (size - 1) * gap;
                place(card, inset + x * (unit + gap), inset + y * (unit + gap), side, side);
                card.style.setProperty('--album-angle', `${[-.7,.65,-.4,.8][i % 4]}deg`);
            });
        } else {
            const w = (width - gap * (columns - 1)) / columns;
            const heights = Array(columns).fill(0);
            // Masonry always keeps native heights. Cropped grids are a separate gallery.
            const preset = design === 'grid'
                ? ({square: 1, landscape: 4/3, portrait: 3/4}[document.body.dataset.grid] || 1) : null;
            cards.forEach(card => {
                const column = heights.indexOf(Math.min(...heights));
                const h = w / (preset || aspect(card));
                place(card, column * (w + gap), heights[column], w, h);
                heights[column] += h + gap;
            });
        }
        gallery.style.height = `${bottom}px`;
    };
})();
