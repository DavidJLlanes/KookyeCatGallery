/* Utilidades compartidas: selectores, selector de tarjetas de foto y preferencias del usuario. */

/* ---------- Helpers ---------- */
export const $  = (sel, ctx = document) => ctx.querySelector(sel);

export const $$ = (sel, ctx = document) => Array.from(ctx.querySelectorAll(sel));

// Tarjetas de foto: las de la galería estándar (.card) y las de las galerías premium: «Estilo Baraja» (.deck__card)
// «Estilo Burbujas» (.bubbles__item) y «Estilo Cilindro» (.cyl__item). La raíz de la galería es #masonry (estándar), #deck o #bubbles (premium).
// Ver docs/galerias-premium.md.
export const CARDS = '.card, .deck__card, .bubbles__item, .cyl__item';

export const galleryRoot = () => $('#masonry') || $('#deck') || $('#bubbles');

export const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

export const isFinePointer = window.matchMedia('(hover: hover) and (pointer: fine)').matches;

/* ---------- Galería: filtrado + paginación cliente ---------- */
export const escHtml = s => String(s)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;')
    .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
