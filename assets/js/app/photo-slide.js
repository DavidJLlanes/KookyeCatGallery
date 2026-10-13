/* Cambio suave de foto en los visores (deslizamiento con inercia). */
import { $, prefersReducedMotion } from './helpers.js';

/* ---------- Cambio suave de foto en los visores ---------- */
// Pasar de una foto a otra en pantalla completa es un deslizamiento continuo, como en un carrusel: la foto nueva se carga y se
// decodifica aparte, entra por el lado del que viene y la anterior sale por el contrario, pegadas y a opacidad completa (nunca se
// ve el fondo ni hay saltos de tamaño). La que sale se oscurece un poco y la que entra se ilumina: da profundidad sin separar las fotos.
// `from` es lo que el dedo ya ha arrastrado (px) y `velocity` su velocidad (px/ms): la animación sigue desde ahí, con la inercia del
// gesto (más rápida cuanto más rápido el gesto). Con botones o teclado, curva suave de entrada y salida.
// `layer` permite reutilizar la imagen vecina que se iba viendo mientras se arrastraba. Devuelve false si la foto no se pudo cargar
// o si otra navegación más reciente la ha sustituido.
const slides = new WeakMap();

export const SLIDE_DIM = 0.55;                                          // Brillo de la foto que sale al final del deslizamiento.

export const slideImage = async (img, src, alt, direction = 1, { from = 0, layer: peek = null, velocity = 0 } = {}) => {
    const state = slides.get(img) || { token: 0, layer: null };
    slides.set(img, state);
    const token = ++state.token;
    const stage = img.parentElement;
    const loader = new Image();
    loader.src = src;
    try { await loader.decode(); }
    catch (_) { if (!loader.complete) await new Promise(resolve => { loader.onload = resolve; loader.onerror = resolve; }); }
    if (token !== state.token || !loader.naturalWidth) {
        if (peek && peek !== state.layer) peek.remove();
        if (token === state.token) stage?.classList.remove('photo-sliding');
        return false;
    }
    if (state.layer && state.layer !== peek) {                   // Un cambio a medias se da por terminado antes de empezar el siguiente.
        img.src = state.layer.src; img.alt = state.layer.alt;
        state.layer.remove(); state.layer = null;
    }
    img.getAnimations?.().forEach(animation => animation.cancel());
    img.style.transform = '';
    img.style.transition = '';
    img.style.filter = '';
    const visible = img.classList.contains('is-shown') && (img.currentSrc || img.src);
    if (prefersReducedMotion || !visible || !img.animate) {
        img.src = src; img.alt = alt;
        img.classList.add('is-shown');
        peek?.remove();
        stage?.classList.remove('photo-sliding');
        return true;
    }
    stage?.classList.add('photo-sliding');
    const layer = peek || document.createElement('img');
    layer.className = 'lightbox__img lightbox__img--incoming is-shown';
    layer.alt = alt;
    if (layer.getAttribute('src') !== src) layer.src = src;
    if (!layer.isConnected) img.insertAdjacentElement('afterend', layer);
    state.layer = layer;
    const width = img.parentElement?.clientWidth || window.innerWidth;
    const progress = Math.min(1, Math.abs(from) / width);        // Lo que ya se recorrió con el dedo (0-1).
    const remaining = width - Math.abs(from);
    const speed = Math.abs(velocity);
    const gesture = from !== 0;
    const duration = gesture
        ? Math.round(Math.min(460, Math.max(200, speed > 0.3 ? (remaining / speed) * 1.4 : 260 + remaining * 0.35)))
        : 520;
    const timing = { duration, easing: gesture ? 'cubic-bezier(.16, 1, .3, 1)' : 'cubic-bezier(.65, 0, .25, 1)', fill: 'both' };
    const dimFrom = 1 - (1 - SLIDE_DIM) * progress;
    const leaving = img.animate([
        { transform: `translate3d(${from}px, 0, 0)`, filter: `brightness(${dimFrom})` },
        { transform: `translate3d(${-direction * width}px, 0, 0)`, filter: `brightness(${SLIDE_DIM})` }], timing);
    const entering = layer.animate([
        { transform: `translate3d(${from + direction * width}px, 0, 0)`, filter: `brightness(${0.75 + 0.25 * progress})` },
        { transform: 'translate3d(0, 0, 0)', filter: 'brightness(1)' }], timing);
    await Promise.allSettled([leaving.finished, entering.finished]);
    if (state.layer !== layer) return token === state.token;      // La terminó una navegación posterior.
    img.src = src; img.alt = alt;
    try { await img.decode(); } catch (_) {}
    leaving.cancel(); entering.cancel();                          // Todo en el mismo fotograma: la base pasa a ser la foto nueva y la capa desaparece.
    layer.remove();
    state.layer = null;
    stage?.classList.remove('photo-sliding');
    return true;
};
