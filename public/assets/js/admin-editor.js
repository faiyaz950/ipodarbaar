/* Minimal rich-text editor for admin story text: formatted editing, HTML submitted via a hidden textarea. */
(function () {
    'use strict';

    var ALLOWED = ['P', 'BR', 'UL', 'OL', 'LI', 'STRONG', 'B', 'EM', 'I', 'U', 'H2', 'H3', 'H4', 'BLOCKQUOTE', 'A', 'TABLE', 'THEAD', 'TBODY', 'TR', 'TH', 'TD', 'FIGURE', 'FIGCAPTION', 'IMG'];
    // Images must come from this site's uploads folder (the "+ Image" button puts them there).
    var IMAGE = /^\/uploads\/[\w\/.-]+\.(webp|png|jpe?g)$/i;
    // Full http(s) links, or paths on this site such as /ipo-gmp.
    var LINK = /^(https?:\/\/|\/(?!\/))\S*$/i;
    var DROP = ['SCRIPT', 'STYLE', 'IFRAME', 'OBJECT', 'EMBED', 'SVG', 'VIDEO', 'AUDIO', 'FORM', 'INPUT', 'BUTTON'];

    /** Keep only formatting tags (no attributes except http(s) link targets); unwrap everything else. */
    function clean(html) {
        var tpl = document.createElement('template');
        tpl.innerHTML = html;
        walk(tpl.content);
        return tpl.innerHTML
            .replace(/<p>(\s|&nbsp;|<br>)*<\/p>/gi, '')
            .trim();
    }

    function walk(node) {
        Array.prototype.slice.call(node.childNodes).forEach(function (child) {
            if (child.nodeType === 8) { child.remove(); return; }
            if (child.nodeType !== 1) { return; }
            var tag = child.tagName;
            if (DROP.indexOf(tag) !== -1) { child.remove(); return; }
            walk(child);
            if (tag === 'DIV') {
                var p = document.createElement('p');
                while (child.firstChild) { p.appendChild(child.firstChild); }
                child.replaceWith(p);
                return;
            }
            if (ALLOWED.indexOf(tag) === -1) {
                child.replaceWith.apply(child, Array.prototype.slice.call(child.childNodes));
                return;
            }
            var href = tag === 'A' ? child.getAttribute('href') : null;
            var keep = {};
            if (tag === 'IMG') {
                ['src', 'alt', 'width', 'height'].forEach(function (name) { keep[name] = child.getAttribute(name); });
                if (!keep.src || !IMAGE.test(keep.src)) { child.remove(); return; }
            }
            Array.prototype.slice.call(child.attributes).forEach(function (a) { child.removeAttribute(a.name); });
            if (href && LINK.test(href)) { child.setAttribute('href', href); }
            Object.keys(keep).forEach(function (name) { if (keep[name]) { child.setAttribute(name, keep[name]); } });
        });
    }

    function init(root) {
        var body = root.querySelector('[data-rte-body]');
        var input = root.querySelector('[data-rte-input]');
        var sourceBtn = root.querySelector('[data-rte-source]');
        var dirty = false;
        var sourceMode = false;

        body.innerHTML = clean(input.value);

        body.addEventListener('input', function () { dirty = true; });
        body.addEventListener('focus', function () {
            document.execCommand('defaultParagraphSeparator', false, 'p');
        });
        body.addEventListener('paste', function (e) {
            e.preventDefault();
            var text = (e.clipboardData || window.clipboardData).getData('text/plain');
            document.execCommand('insertText', false, text);
        });

        root.querySelectorAll('[data-cmd]').forEach(function (btn) {
            btn.addEventListener('mousedown', function (e) { e.preventDefault(); });
            btn.addEventListener('click', function () {
                if (sourceMode) { return; }
                body.focus();
                var cmd = btn.getAttribute('data-cmd');
                var arg = btn.getAttribute('data-arg');
                if (cmd === 'createLink') {
                    arg = window.prompt('Link URL (https://… or a page on this site like /ipo-gmp)', 'https://');
                    if (!arg || !LINK.test(arg)) { return; }
                }
                document.execCommand(cmd, false, cmd === 'formatBlock' ? '<' + arg + '>' : arg);
                dirty = true;
            });
        });

        // [[ipo:slug]] is replaced by a live IPO card when the post is shown.
        var ipoBtn = root.querySelector('[data-rte-ipo]');
        if (ipoBtn) {
            ipoBtn.addEventListener('mousedown', function (e) { e.preventDefault(); });
            ipoBtn.addEventListener('click', function () {
                if (sourceMode) { return; }
                var slug = (window.prompt('IPO page address or slug, e.g. https://ipodarbaar.in/ipo/abc-ipo or abc-ipo', '') || '').trim();
                slug = slug.replace(/[?#].*$/, '').replace(/\/+$/, '').split('/').pop().toLowerCase();
                if (!/^[a-z0-9-]+$/.test(slug)) { return; }
                body.focus();
                document.execCommand('insertHTML', false, '<p>[[ipo:' + slug + ']]</p>');
                dirty = true;
            });
        }

        // "+ Image" uploads a picture to the site and inserts it with its description and caption.
        var imageBtn = root.querySelector('[data-rte-image]');
        var imageInput = root.querySelector('[data-rte-image-input]');
        var savedRange = null;
        if (imageBtn && imageInput) {
            imageBtn.addEventListener('mousedown', function (e) {
                e.preventDefault();
                var sel = window.getSelection();
                savedRange = sel.rangeCount && body.contains(sel.anchorNode) ? sel.getRangeAt(0).cloneRange() : null;
            });
            imageBtn.addEventListener('click', function () { if (!sourceMode) { imageInput.click(); } });
            imageInput.addEventListener('change', function () {
                var file = imageInput.files[0];
                if (!file) { return; }
                var data = new FormData();
                data.append('image', file);
                data.append('_token', root.closest('form').querySelector('input[name="_token"]').value);
                imageBtn.disabled = true;
                imageBtn.textContent = 'Uploading…';
                fetch(imageBtn.getAttribute('data-upload'), { method: 'POST', body: data, headers: { Accept: 'application/json' }, credentials: 'same-origin' })
                    .then(function (res) { return res.json().then(function (json) { return { ok: res.ok, json: json }; }); })
                    .then(function (result) {
                        if (!result.ok) {
                            var errors = result.json.errors && result.json.errors.image;
                            window.alert(errors ? errors[0] : 'The image could not be uploaded.');
                            return;
                        }
                        var alt = window.prompt('Describe the image (alt text, for Google and screen readers)', '') || '';
                        var caption = window.prompt('Caption shown under the image (optional)', '') || '';
                        var figure = document.createElement('figure');
                        var img = document.createElement('img');
                        img.setAttribute('src', result.json.url);
                        img.setAttribute('alt', alt);
                        if (result.json.width) { img.setAttribute('width', result.json.width); img.setAttribute('height', result.json.height); }
                        figure.appendChild(img);
                        if (caption) {
                            var cap = document.createElement('figcaption');
                            cap.textContent = caption;
                            figure.appendChild(cap);
                        }
                        body.focus();
                        if (savedRange) {
                            var sel = window.getSelection();
                            sel.removeAllRanges();
                            sel.addRange(savedRange);
                        }
                        document.execCommand('insertHTML', false, figure.outerHTML + '<p><br></p>');
                        dirty = true;
                    })
                    .catch(function () { window.alert('The image could not be uploaded.'); })
                    .then(function () {
                        imageBtn.disabled = false;
                        imageBtn.textContent = '+ Image';
                        imageInput.value = '';
                    });
            });
        }

        sourceBtn.addEventListener('click', function () {
            if (!sourceMode) {
                if (dirty) { input.value = clean(body.innerHTML); }
                input.hidden = false;
                body.hidden = true;
            } else {
                body.innerHTML = clean(input.value);
                input.hidden = true;
                body.hidden = false;
                dirty = true;
            }
            sourceMode = !sourceMode;
            sourceBtn.classList.toggle('active', sourceMode);
            root.classList.toggle('is-source', sourceMode);
        });

        // Untouched editors submit the original HTML unchanged, so the story keeps following the API.
        root.closest('form').addEventListener('submit', function () {
            if (!sourceMode && dirty) { input.value = clean(body.innerHTML); }
        });
    }

    document.querySelectorAll('[data-rte]').forEach(init);
})();
