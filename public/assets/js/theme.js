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

    function cookieTheme() {
        var m = document.cookie.match(/theme=(dark|light)/);
        return m ? m[1] : null;
    }

    function init() {
        // Wire every toggle button
        document.querySelectorAll('[data-theme-toggle]').forEach(function (btn) {
            btn.addEventListener('click', window.themeToggle);
            btn.setAttribute('aria-label', 'Toggle dark / light mode');
        });

        // First-visit chooser — honour both localStorage and the cookie
        // (some browsers/webviews don't persist localStorage).
        var stored = null;
        try { stored = localStorage.getItem(KEY); } catch (e) {}
        stored = stored || cookieTheme();
        if (stored) {
            try { localStorage.setItem(KEY, stored); } catch (e) {}
            return;
        }

        // Non-blocking floating card — it must never cover the page or
        // intercept taps, so there is deliberately no full-screen overlay.
        var box = document.createElement('div');
        box.id = 'theme-chooser';
        box.innerHTML =
            '<div class="theme-chooser-card">' +
            '<button type="button" class="theme-chooser-close" aria-label="Dismiss">×</button>' +
            '<div class="theme-icon">◐</div>' +
            '<h2 class="theme-chooser-title">Choose your appearance</h2>' +
            '<p class="theme-chooser-sub">You can change this anytime using the toggle in the header.</p>' +
            '<div style="display:flex;gap:.6rem">' +
            '<button type="button" class="theme-choice" data-choose="light">☀ Light</button>' +
            '<button type="button" class="theme-choice" data-choose="dark">🌙 Dark</button>' +
            '</div></div>';
        document.body.appendChild(box);
        box.classList.add('show');

        box.querySelector('.theme-chooser-close').addEventListener('click', function () {
            apply(current());
            box.remove();
        });
        box.querySelectorAll('[data-choose]').forEach(function (b) {
            b.addEventListener('click', function () {
                apply(b.getAttribute('data-choose'));
                box.remove();
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
