/* Galería: filtrado, paginación en el cliente y maquetación. */
import { $, $$, escHtml } from './helpers.js';
import { updateMapForCategory } from './map.js';

export const initGallery = () => {
    const gallery = $('#masonry');
    if (!gallery) return;
    const getGalleryDesign = () => window.matchMedia('(max-width: 768px)').matches
        ? document.body.dataset.galleryMobile : document.body.dataset.galleryDesktop;
    const initialCards = $$('.card', gallery || document);
    let galleryDesign = getGalleryDesign();
    document.body.dataset.galleryActive = galleryDesign;
    const buildCategoryRails = () => {
        const groups = new Map();
        initialCards.forEach(card => {
            const category = card.dataset.category || 'Otras';
            if (!groups.has(category)) groups.set(category, []);
            groups.get(category).push(card);
        });
        const fragment = document.createDocumentFragment();
        groups.forEach((cards, category) => {
            const row = document.createElement('section');
            row.className = 'category-rail';
            row.dataset.category = category;
            const heading = document.createElement('h2');
            heading.className = 'category-rail__title';
            heading.textContent = category;
            const track = document.createElement('div');
            track.className = 'category-rail__track';
            cards.forEach(card => track.append(card));
            const header = document.createElement('div');
            header.className = 'category-rail__header';
            const controls = document.createElement('div');
            controls.className = 'category-rail__controls';
            track.id = 'category-track-' + fragment.childElementCount;
            track.setAttribute('aria-label', category);
            const arrows = [-1, 1].map(direction => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'category-rail__arrow';
                button.textContent = direction < 0 ? '‹' : '›';
                button.setAttribute('aria-label', (direction < 0 ? 'Fotos anteriores de ' : 'Más fotos de ') + category);
                button.setAttribute('aria-controls', track.id);
                button.addEventListener('click', () => track.scrollBy({
                    left: direction * track.clientWidth * .85,
                    behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth'
                }));
                controls.append(button);
                return button;
            });
            const updateArrows = () => {
                arrows[0].disabled = track.scrollLeft < 2;
                arrows[1].disabled = track.scrollLeft + track.clientWidth >= track.scrollWidth - 2;
            };
            track.addEventListener('scroll', updateArrows, { passive: true });
            row.addEventListener('rail:resize', updateArrows);
            header.append(heading, controls);
            row.append(header, track);
            fragment.append(row);
        });
        gallery.replaceChildren(fragment);
    };
    if (galleryDesign === 'category-rails') buildCategoryRails();
    const allCards = initialCards;
    if (!allCards.length) return;

    const filterEl      = $('#categoriesFilter');
    const categorySelect = $('#mapCategory');
    const jsPagination  = $('#jsPagination');
    // Fotos por página: `photos_mobile` / `photos_desktop` del panel (data-photos-* en <body>); 0 = todas.
    // Es independiente de las columnas. El modo «filas por categoría» siempre muestra todas.
    const perPage = () => {
        if (getGalleryDesign() === 'category-rails') return Number.MAX_SAFE_INTEGER;
        const mobile = window.matchMedia('(max-width: 768px)').matches;
        const attribute = mobile ? 'data-photos-mobile' : 'data-photos-desktop';
        const raw = document.body.getAttribute(attribute);
        const configured = raw === null || raw.trim() === '' ? NaN : Number(raw);
        if (configured === 0) return Number.MAX_SAFE_INTEGER;
        return Number.isSafeInteger(configured) && configured > 0 ? configured : (mobile ? 12 : 20);
    };
    let   currentCat    = '';
    let   currentLocation = null;
    let   currentPage   = 1;

    const getFiltered = () => {
        let cards = currentCat ? allCards.filter(c => c.dataset.category === currentCat) : allCards;
        if (currentLocation) cards = cards.filter(card => `${Number.parseFloat(card.dataset.lat)},${Number.parseFloat(card.dataset.lng)}` === currentLocation);
        if (document.body.classList.contains('favorites-only')) {
            let saved = [];
            try { saved = JSON.parse(localStorage.getItem('djl-photo-favorites-v1') || '[]'); } catch (_) {}
            const favorites = new Set(Array.isArray(saved) ? saved : []);
            cards = cards.filter(card => favorites.has(card.dataset.slug || ''));
        }
        return cards;
    };

    const pageRange = (current, total) => {
        if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1);
        const r = [1];
        if (current > 3) r.push('...');
        for (let i = Math.max(2, current - 1); i <= Math.min(total - 1, current + 1); i++) r.push(i);
        if (current < total - 2) r.push('...');
        r.push(total);
        return r;
    };

    const scrollToGallery = () => {
        const el = document.getElementById('galeria');
        if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    const renderPagination = (total, totalPages) => {
        if (!jsPagination) return;
        if (totalPages <= 1) {
            jsPagination.hidden = true;
            jsPagination.style.display = 'none';
            return;
        }
        jsPagination.hidden = false;
        jsPagination.style.display = '';

        const hasPrev = currentPage > 1;
        const hasNext = currentPage < totalPages;
        const label   = currentCat
            ? `${escHtml(window.siteText(total === 1 ? '{count} foto en' : '{count} fotos en').replace('{count}', total))} <em>${escHtml(currentCat)}</em>`
            : escHtml(window.siteText(total === 1 ? '{count} fotografía en total' : '{count} fotografías en total').replace('{count}', total));

        jsPagination.innerHTML = `
                <div class="pagination__controls">
                    <button class="pagination__nav pagination__nav--prev${hasPrev ? '' : ' pagination__nav--disabled'}"
                        ${hasPrev ? '' : 'disabled'} aria-label="${window.siteTextHTML("Página anterior")}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M15 6l-6 6 6 6"/></svg>
                        <span>${window.siteTextHTML("Anterior")}</span>
                    </button>
                    <div class="pagination__pages">
                        ${pageRange(currentPage, totalPages).map(n => n === '...'
                            ? `<span class="pagination__ellipsis">…</span>`
                            : `<button class="pagination__page${n === currentPage ? ' is-current' : ''}" data-page="${n}"
                               ${n === currentPage ? 'aria-current="page"' : ''}>${n}</button>`
                        ).join('')}
                    </div>
                    <button class="pagination__nav pagination__nav--next${hasNext ? '' : ' pagination__nav--disabled'}"
                        ${hasNext ? '' : 'disabled'} aria-label="${window.siteTextHTML("Página siguiente")}">
                        <span>${window.siteTextHTML("Siguiente")}</span>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 6l6 6-6 6"/></svg>
                    </button>
                </div>
                <p class="pagination__info">${window.siteTextHTML("\n                    Página ")}<strong>${currentPage}</strong>${window.siteTextHTML(" de ")}${totalPages}
                    <span class="pagination__sep">·</span> ${label}
                </p>`;

        jsPagination.querySelector('.pagination__nav--prev')?.addEventListener('click', () => {
            if (currentPage > 1) { currentPage--; render(); scrollToGallery(); }
        });
        jsPagination.querySelector('.pagination__nav--next')?.addEventListener('click', () => {
            if (currentPage < totalPages) { currentPage++; render(); scrollToGallery(); }
        });
        jsPagination.querySelectorAll('.pagination__page:not(.is-current)').forEach(btn => {
            btn.addEventListener('click', () => {
                currentPage = parseInt(btn.dataset.page, 10);
                render();
                scrollToGallery();
            });
        });
    };

    const render = () => {
        const activeDesign = getGalleryDesign();
        if (activeDesign !== galleryDesign) {
            if (activeDesign === 'category-rails') buildCategoryRails();
            else if (galleryDesign === 'category-rails') gallery.replaceChildren(...allCards);
            galleryDesign = activeDesign;
            document.body.dataset.galleryActive = activeDesign;
        }
        const filtered   = getFiltered();
        const total      = filtered.length;
        const pageSize   = perPage();
        const totalPages = Math.max(1, Math.ceil(total / pageSize));
        if (currentPage > totalPages) currentPage = 1;

        const start     = (currentPage - 1) * pageSize;
        const pageCards = filtered.slice(start, start + pageSize);
        const visibleCards = new Set(pageCards);

        // La página activa es el único conjunto que puede mostrarse. El display inline !important
        // evita que una regla de cualquier diseño vuelva a pintar tarjetas fuera del límite.
        allCards.forEach(c => {
            const isOnPage = visibleCards.has(c);
            c.classList.toggle('is-hidden', !isOnPage);
            c.hidden = !isOnPage;
            if (isOnPage) c.style.removeProperty('display');
            else c.style.setProperty('display', 'none', 'important');
        });
        pageCards.forEach((c, i) => {
            // Fuerza el reveal si no lo tiene aún (entraron al DOM sin pasar por el viewport)
            if (!c.classList.contains('is-revealed')) {
                setTimeout(() => c.classList.add('is-revealed'), i * 50);
            }
        });

        if (gallery && activeDesign === 'category-rails') {
            $$('.category-rail', gallery).forEach(row => {
                row.hidden = !$('.card:not(.is-hidden)', row);
            });
        }
        layoutMasonryCards(gallery, activeDesign);

        renderPagination(total, totalPages);
        document.dispatchEvent(new CustomEvent('gallery:rendered', { detail: { category: currentCat, page: currentPage } }));
        if (window.photoMap) updateMapForCategory(currentCat);
    };

    document.addEventListener('favorites:changed', () => {
        currentPage = 1;
        render();
    });

    document.addEventListener('gallery:filter-location', event => {
        currentLocation = event.detail?.location || null;
        currentCat = '';
        currentPage = 1;
        $$('.categories-filter__chip', filterEl || document).forEach(chip => chip.classList.remove('is-active'));
        if (categorySelect) categorySelect.value = '';
        render();
    });

    // Chips de categoría
    if (filterEl) {
        const chips = $$('.categories-filter__chip', filterEl);
        chips.forEach(chip => {
            chip.addEventListener('click', () => {
                chips.forEach(c => c.classList.remove('is-active'));
                chip.classList.add('is-active');
                currentLocation = null;
                currentCat  = chip.dataset.category || '';
                currentPage = 1;
                render();
            });
        });
    }

    if (categorySelect) {
        categorySelect.addEventListener('change', () => {
            currentLocation = null;
            currentCat = categorySelect.value || '';
            currentPage = 1;
            render();
        });
    }

    const collectionCards = $$('.collection-card[data-collection-category]');
    collectionCards.forEach(card => {
        card.addEventListener('click', () => {
            currentLocation = null;
            currentCat = card.dataset.collectionCategory || '';
            currentPage = 1;
            $$('.categories-filter__chip', filterEl || document).forEach(chip => {
                const active = (chip.dataset.category || '') === currentCat;
                chip.classList.toggle('is-active', active);
            });
            collectionCards.forEach(item => {
                const active = item === card;
                item.classList.toggle('is-active', active);
                item.setAttribute('aria-pressed', active ? 'true' : 'false');
            });
            render();
            document.querySelector('#masonry')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });

    // La tarjeta de colección sincroniza el filtro existente.
    // Si la URL es /foto/slug, muestra la página que contiene esa foto
    const openSlug = document.body.dataset.openSlug;
    if (openSlug) {
        const targetIdx = allCards.findIndex(c => c.dataset.slug === openSlug);
        if (targetIdx !== -1) {
            currentPage = Math.ceil((targetIdx + 1) / perPage());
        }
    }

    render(); // render inicial
    gallery?.addEventListener('load', event => {
        if (!event.target.matches('.card__img')) return;
        requestAnimationFrame(() => layoutMasonryCards(gallery, getGalleryDesign()));
    }, true);
    window.addEventListener('resize', () => {
        requestAnimationFrame(() => {
            if (getGalleryDesign() !== galleryDesign) render();
            else layoutMasonryCards(gallery, galleryDesign);
        });
    }, { passive: true });
    let lastGalleryWidth = gallery.clientWidth;
    new ResizeObserver(() => {
        if (gallery.clientWidth === lastGalleryWidth) return;
        lastGalleryWidth = gallery.clientWidth;
        layoutMasonryCards(gallery, getGalleryDesign());
    }).observe(gallery);
    window.matchMedia('(max-width: 768px)').addEventListener('change', () => {
        currentPage = 1;
        render();
    });
};

const layoutMasonryCards = (gallery, design) => window.layoutPhotoGallery?.(gallery, design);
