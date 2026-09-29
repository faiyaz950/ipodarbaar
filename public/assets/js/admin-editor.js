/* Minimal rich-text editor for admin story text: formatted editing, HTML submitted via a hidden textarea. */
(function () {
    'use strict';

    var ALLOWED = ['P', 'BR', 'UL', 'OL', 'LI', 'STRONG', 'B', 'EM', 'I', 'U', 'H2', 'H3', 'H4', 'BLOCKQUOTE', 'A', 'TABLE', 'THEAD', 'TBODY', 'TR', 'TH', 'TD'];
    // Full http(s) links, or paths on this site such as /ipo-gmp.
    var LINK = /^(https?:\/\/|\/(?!\/))\S*$/i;
    var DROP = ['SCRIPT', 'STYLE', 'IFRAME', 'OBJECT', 'EMBED', 'IMG', 'SVG', 'VIDEO', 'AUDIO', 'FORM', 'INPUT', 'BUTTON'];

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
            Array.prototype.slice.call(child.attributes).forEach(function (a) { child.removeAttribute(a.name); });
            if (href && LINK.test(href)) { child.setAttribute('href', href); }
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
