/**
 * Manual Tailwind build config for public/assets/css/app.css.
 *
 * Next.js does NOT compile Tailwind here (globals.css has no @tailwind
 * directives); the app links to the prebuilt public/assets/css/app.css,
 * which is generated from src/css/app.css via this config:
 *
 *   npm run build:css
 *
 * Content paths cover the Next.js/TSX app (the legacy PHP app lives in a
 * separate folder — do not scan for PHP templates here).
 */
module.exports = {
    content: [
        './app/**/*.{ts,tsx}',
        './src/**/*.{ts,tsx}',
    ],
    theme: {
        extend: {
            fontFamily: { sans: ['Poppins', 'ui-sans-serif', 'system-ui', 'sans-serif'] },
        },
    },
    plugins: [],
};
