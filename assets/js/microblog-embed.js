/* Script opcional para quien incrusta un post: ajusta el alto del <iframe> al contenido real. */
(function () {
    'use strict';
    window.addEventListener('message', function (event) {
        var data = event.data;
        if (!data || data.type !== 'djl-microblog-height' || !(data.height > 0)) return;
        var frames = document.querySelectorAll('iframe.djl-microblog-embed');
        for (var i = 0; i < frames.length; i++) {
            if (frames[i].contentWindow === event.source) frames[i].style.height = Math.min(4000, data.height) + 'px';
        }
    });
})();
