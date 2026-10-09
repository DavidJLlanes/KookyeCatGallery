#!/usr/bin/env python3
"""Replace the abrupt photo-detail fade with a professional slide+fade."""
from pathlib import Path

path = Path("assets/js/main.js")
src = path.read_text(encoding="utf-8")

old = """        const transitionDetail = async (commit) => {
            if (prefersReducedMotion || !detail.animate) { commit(); return; }
            const out = detail.animate([{ opacity: 1 }, { opacity: 0 }],
                { duration: 150, easing: 'ease-in', fill: 'forwards' });
            await out.finished.catch(() => {});
            detail.style.opacity = '0';
            out.cancel();
            commit();
            const img = detailImage();
            if (img) { img.style.transform = ''; img.style.opacity = ''; }
            const incoming = detail.animate([{ opacity: 0 }, { opacity: 1 }],
                { duration: 280, easing: 'cubic-bezier(.22, .8, .24, 1)' });
            detail.style.opacity = '';
            await incoming.finished.catch(() => {});
        };"""

new = """        /* Transicion profesional de la ficha: deslizamiento horizontal + fade.
           La direccion del gesto define el sentido (siguiente = entra por la derecha).
           Curvas de easing tipo iOS/Apple Photos: salida rapida, entrada con inercia suave. */
        const transitionDetail = async (commit, direction = 1) => {
            if (prefersReducedMotion || !detail.animate) { commit(); return; }

            const dist = Math.min(56, Math.round(window.innerWidth * 0.09));
            const outMs = 260;
            const inMs  = 480;
            const outEase = 'cubic-bezier(0.4, 0.0, 0.6, 1)';
            const inEase  = 'cubic-bezier(0.16, 1, 0.3, 1)';

            detail.style.willChange = 'transform, opacity';

            const leaving = detail.animate([
                { opacity: 1, transform: 'translate3d(0, 0, 0)' },
                { opacity: 0, transform: `translate3d(${-direction * dist}px, 0, 0)` }
            ], { duration: outMs, easing: outEase, fill: 'forwards' });
            await leaving.finished.catch(() => {});
            detail.style.opacity = '0';
            detail.style.transform = `translate3d(${-direction * dist}px, 0, 0)`;
            leaving.cancel();

            commit();

            const img = detailImage();
            if (img) { img.style.transform = ''; img.style.opacity = ''; }

            const entering = detail.animate([
                { opacity: 0, transform: `translate3d(${direction * dist}px, 0, 0)` },
                { opacity: 1, transform: 'translate3d(0, 0, 0)' }
            ], { duration: inMs, easing: inEase, fill: 'forwards' });
            detail.style.opacity = '';
            detail.style.transform = '';
            await entering.finished.catch(() => {});
            entering.cancel();
            detail.style.willChange = '';
        };"""

if old not in src:
    raise SystemExit("transitionDetail block not found")

path.write_text(src.replace(old, new, 1), encoding="utf-8")
print("OK", path.stat().st_size)
