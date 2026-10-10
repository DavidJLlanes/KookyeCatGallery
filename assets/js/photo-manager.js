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


    const batchForm = document.getElementById('photoBatchForm');
    if (batchForm) {
        const filesInput = document.getElementById('batchPhotos');
        const categoryChoice = document.getElementById('batchCategoryChoice');
        const categoryInput = document.getElementById('batchCategory');
        const newCategoryField = document.getElementById('batchNewCategoryField');
        const preview = document.getElementById('batchPreview');
        const batchStatus = document.getElementById('batchStatus');
        const batchSubmit = document.getElementById('batchSubmit');
        const csrf = batchForm.querySelector('[name="csrf"]')?.value || '';
        const maxBytes = 15 * 1024 * 1024;
        const selectedCategory = () => categoryChoice.value === '__new__' ? categoryInput.value.trim() : categoryChoice.value;
        const syncBatchCategory = () => {
            const custom = categoryChoice.value === '__new__';
            newCategoryField.hidden = !custom;
            categoryInput.disabled = !custom;
            categoryInput.required = custom;
            if (!custom) categoryInput.value = '';
            renderBatchSelection();
            if (custom) categoryInput.focus();
        };
        const renderBatchSelection = () => {
            const files = [...(filesInput.files || [])];
            preview.replaceChildren();
            if (!files.length) {
                const note = document.createElement('p');
                note.textContent = 'Las fotos seleccionadas aparecerán aquí.';
                preview.append(note);
                batchSubmit.disabled = true;
                return;
            }
            const summary = document.createElement('p');
            summary.className = 'admin-batch__summary';
            summary.textContent = files.length + ' fotografías · ' + (files.reduce((total, file) => total + file.size, 0) / 1048576).toFixed(1) + ' MB';
            preview.append(summary);
            files.forEach(file => {
                const row = document.createElement('div');
                row.className = 'admin-batch__file';
                row.dataset.invalid = String(file.size > maxBytes || !/^image\/(jpeg|png)$/.test(file.type));
                const name = document.createElement('span');
                name.textContent = file.name;
                const size = document.createElement('small');
                size.textContent = file.size > maxBytes ? 'Supera 15 MB' : (file.size / 1048576).toFixed(1) + ' MB';
                row.append(name, size);
                preview.append(row);
            });
            batchSubmit.disabled = !selectedCategory() || files.some(file => file.size > maxBytes || !/^image\/(jpeg|png)$/.test(file.type));
        };
        filesInput.addEventListener('change', renderBatchSelection);
        categoryChoice.addEventListener('change', syncBatchCategory);
        categoryInput.addEventListener('input', renderBatchSelection);
        syncBatchCategory();
        batchForm.addEventListener('submit', async event => {
            event.preventDefault();
            const files = [...(filesInput.files || [])];
            const category = selectedCategory();
            if (!files.length || !category || batchSubmit.disabled) return;
            batchSubmit.disabled = true;
            const draft = document.getElementById('batchDraft').checked;
            const outcomes = [];
            for (let index = 0; index < files.length; index++) {
                const file = files[index];
                batchStatus.textContent = 'Subiendo ' + (index + 1) + ' de ' + files.length + ': ' + file.name;
                const row = preview.querySelectorAll('.admin-batch__file')[index];
                row.dataset.state = 'uploading';
                try {
                    const body = new FormData();
                    body.set('action', 'batch_upload');
                    body.set('csrf', csrf);
                    body.set('category', category);
                    body.set('draft', draft ? '1' : '0');
                    body.set('photo', file, file.name);
                    const response = await fetch('/admin.php', {method: 'POST', body, credentials: 'same-origin'});
                    const result = await response.json();
                    if (!response.ok || !result?.ok) throw new Error(result?.error || 'No se pudo subir esta fotografía.');
                    row.dataset.state = 'success';
                    row.querySelector('small').textContent = 'Subida';
                    outcomes.push({ok: true});
                } catch (error) {
                    row.dataset.state = 'error';
                    row.querySelector('small').textContent = error.message || 'Error de subida';
                    outcomes.push({ok: false});
                }
            }
            const succeeded = outcomes.filter(result => result.ok).length;
            batchStatus.textContent = succeeded + ' de ' + files.length + ' fotografías subidas.' + (succeeded < files.length ? ' Las que fallaron siguen marcadas arriba.' : '');
            batchSubmit.textContent = 'Subida finalizada';
            if (succeeded) {
                const link = document.createElement('a');
                link.className = 'upload-submit admin-batch__library-link';
                link.href = '/admin.php?library=1&status=' + encodeURIComponent(succeeded + ' fotografías añadidas' + (draft ? ' como borradores.' : '.'));
                link.textContent = 'Abrir biblioteca';
                batchForm.append(link);
            }
        });
    }

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
    const categoryFilter = document.getElementById('photoLibraryCategory');
    const statusFilter = document.getElementById('photoLibraryStatus');
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
        const category = categoryFilter?.value || '';
        const state = statusFilter?.value || '';
        let visible = 0;
        grid.querySelectorAll('.admin-photo-card').forEach(card => {
            const matchesSearch = normalize(card.textContent + ' ' + card.dataset.photoFile).includes(needle);
            const matchesCategory = !category || card.dataset.photoCategory === category;
            const matchesState = !state || (state === 'draft' ? card.dataset.photoDraft === '1' : state === 'featured' ? card.dataset.photoFeatured === '1' : card.dataset.photoDraft !== '1');
            const match = matchesSearch && matchesCategory && matchesState;
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

    search?.addEventListener('input', filter);
    categoryFilter?.addEventListener('change', filter);
    statusFilter?.addEventListener('change', filter);

    const quickDialog = document.getElementById('adminQuickEdit');
    const quickForm = document.getElementById('adminQuickEditForm');
    if (quickDialog && quickForm) {
        const quickError = quickDialog.querySelector('[data-quick-error]');
        const quickSave = quickDialog.querySelector('[data-quick-save]');
        grid.addEventListener('click', event => {
            const button = event.target.closest('[data-quick-edit]');
            if (!button) return;
            const card = button.closest('.admin-photo-card');
            quickForm.querySelector('[data-quick-file]').value = card.dataset.photoFile || '';
            quickForm.elements.title.value = card.dataset.photoTitle || '';
            quickForm.elements.category.value = card.dataset.photoCategory || '';
            quickForm.elements.description.value = card.dataset.photoDescription || '';
            quickForm.elements.slug.value = card.dataset.photoSlug || '';
            quickForm.elements.latitude.value = card.dataset.photoLatitude || '';
            quickForm.elements.longitude.value = card.dataset.photoLongitude || '';
            quickForm.elements.featured.checked = card.dataset.photoFeatured === '1';
            quickForm.elements.draft.checked = card.dataset.photoDraft === '1';
            quickError.hidden = true;
            quickDialog.showModal();
            quickForm.elements.title.focus();
        });
        quickDialog.querySelector('[data-quick-cancel]')?.addEventListener('click', () => quickDialog.close());
        quickForm.addEventListener('submit', async event => {
            event.preventDefault();
            quickSave.disabled = true;
            quickError.hidden = true;
            try {
                const response = await fetch('/admin.php', {method: 'POST', body: new FormData(quickForm), credentials: 'same-origin'});
                const result = await response.json();
                if (!response.ok || !result?.ok) throw new Error(result?.error || 'No se pudieron guardar los cambios.');
                window.location.assign('/admin.php?library=1&status=' + encodeURIComponent('Fotografía actualizada.'));
            } catch (error) {
                quickError.textContent = error.message || 'Error de conexión. Vuelve a intentarlo.';
                quickError.hidden = false;
                quickSave.disabled = false;
            }
        });
    }

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
