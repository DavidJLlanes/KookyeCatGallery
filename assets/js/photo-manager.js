(() => {
    'use strict';
    const cameraButton = document.getElementById('cameraSourceButton');
    const photoFile = document.getElementById('photoFile');
    if (cameraButton && photoFile) {
        cameraButton.addEventListener('click', () => {
            photoFile.setAttribute('capture', 'environment');
            photoFile.click();
            window.setTimeout(() => photoFile.removeAttribute('capture'), 1000);
        });
        photoFile.addEventListener('click', event => {
            if (event.isTrusted && !photoFile.hasAttribute('capture')) photoFile.removeAttribute('capture');
        });
    }

    document.querySelectorAll('[data-category-action]').forEach(form => {
        const operation = form.querySelector('[data-category-operation]');
        const existing = form.querySelector('[data-existing-target]');
        const existingSelect = form.querySelector('[data-existing-select]');
        const fresh = form.querySelector('[data-new-target]');
        const freshInput = form.querySelector('[data-new-input]');
        const confirmation = form.querySelector('[data-delete-confirm]');
        const confirmationInput = form.querySelector('[data-confirm-input]');
        const update = () => {
            const value = operation.value;
            const needsExisting = value === 'move_existing';
            const needsNew = value === 'rename' || value === 'move_new';
            const needsConfirm = value === 'delete_photos';
            existing.hidden = !needsExisting;
            existingSelect.disabled = !needsExisting;
            existingSelect.required = needsExisting;
            fresh.hidden = !needsNew;
            freshInput.disabled = !needsNew;
            freshInput.required = needsNew;
            confirmation.hidden = !needsConfirm;
            confirmationInput.disabled = !needsConfirm;
            confirmationInput.required = needsConfirm;
        };
        operation.addEventListener('change', update);
        update();
    });

    const grid = document.getElementById('adminPhotoGrid');
    if (!grid) return;

    const heartRows = Array.from(document.querySelectorAll('[data-admin-heart]'));
    if (heartRows.length) {
        const slugs = Array.from(new Set(heartRows.map(row => row.dataset.adminHeart).filter(Boolean)));
        fetch('/hearts.php?slugs=' + encodeURIComponent(slugs.join(',')), { cache: 'no-store' })
            .then(response => response.ok ? response.json() : null)
            .then(data => {
                if (!data?.ok) return;
                heartRows.forEach(row => {
                    const count = data.counts?.[row.dataset.adminHeart] || 0;
                    const value = row.querySelector('span');
                    row.closest('.admin-photo-card')?.setAttribute('data-heart-count', String(Number(count)));
                    if (value) value.textContent = Number(count).toLocaleString('es-ES');
                });
            })
            .catch(() => {});
    }

    const search = document.getElementById('photoLibrarySearch');
    const count = document.getElementById('photoLibraryCount');
    const empty = document.getElementById('photoLibraryEmpty');
    const dialog = document.getElementById('adminDeleteDialog');
    const form = document.getElementById('adminDeleteForm');
    const confirmInput = document.getElementById('deleteConfirm');
    const feedback = document.getElementById('deleteFeedback');
    const deleteButton = document.getElementById('deleteSubmit');

    const normalize = value => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('es');
    const filter = () => {
        const needle = normalize(search.value.trim());
        let visible = 0;
        grid.querySelectorAll('.admin-photo-card').forEach(card => {
            const match = normalize(card.textContent + ' ' + card.dataset.photoFile).includes(needle);
            card.hidden = !match;
            if (match) visible++;
        });
        count.textContent = visible + ' fotografía' + (visible === 1 ? '' : 's');
        empty.hidden = visible !== 0;
    };

    const sort = document.getElementById('photoLibrarySort');
    const saveOrder = document.getElementById('savePhotoOrder');
    const cards = () => Array.from(grid.querySelectorAll('.admin-photo-card'));
    const applySort = mode => {
        const list = cards();
        if (mode === 'recent') list.sort((a, b) => Number(b.dataset.photoMtime || 0) - Number(a.dataset.photoMtime || 0));
        if (mode === 'popular') list.sort((a, b) => Number(b.dataset.heartCount || 0) - Number(a.dataset.heartCount || 0));
        list.forEach(card => grid.appendChild(card));
        if (saveOrder) saveOrder.hidden = mode !== 'manual';
        grid.classList.toggle('is-manual-order', mode === 'manual');
        filter();
    };
    sort?.addEventListener('change', () => applySort(sort.value));
    grid.addEventListener('click', event => {
        const up = event.target.closest('[data-order-up]');
        const down = event.target.closest('[data-order-down]');
        if (!up && !down) return;
        if (sort) sort.value = 'manual';
        const card = event.target.closest('.admin-photo-card');
        if (!card) return;
        if (up && card.previousElementSibling) grid.insertBefore(card, card.previousElementSibling);
        if (down && card.nextElementSibling) grid.insertBefore(card.nextElementSibling, card);
        applySort('manual');
    });
    saveOrder?.addEventListener('click', async () => {
        saveOrder.disabled = true;
        const original = saveOrder.textContent;
        try {
            const body = new FormData();
            body.set('action', 'reorder_photos');
            body.set('csrf', document.querySelector('input[name="csrf"]')?.value || '');
            body.set('order', JSON.stringify(cards().map(card => card.dataset.photoFile)));
            const response = await fetch('/admin.php', { method: 'POST', body, credentials: 'same-origin' });
            const result = await response.json();
            if (!response.ok || !result.ok) throw new Error(result.error || 'No se pudo guardar el orden.');
            saveOrder.textContent = 'Orden guardado';
            setTimeout(() => { saveOrder.textContent = original; }, 1600);
        } catch (error) {
            alert(error.message || 'No se pudo guardar el orden.');
        } finally {
            saveOrder.disabled = false;
        }
    });

    search.addEventListener('input', filter);
    grid.addEventListener('click', event => {
        const button = event.target.closest('[data-delete-file]');
        if (!button) return;
        document.getElementById('deletePhotoFile').value = button.dataset.deleteFile;
        document.getElementById('deletePhotoTitle').textContent = button.dataset.deleteTitle;
        confirmInput.value = '';
        feedback.hidden = true;
        feedback.textContent = '';
        dialog.showModal();
        confirmInput.focus();
    });

    document.getElementById('deleteCancel').addEventListener('click', () => dialog.close());
    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (confirmInput.value.trim() !== 'ELIMINAR') {
            feedback.textContent = 'Escribe ELIMINAR exactamente para continuar.';
            feedback.hidden = false;
            return;
        }
        deleteButton.disabled = true;
        feedback.hidden = true;
        try {
            const response = await fetch('/admin.php', {
                method: 'POST',
                body: new FormData(form),
                credentials: 'same-origin'
            });
            const result = await response.json();
            if (!response.ok || !result.ok) throw new Error(result.error || 'No se pudo eliminar la fotografía.');
            window.location.assign('/admin.php?library=1&status=' + encodeURIComponent('Fotografía eliminada correctamente.'));
        } catch (error) {
            feedback.textContent = error.message || 'Error de conexión. Vuelve a intentarlo.';
            feedback.hidden = false;
            deleteButton.disabled = false;
        }
    });
})();
