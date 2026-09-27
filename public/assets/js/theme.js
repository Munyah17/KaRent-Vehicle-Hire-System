/**
 * KaRent theme switcher — light/dark mode.
 * Stores choice in localStorage + a cookie so it survives across visits.
 * First-time visitors get a chooser prompt.
 */
(function () {
    var KEY = 'karent.theme';

    function current() {
        return document.documentElement.classList.contains('dark') ? 'dark' : 'light';
    }

    function apply(t) {
        document.documentElement.classList.toggle('dark', t === 'dark');
        document.documentElement.setAttribute('data-bs-theme', t);
        try { localStorage.setItem(KEY, t); } catch (e) {}
        document.cookie = 'theme=' + t + ';path=/;max-age=31536000;SameSite=Lax';
    }

    window.themeToggle = function () {
        apply(current() === 'dark' ? 'light' : 'dark');
    };

    document.addEventListener('DOMContentLoaded', function () {
        // Wire every toggle button
        document.querySelectorAll('[data-theme-toggle]').forEach(function (btn) {
            btn.addEventListener('click', window.themeToggle);
            btn.setAttribute('aria-label', 'Toggle dark / light mode');
        });

        // First-visit chooser
        var stored = null;
        try { stored = localStorage.getItem(KEY); } catch (e) {}
        if (stored) return;

        var box = document.createElement('div');
        box.id = 'theme-chooser';
        box.innerHTML =
            '<div class="theme-chooser-card">' +
            '<div style="font-size:2rem;line-height:1">◐</div>' +
            '<h2 style="font-size:1.05rem;font-weight:600;margin:.5rem 0 .25rem">Choose your appearance</h2>' +
            '<p style="font-size:.8rem;opacity:.7;margin-bottom:1rem">You can change this anytime using the toggle in the header.</p>' +
            '<div style="display:flex;gap:.75rem">' +
            '<button type="button" class="theme-choice" data-choose="light">☀ Light</button>' +
            '<button type="button" class="theme-choice" data-choose="dark">🌙 Dark</button>' +
            '</div></div>';
        document.body.appendChild(box);
        box.classList.add('show');

        box.querySelectorAll('[data-choose]').forEach(function (b) {
            b.addEventListener('click', function () {
                apply(b.getAttribute('data-choose'));
                box.remove();
            });
        });
    });
})();
