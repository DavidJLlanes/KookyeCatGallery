/* Gestos sobre una foto: arrastrar para navegar, deslizar para cerrar. */

// Horizontal photo gestures navigate; browser page zoom is disabled outside the editor.
// Gestos sobre una foto. El primer movimiento claro decide el eje y ya no cambia durante el gesto:
//   · horizontal → `follow(dx)` mientras se arrastra (el visor mueve la foto con el dedo) y, al soltar, `navigate(direction, dx, vx)` si
//     el gesto vale (≥ 50 px, o un gesto rápido de ≥ 24 px) o `release(dx)` si no. `vx` es la velocidad final (px/ms).
//   · vertical (solo si hay `pull`) → `pull(dy)` mientras se arrastra y `drop(dy, vy)` al soltar (deslizar para cerrar el visor).
export const bindPhotoNavigation = (surface, navigate, { follow = null, release = null, pull = null, drop = null } = {}) => {
    if (!surface) return;
    const pointers = new Set();
    let start = null;
    let suppressClickUntil = 0;
    surface.addEventListener('pointerdown', (event) => {
        if (event.pointerType === 'mouse' && event.button !== 0) return;
        const control = event.target.closest('a, button, input, select, textarea');
        if (control && !control.matches('.photo-detail__btn')) return;
        pointers.add(event.pointerId);
        if (pointers.size !== 1) { start = null; return; }
        start = { id: event.pointerId, x: event.clientX, y: event.clientY, axis: null, lastX: event.clientX, lastY: event.clientY, lastT: event.timeStamp, vx: 0, vy: 0 };
        try { surface.setPointerCapture(event.pointerId); } catch (_) {}
    });
    surface.addEventListener('pointermove', (event) => {
        if (!start || start.id !== event.pointerId) return;
        const dx = event.clientX - start.x;
        const dy = event.clientY - start.y;
        const dt = Math.max(1, event.timeStamp - start.lastT);
        start.vx = 0.6 * start.vx + 0.4 * ((event.clientX - start.lastX) / dt);
        start.vy = 0.6 * start.vy + 0.4 * ((event.clientY - start.lastY) / dt);
        start.lastX = event.clientX; start.lastY = event.clientY; start.lastT = event.timeStamp;
        if (!start.axis) {
            if (Math.hypot(dx, dy) < 10) return;
            start.axis = Math.abs(dx) >= Math.abs(dy) * 1.2 ? 'x' : (pull && Math.abs(dy) > Math.abs(dx) * 1.2 ? 'y' : 'none');
        }
        if (start.axis === 'x' && follow) follow(dx);
        else if (start.axis === 'y') pull(dy);
    });
    surface.addEventListener('pointerup', (event) => {
        const gesture = start;
        pointers.delete(event.pointerId);
        start = null;
        if (!gesture || gesture.id !== event.pointerId) return;
        const dx = event.clientX - gesture.x;
        const dy = event.clientY - gesture.y;
        const idle = event.timeStamp - gesture.lastT > 120;            // El dedo se paró antes de soltar: no hay inercia.
        const vx = idle ? 0 : gesture.vx, vy = idle ? 0 : gesture.vy;
        if (gesture.axis === 'y') { suppressClickUntil = performance.now() + 400; drop(dy, vy); return; }
        const fast = Math.abs(vx) > 0.5 && Math.abs(dx) >= 24 && Math.sign(vx) === Math.sign(dx);
        if ((Math.abs(dx) < 50 && !fast) || Math.abs(dx) < Math.abs(dy) * 1.5) {
            if (gesture.axis === 'x') { suppressClickUntil = performance.now() + 400; if (release) release(dx); }   // Arrastrar no es pulsar.
            return;
        }
        suppressClickUntil = performance.now() + 400;
        navigate(dx > 0 ? -1 : 1, dx, vx);
    });
    const cancel = (event) => {
        const gesture = start;
        pointers.delete(event.pointerId);
        start = null;
        if (gesture?.axis === 'x' && release) release(0);
        if (gesture?.axis === 'y' && drop) drop(0, 0);
    };
    surface.addEventListener('pointercancel', cancel);
    surface.addEventListener('lostpointercapture', (event) => { pointers.delete(event.pointerId); });
    surface.addEventListener('click', (event) => {
        if (performance.now() < suppressClickUntil) {
            event.preventDefault();
            event.stopImmediatePropagation();
        }
    }, true);
    surface.addEventListener('dragstart', (event) => event.preventDefault());
};
