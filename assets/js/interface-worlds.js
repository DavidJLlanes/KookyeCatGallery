/* Optional 3D layer for Orbit (mobile) and Observatory (desktop).
   The gallery, map and navigation never depend on WebGL or third-party CDNs. */
const mobile = window.matchMedia('(max-width: 768px)');
const reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
const eligible = () => !reduced.matches && !navigator.connection?.saveData;
let stopWorld = null;

async function loadScript(url) {
    if (document.querySelector('script[data-world-src="' + url + '"]')) return;
    await new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = url;
        script.async = true;
        script.dataset.worldSrc = url;
        script.onload = resolve;
        script.onerror = reject;
        document.head.append(script);
    });
}

async function startWorld() {
    stopWorld?.();
    stopWorld = null;
    const body = document.body;
    const mobileOrbit = mobile.matches && body.dataset.headerMobile === 'orbit';
    const desktopObservatory = !mobile.matches && body.dataset.headerDesktop === 'observatory';
    const host = document.querySelector(mobileOrbit ? '.mobile-profile' : desktopObservatory ? '.hero' : '.world-never');
    if (!host || !eligible() || !window.WebGL2RenderingContext) return;

    const canvas = document.createElement('canvas');
    canvas.className = 'world-canvas';
    canvas.setAttribute('aria-hidden', 'true');
    host.prepend(canvas);
    let cancelled = false;
    stopWorld = () => { cancelled = true; canvas.remove(); };
    try {
        const THREE = await import('https://cdn.jsdelivr.net/npm/three@0.160.1/build/three.module.js');
        if (cancelled) return;
        const renderer = new THREE.WebGLRenderer({canvas, alpha: true, antialias: false, powerPreference: 'low-power'});
        renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 1.5));
        const scene = new THREE.Scene();
        const camera = new THREE.PerspectiveCamera(48, 1, 0.1, 30);
        camera.position.z = 7;
        const group = new THREE.Group();
        group.position.x = mobileOrbit ? .75 : 1.7;
        scene.add(group);

        const positions = [];
        const count = mobileOrbit ? 130 : 260;
        for (let i = 0; i < count; i++) {
            const y = 1 - i / (count - 1) * 2;
            const r = Math.sqrt(1 - y * y);
            const theta = i * Math.PI * (3 - Math.sqrt(5));
            positions.push(r * Math.cos(theta) * 2.2, y * 2.2, r * Math.sin(theta) * 2.2);
        }
        const stars = new THREE.BufferGeometry();
        stars.setAttribute('position', new THREE.Float32BufferAttribute(positions, 3));
        const dots = new THREE.PointsMaterial({color: 0xa6edff, size: mobileOrbit ? .035 : .028, transparent: true, opacity: .9});
        group.add(new THREE.Points(stars, dots));

        const ringGeometry = new THREE.TorusGeometry(2.25, .007, 3, 160);
        const ringMaterial = new THREE.MeshBasicMaterial({color: 0x79d9ef, transparent: true, opacity: .42});
        const rings = [0, Math.PI / 3, -Math.PI / 3].map(angle => {
            const ring = new THREE.Mesh(ringGeometry, ringMaterial);
            ring.rotation.x = angle;
            group.add(ring);
            return ring;
        });
        const clock = new THREE.Clock();
        let visible = true;
        const observer = new IntersectionObserver(entries => {
            visible = !!entries[0]?.isIntersecting;
        }, {threshold: 0});
        observer.observe(host);
        const resize = () => {
            const {width, height} = host.getBoundingClientRect();
            if (!width || !height) return;
            renderer.setSize(width, height, false);
            camera.aspect = width / height;
            camera.updateProjectionMatrix();
        };
        const ro = new ResizeObserver(resize);
        ro.observe(host);
        resize();
        renderer.setAnimationLoop(() => {
            if (!visible || document.hidden || reduced.matches) return;
            const t = clock.getElapsedTime();
            group.rotation.y = t * .09;
            group.rotation.z = Math.sin(t * .15) * .12;
            renderer.render(scene, camera);
        });

        const oldStop = stopWorld;
        stopWorld = () => {
            renderer.setAnimationLoop(null);
            observer.disconnect();
            ro.disconnect();
            stars.dispose();
            dots.dispose();
            ringGeometry.dispose();
            ringMaterial.dispose();
            renderer.dispose();
            oldStop?.();
        };
        if (desktopObservatory) {
            try {
                await loadScript('https://cdn.jsdelivr.net/npm/gsap@3.13.0/dist/gsap.min.js');
                await loadScript('https://cdn.jsdelivr.net/npm/gsap@3.13.0/dist/ScrollTrigger.min.js');
                if (!cancelled && window.gsap && window.ScrollTrigger) {
                    window.gsap.registerPlugin(window.ScrollTrigger);
                    const tween = window.gsap.fromTo(canvas, {opacity: .9, scale: 1}, {
                        opacity: .3, scale: 1.2, ease: 'none',
                        scrollTrigger: {trigger: host, start: 'top top', end: 'bottom top', scrub: true}
                    });
                    const oldStop = stopWorld;
                    stopWorld = () => { tween.scrollTrigger?.kill(); tween.kill(); oldStop?.(); };
                }
            } catch (_) { /* Native 3D keeps working without GSAP. */ }
        }

    } catch (_) {
        canvas.remove();
        stopWorld = null;
    }
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', startWorld, {once: true});
else startWorld();
mobile.addEventListener('change', startWorld);
reduced.addEventListener('change', startWorld);
