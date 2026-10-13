<?php
declare(strict_types=1);

/**
 * Panel flotante «Nuevo post» para el propietario, dentro de la propia web.
 *
 * Solo se pinta si hay sesión de administrador (index.php). Se abre desde el botón «+» (assets/js/fab-menu.js →
 * evento `mbc:open`) y envía el formulario por fetch a admin.php (acción `microblog_save`, con `ajax=1`), que
 * responde en JSON. Usa las mismas reglas que el panel (inc/microblog.php) y el mismo JS de formulario
 * (assets/js/microblog-admin.js: contador, enlaces, imágenes y botón de enlace en el texto).
 *
 * Marcado: assets/css/microblog-composer.css · Comportamiento: assets/js/microblog-composer.js
 */

function microblog_composer_html(string $csrf): string
{
    $e = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $icon = static fn(string $paths): string => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $paths . '</svg>';

    $levels = '';
    $descriptions = [0 => 'Tarjeta sencilla', 1 => 'Franja de color', 2 => 'Borde luminoso'];
    foreach (microblog_levels() as $value => $label) {
        $levels .= '<label class="mbc-level mbc-level--' . $value . '"><input type="radio" name="level" value="' . $value . '"' . ($value === 0 ? ' checked' : '') . '>'
            . '<span class="mbc-level__preview" aria-hidden="true"><i></i><i></i><i></i></span>'
            . '<span class="mbc-level__name">' . $e($label) . '</span><small>' . $e($descriptions[$value]) . '</small></label>';
    }

    $linkRows = '';
    for ($i = 0; $i < MICROBLOG_MAX_LINKS; $i++) {
        $linkRows .= '<div class="mbc-row" data-mb-link-row' . ($i > 0 ? ' hidden' : '') . '>'
            . '<label class="mbc-field"><span>Enlace ' . ($i + 1) . '</span><input name="link_url[]" type="url" maxlength="500" placeholder="https://…" autocomplete="off"></label>'
            . '<label class="mbc-field"><span>Título (opcional)</span><input name="link_title[]" type="text" maxlength="120"></label></div>';
    }

    return '<div class="mbc" data-mbc hidden>'
        . '<div class="mbc__scrim" data-mbc-close></div>'
        . '<section class="mbc__panel" role="dialog" aria-modal="true" aria-labelledby="mbc-title" tabindex="-1">'
        . '<div class="mbc__grip" data-mbc-grip aria-hidden="true"></div>'
        . '<header class="mbc__head"><img class="mbc__avatar" src="' . $e(site_media_src('profile_image')) . '" alt="" width="44" height="44">'
        . '<div class="mbc__titles"><h2 id="mbc-title">Nuevo post</h2><p>' . $e(site_text('text_4577bab0d9627af1')) . ' · se publica en el microblog</p></div>'
        . '<button type="button" class="mbc__close" data-mbc-close aria-label="Cerrar">' . $icon('<path d="M6 6l12 12M18 6L6 18"/>') . '</button></header>'

        . '<form class="mbc__form" method="post" action="/admin.php" enctype="multipart/form-data" data-mb-admin-form data-mbc-form novalidate>'
        . '<input type="hidden" name="action" value="microblog_save"><input type="hidden" name="ajax" value="1"><input type="hidden" name="csrf" value="' . $e($csrf) . '">'
        . '<div class="mbc__body">'

        // Texto con barra de herramientas (enlace) y contador.
        . '<div class="mbc-editor">'
        . '<div class="mbc-editor__bar"><button type="button" class="mbc-tool" data-mb-link-tool title="Insertar enlace (Ctrl+K)">' . $icon('<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/>') . '<span>Enlace</span></button>'
        . '<span class="mbc-editor__count"><span data-mb-count>0</span> / ' . MICROBLOG_MAX_TEXT . '</span></div>'
        . '<div class="mbc-linkbox" data-mb-linkbox hidden>'
        . '<label class="mbc-field"><span>Texto que se verá</span><input type="text" data-mb-link-label maxlength="200" placeholder="mi web"></label>'
        . '<label class="mbc-field"><span>Dirección</span><input type="url" data-mb-link-href maxlength="500" placeholder="https://…"></label>'
        . '<div class="mbc-linkbox__actions"><button type="button" class="mbc-btn mbc-btn--ghost" data-mb-link-cancel>Cancelar</button><button type="button" class="mbc-btn mbc-btn--solid" data-mb-link-apply>Insertar</button></div>'
        . '<p class="mbc-hint" data-mb-link-error role="alert" hidden></p></div>'
        . '<textarea name="text" rows="5" maxlength="' . MICROBLOG_MAX_TEXT . '" placeholder="¿Qué quieres compartir?" aria-label="Texto del post" data-mb-text data-mbc-first></textarea>'
        . '<p class="mbc-hint">Para un enlace con texto escribe <code>[texto](https://…)</code> o usa el botón Enlace. Las URLs sueltas se enlazan solas.</p>'
        . '</div>'

        // Añadir: cita, enlaces, imágenes.
        . '<div class="mbc-chips" role="group" aria-label="Añadir al post">'
        . '<button type="button" class="mbc-chip" data-mbc-toggle="quote" aria-expanded="false">' . $icon('<path d="M7 7h4v4H7zM13 7h4v4h-4zM7 11c0 3 0 4-2 6M13 11c0 3 0 4-2 6"/>') . 'Cita</button>'
        . '<button type="button" class="mbc-chip" data-mbc-toggle="links" aria-expanded="false">' . $icon('<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/>') . 'Enlaces</button>'
        . '<button type="button" class="mbc-chip" data-mbc-toggle="images" aria-expanded="false">' . $icon('<rect x="3" y="4" width="18" height="16" rx="3"/><circle cx="8.5" cy="9.5" r="1.6"/><path d="M21 16l-5-5-8 9"/>') . 'Imágenes</button></div>'

        . '<section class="mbc-section" data-mbc-section="quote" hidden><label class="mbc-field"><span>Texto de la cita</span><textarea name="quote_text" rows="3" maxlength="' . MICROBLOG_MAX_QUOTE . '"></textarea></label>'
        . '<div class="mbc-row"><label class="mbc-field"><span>Fuente</span><input name="quote_source" type="text" maxlength="120" placeholder="Autor, libro, web…"></label>'
        . '<label class="mbc-field"><span>Enlace de la fuente</span><input name="quote_url" type="url" maxlength="500" placeholder="https://…"></label></div></section>'

        . '<section class="mbc-section" data-mbc-section="links" hidden>' . $linkRows
        . '<button type="button" class="mbc-add" data-mb-add-link>＋ Añadir otro enlace</button></section>'

        . '<section class="mbc-section" data-mbc-section="images" hidden>'
        . '<label class="mbc-drop"><input name="images[]" type="file" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" multiple data-mb-images data-mb-max="' . MICROBLOG_MAX_IMAGES . '">'
        . '<span class="mbc-drop__icon">' . $icon('<path d="M12 16V4M7 9l5-5 5 5M4 16v3a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-3"/>') . '</span>'
        . '<span class="mbc-drop__text"><strong>Elegir imágenes</strong><small>Hasta ' . MICROBLOG_MAX_IMAGES . ' · JPG, PNG o WebP · se guardan en WebP (máx. 1500 px)</small></span></label>'
        . '<div class="mbc-previews" data-mb-previews></div></section>'

        // Destacado, fijado y programación.
        . '<fieldset class="mbc-levels"><legend>Destacado</legend><div class="mbc-levels__grid">' . $levels . '</div></fieldset>'
        . '<div class="mbc-options">'
        . '<label class="mbc-switch"><input type="checkbox" name="pinned" value="1"><span class="mbc-switch__track" aria-hidden="true"></span><span>Fijar arriba del feed</span></label>'
        . '<label class="mbc-field mbc-field--schedule"><span>Programar publicación (opcional)</span><input name="publish_at" type="datetime-local"><small>Hora de España. Vacío = publicar ahora.</small></label>'
        . '</div>'
        . '</div>'

        . '<footer class="mbc__foot"><p class="mbc__status" data-mbc-status role="status" aria-live="polite"></p>'
        . '<button type="button" class="mbc-btn mbc-btn--ghost" data-mbc-close>Cancelar</button>'
        . '<button type="submit" class="mbc-btn mbc-btn--solid mbc-btn--publish" data-mbc-submit><span class="mbc-btn__label">Publicar</span><span class="mbc-btn__spinner" aria-hidden="true"></span></button></footer>'
        . '</form></section></div>';
}
