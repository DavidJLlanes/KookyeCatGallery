(() => {
    const button = document.getElementById('installAppButton');
    const settingsTabs = document.querySelector('.admin-settings-tabs');
    if (button && settingsTabs) settingsTabs.append(button);
    const isStandalone = window.matchMedia('(display-mode: standalone)').matches
        || window.navigator.standalone === true;
    const isAppleMobile = /iPhone|iPad|iPod/.test(navigator.userAgent)
        || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    const isAndroid = /Android/i.test(navigator.userAgent);
    let installPrompt = null;
    const isSafari = /^((?!chrome|android|crios|fxios|edgios).)*safari/i.test(navigator.userAgent);

    // La instalación es opcional, pero el chequeo de actualizaciones también
    // debe funcionar dentro de la app instalada y aunque no exista este botón.
    if (button && !isStandalone) {
        if (isAppleMobile || isAndroid) button.hidden = false;

        window.addEventListener('beforeinstallprompt', (event) => {
            event.preventDefault();
            installPrompt = event;
            button.hidden = false;
        });

        window.addEventListener('appinstalled', () => {
            installPrompt = null;
            button.hidden = true;
            try { localStorage.setItem('djl-pwa-installed', '1'); } catch (_) {}
        });

        button.addEventListener('click', async () => {
            if (installPrompt) {
                installPrompt.prompt();
                await installPrompt.userChoice;
                installPrompt = null;
                return;
            }

            if (isAppleMobile || isAndroid) {
                let dialog = document.querySelector('.pwa-install-help');
                if (!dialog) {
                    dialog = document.createElement('dialog');
                    dialog.className = 'pwa-install-help';
                    dialog.innerHTML = isAppleMobile
                        ? `<h2>${window.siteTextHTML("Instala Kookye Cat Gallery")}</h2><p>${isSafari ? window.siteTextHTML("En Safari, toca ") + "<strong>" + window.siteTextHTML("Compartir") + "</strong>" : window.siteTextHTML("En iPhone o iPad, abre esta página en Safari y toca ") + "<strong>" + window.siteTextHTML("Compartir") + "</strong>"}${window.siteTextHTML(" y elige ")}<strong>${window.siteTextHTML("Añadir a pantalla de inicio")}</strong>.</p><p>${window.siteTextHTML("La app conservará el acceso a la galería y al área privada desde la pantalla de inicio.")}</p><form method="dialog"><button type="submit">${window.siteTextHTML("Entendido")}</button></form>`
                        : `<h2>${window.siteTextHTML("Instala Kookye Cat Gallery")}</h2><p>${window.siteTextHTML("Usa ")}<strong>${window.siteTextHTML("Instalar app")}</strong>${window.siteTextHTML(" cuando aparezca en Chrome. Si no aparece, abre el menú ⋮ y elige Instalar app o Añadir a pantalla de inicio.")}</p><p>${window.siteTextHTML("La app conservará el acceso a la galería y al área privada desde la pantalla de inicio.")}</p><form method="dialog"><button type="submit">${window.siteTextHTML("Entendido")}</button></form>`;
                    document.body.append(dialog);
                }
                if (typeof dialog.showModal === 'function') dialog.showModal();
            }
        });
    }

    // Al volver con Atrás, algunos navegadores restauran una página antigua desde memoria.
    // Recargar esa copia permite actualizar también la sesión y las acciones de administrador.
    window.addEventListener('pageshow', (event) => {
        if (event.persisted) window.location.reload();
    });

    const pageVersion = document.querySelector('meta[name="app-version"]')?.content || 'local';
    let updateRequested = false;
    let requestedVersion = '';
    let reloadStarted = false;
    let updateNotice = null;

    const hasUnsavedChanges = () => {
        for (const form of document.forms) {
            for (const field of form.elements) {
                if (field.type === 'file' && field.files && field.files.length) return true;
                if (!field.name || /^(hidden|submit|button|reset|image)$/.test(field.type)) continue;
                if (field.value !== field.defaultValue && String(field.value).trim() !== '') return true;
                if (field.type === 'checkbox' && field.checked !== field.defaultChecked) return true;
            }
        }
        return false;
    };

    const announceDeferredUpdate = () => {
        if (updateNotice) return;
        updateNotice = document.createElement('aside');
        updateNotice.className = 'app-update-notice';
        updateNotice.setAttribute('role', 'status');
        updateNotice.setAttribute('aria-live', 'polite');
        updateNotice.textContent = window.siteText("Hay una nueva versión. Se actualizará automáticamente al terminar los cambios de esta página.");
        document.body.append(updateNotice);
    };

    const reloadWhenSafe = () => {
        if (!updateRequested || reloadStarted) return;
        if (hasUnsavedChanges()) {
            announceDeferredUpdate();
            return;
        }
        reloadStarted = true;
        try { sessionStorage.setItem('djl-app-updated-to', requestedVersion); } catch (_) {}
        window.location.reload();
    };

    const startVersionChecks = () => {
        if (!('serviceWorker' in navigator)) return;
        const workerUrl = version => '/service-worker.js?v=' + encodeURIComponent(version);
        const registerWorker = version => navigator.serviceWorker.register(workerUrl(version), {scope:'/'});

        navigator.serviceWorker.addEventListener('controllerchange', reloadWhenSafe);

        const readLatestVersion = async () => {
            try {
                const response = await fetch('/version.json?check=' + Date.now(), {
                    cache: 'no-store',
                    credentials: 'same-origin'
                });
                if (!response.ok) return;
                const release = await response.json();
                const latest = String(release.version || '');
                if (!/^[a-f0-9]{7,40}$/i.test(latest) || latest === pageVersion || latest === requestedVersion) return;

                requestedVersion = latest;
                updateRequested = true;
                if (hasUnsavedChanges()) announceDeferredUpdate();
                await registerWorker(latest);
                if (!navigator.serviceWorker.controller) reloadWhenSafe();
            } catch (_) {
                if (updateRequested && !reloadStarted) {
                    updateRequested = false;
                    requestedVersion = '';
                    if (updateNotice) { updateNotice.remove(); updateNotice = null; }
                }
                // Reintentará al volver a la pestaña o en el siguiente intervalo.
            }
        };

        window.addEventListener('load', () => {
            registerWorker(pageVersion).catch(() => {});
            readLatestVersion();
            window.setInterval(() => {
                if (document.visibilityState === 'visible') readLatestVersion();
            }, 120000);
        });
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') readLatestVersion();
        });
        document.addEventListener('input', reloadWhenSafe, true);
        document.addEventListener('change', reloadWhenSafe, true);
    };

    startVersionChecks();

    // Las fotos cambian sin que cambie la versión del código ni el service worker.
    // Comprobar una firma pequeña permite actualizar también la app ya instalada.
    const startGalleryChecks = () => {
        const galleryVersion = document.querySelector('meta[name="gallery-version"]')?.content || '';
        if (!/^[a-f0-9]{64}$/i.test(galleryVersion)) return;

        let checking = false;
        let pendingVersion = '';
        const attemptedKey = 'djl-gallery-reload-attempted';

        const reloadGalleryWhenSafe = () => {
            if (!pendingVersion || reloadStarted || document.visibilityState !== 'visible'
                || document.querySelector('.lightbox.is-open') || hasUnsavedChanges()) return;

            try {
                if (sessionStorage.getItem(attemptedKey) === pendingVersion) return;
                sessionStorage.setItem(attemptedKey, pendingVersion);
            } catch (_) {}
            reloadStarted = true;
            window.location.reload();
        };

        const checkGallery = async () => {
            if (checking || reloadStarted || document.visibilityState !== 'visible') return;
            checking = true;
            try {
                const response = await fetch('/gallery-version.php', {
                    cache: 'no-store',
                    credentials: 'same-origin'
                });
                if (!response.ok) return;
                const latest = String((await response.json()).version || '');
                if (/^[a-f0-9]{64}$/i.test(latest) && latest !== galleryVersion) {
                    pendingVersion = latest;
                    reloadGalleryWhenSafe();
                }
            } catch (_) {
                // Sin conexión: conservamos la galería y reintentamos más tarde.
            } finally {
                checking = false;
            }
        };

        const checkOnReturn = () => {
            if (document.visibilityState !== 'visible') return;
            reloadGalleryWhenSafe();
            checkGallery();
        };

        window.addEventListener('pageshow', checkOnReturn);
        window.addEventListener('focus', checkOnReturn);
        document.addEventListener('visibilitychange', checkOnReturn);

        // Si había una foto abierta al detectar cambios, recargar al cerrar el visor.
        const lightbox = document.getElementById('lightbox');
        if (lightbox) {
            new MutationObserver(reloadGalleryWhenSafe).observe(lightbox, {
                attributes: true,
                attributeFilter: ['class']
            });
        }
    };

    startGalleryChecks();
})();

