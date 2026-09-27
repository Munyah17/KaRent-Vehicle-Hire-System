/** Tailwind config — scans all PHP views for class usage. */
module.exports = {
  content: [
    './public/**/*.php',
    './app/views/**/*.php',
    './app/helpers.php',
  ],
  theme: {
    extend: {
      fontFamily: {
        sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
      },
    },
  },
  plugins: [require('@tailwindcss/forms')],
};
