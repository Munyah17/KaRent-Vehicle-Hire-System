/**
 * Manual Tailwind build config for public/assets/css/app.css.
 *
 * Next.js does NOT compile Tailwind here (globals.css has no @tailwind
 * directives); the app links to the prebuilt public/assets/css/app.css,
 * which is generated from src/css/app.css via this config:
 *
 *   npm run build:css
 *
 * Content paths must cover every source of utility classes — the legacy
 * PHP templates AND the Next.js/TSX app.
 */
module.exports = {
    content: [
        './app/**/*.{ts,tsx,php}',
        './src/**/*.{ts,tsx}',
        './public/**/*.php',
    ],
    theme: {
        extend: {
            fontFamily: { sans: ['Poppins', 'ui-sans-serif', 'system-ui', 'sans-serif'] },
        },
    },
    plugins: [],
};
