/* Disuasión de descarga de imágenes. */

/* ---------- Disuasión de descarga de imágenes ---------- */
// Bloquea clic derecho y arrastre SOBRE imágenes (deja el resto de la web normal).
// No es infalible (la imagen llega al navegador igual), pero frena al usuario casual.
export const initImageProtection = () => {
    const blockOnImg = (e) => {
        if (e.target && e.target.tagName === 'IMG') e.preventDefault();
    };
    document.addEventListener('contextmenu', blockOnImg); // clic derecho + pulsación larga (Android)
    document.addEventListener('dragstart', blockOnImg);   // arrastrar al escritorio
};
