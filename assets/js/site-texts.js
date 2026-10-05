(() => {
    const node = document.getElementById('siteTexts');
    const texts = node ? JSON.parse(node.textContent) : {};
    window.siteText = (text) => Object.prototype.hasOwnProperty.call(texts, text) ? texts[text] : text;
    window.siteTextHTML = (text) => window.siteText(text).replace(/[&<>"']/g, (char) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    })[char]);
})();
