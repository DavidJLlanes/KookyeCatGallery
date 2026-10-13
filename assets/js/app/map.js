/* Mapa interactivo (Leaflet) y su filtrado por categoría. */
import { $, $$, CARDS, galleryRoot } from './helpers.js';

/* ---------- Mapa interactivo ---------- */
let photoMap = null;

export const initMap = () => {
    const mapEl = $('#map');
    if (!mapEl) return;
    if (photoMap) return;
    if (typeof L === 'undefined') {
        if (mapEl.dataset.leafletWaiting !== 'true') {
            mapEl.dataset.leafletWaiting = 'true';
            window.addEventListener('leaflet:ready', () => {
                delete mapEl.dataset.leafletWaiting;
                initMap();
            }, { once: true });
            window.addEventListener('leaflet:failed', () => {
                delete mapEl.dataset.leafletWaiting;
                mapEl.textContent = 'No se pudo cargar el mapa. Comprueba tu conexión e inténtalo de nuevo.';
            }, { once: true });
        }
        return;
    }

    const cards = $$(CARDS);

    // Agrupa fotos por ubicación
    const locations = {};
    cards.forEach(card => {
        const lat = parseFloat(card.dataset.lat);
        const lng = parseFloat(card.dataset.lng);
        if (!isNaN(lat) && !isNaN(lng) && lat !== 0 && lng !== 0) {
            const key = `${lat},${lng}`;
            const cat = card.dataset.category || 'Otras';
            if (!locations[key]) locations[key] = { lat, lng, categories: {}, count: 0, cards: [] };
            locations[key].count++;
            locations[key].cards.push(card);
            locations[key].categories[cat] = (locations[key].categories[cat] || 0) + 1;
        }
    });

    try {
        // Ajusta el mapa a todas las ubicaciones; sin coordenadas, muestra una vista mundial.
        const locationList = Object.values(locations);
        const hasLocations = locationList.length > 0;
        photoMap = L.map(mapEl).setView(
            hasLocations ? [locationList[0].lat, locationList[0].lng] : [20, 0],
            hasLocations ? 9 : 2
        );

        // Tile layer oscuro
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            maxZoom: 19,
        }).addTo(photoMap);

        // Añade marcadores
        locationList.forEach(loc => {
            const marker = L.circleMarker([loc.lat, loc.lng], {
                radius: 20,
                fillColor: getComputedStyle(document.body).getPropertyValue('--accent').trim(),
                color: getComputedStyle(document.body).getPropertyValue('--accent').trim(),
                weight: 2,
                opacity: 0.9,
                fillOpacity: 0.85,
            }).addTo(photoMap);

            marker._photoCount = loc.count;
            marker._categories = loc.categories;
            marker._location = `${loc.lat},${loc.lng}`;

            marker.bindPopup(loc.count === 1
                ? `<button class="map-popup__view" type="button">${window.siteTextHTML("1 foto")}<br><small>${window.siteTextHTML("(pincha para ver)")}</small></button>`
                : `<div style="color: #0a0a0a; font-weight: 500; cursor: pointer;">
                        ${window.siteTextHTML("{count} fotos").replace("{count}", loc.count)}<br>
                        <small style="opacity: 0.7;">${window.siteTextHTML("(pincha para ver)")}</small>
                    </div>`);

            if (loc.count === 1) {
                marker.on('popupopen', () => {
                    const button = marker.getPopup()?.getElement()?.querySelector('.map-popup__view');
                    if (!button) return;
                    button.onclick = () => {
                        marker.closePopup();
                        galleryRoot()?.dispatchEvent(new CustomEvent('map:open-photo', {
                            detail: { card: loc.cards[0] }
                        }));
                    };
                });
            }

            // Filtering has one owner: it updates visibility, geometry and pagination together.
            marker.on('click', () => {
                document.dispatchEvent(new CustomEvent('gallery:filter-location', {
                    detail: { location: marker._location }
                }));
            });
        });

        if (hasLocations) {
            const bounds = L.latLngBounds(locationList.map(loc => [loc.lat, loc.lng]));
            photoMap.fitBounds(bounds, { padding: [32, 32], maxZoom: 9 });
        }

        window.photoMap = photoMap;
    } catch (e) {
        console.error('Error inicializando mapa:', e);
    }
};

export const updateMapForCategory = (category) => {
    if (!photoMap) return;
    photoMap.eachLayer(layer => {
        if (layer._photoCount !== undefined) {
            const hasCategory = category === '' || layer._categories[category];
            layer.setStyle({
                opacity: hasCategory ? 0.9 : 0.2,
                fillOpacity: hasCategory ? 0.85 : 0.1,
            });
        }
    });
};

// El mapa arranca por separado para que un error de otra parte de la galería no lo deje vacío.
const initMapIndependently = () => {
    try {
        initMap();
    } catch (error) {
        console.error('Error inicializando el mapa:', error);
    }
};
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initMapIndependently, { once: true });
} else {
    initMapIndependently();
}
window.addEventListener('load', initMapIndependently, { once: true });
