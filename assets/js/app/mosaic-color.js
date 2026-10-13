/* Color dominante de cada foto para la galería «Mosaico cromático». */
import { $, $$ } from './helpers.js';

const imageToneCache = new WeakMap();

const imageTone = image => {
    if (!image || !image.complete || !image.naturalWidth) return null;
    if (imageToneCache.has(image)) return imageToneCache.get(image);
    try {
        const canvas = document.createElement('canvas');
        canvas.width = 24; canvas.height = 24;
        const context = canvas.getContext('2d', { willReadFrequently: true });
        if (!context) return null;
        context.drawImage(image, 0, 0, 24, 24);
        const pixels = context.getImageData(0, 0, 24, 24).data;
        let red = 0, green = 0, blue = 0, count = 0;
        for (let i = 0; i < pixels.length; i += 4) {
            const brightness = (pixels[i] + pixels[i + 1] + pixels[i + 2]) / 3;
            if (pixels[i + 3] < 180 || brightness < 18 || brightness > 242) continue;
            red += pixels[i]; green += pixels[i + 1]; blue += pixels[i + 2]; count++;
        }
        if (!count) return null;
        const tone = `rgb(${Math.round(red / count)}, ${Math.round(green / count)}, ${Math.round(blue / count)})`;
        imageToneCache.set(image, tone);
        return tone;
    } catch (_) { return null; }
};

export const initMosaicColor = () => {
    const gallery = $('#masonry');
    const toneDesigns = ['mosaic', 'scattered'];
    if (!gallery || !toneDesigns.includes(document.body.dataset.galleryMobile) && !toneDesigns.includes(document.body.dataset.galleryDesktop)) return;
    const applyTone = card => {
        const tone = imageTone($('.card__img', card));
        if (tone) card.style.setProperty('--photo-tone', tone);
    };
    const intersections = new Map();
    const observer = new IntersectionObserver(entries => {
        entries.forEach(entry => { if (entry.isIntersecting && !entry.target.hidden) intersections.set(entry.target, entry); else intersections.delete(entry.target); });
        if (!toneDesigns.includes(document.body.dataset.galleryActive)) return;
        const visible = [...intersections.values()].filter(entry => !entry.target.hidden).sort((a, b) => b.intersectionRatio - a.intersectionRatio);
        visible.forEach(entry => applyTone(entry.target));
        const active = visible[0];
        const tone = active && imageTone($('.card__img', active.target));
        if (tone && document.body.dataset.galleryActive === 'mosaic') {
            document.body.style.setProperty('--active-photo-tone', tone);
            document.body.dataset.activePhotoTone = 'true';
        }
    }, { threshold: [0.35, 0.6, 0.85] });
    $$('.card', gallery).forEach(card => {
        observer.observe(card);
        applyTone(card);
        $('.card__img', card)?.addEventListener('load', () => {
            applyTone(card);
            if (toneDesigns.includes(document.body.dataset.galleryActive)) {
                observer.unobserve(card);
                observer.observe(card);
            }
        });
    });
};
