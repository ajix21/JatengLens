/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './resources/**/*.vue',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['ui-sans-serif', 'system-ui', 'sans-serif', '"Apple Color Emoji"', '"Segoe UI Emoji"', '"Segoe UI Symbol"', '"Noto Color Emoji"'],
            },
            colors: {
                navy: {
                    950: '#0a0f1e',
                    900: '#0d1424',
                    800: '#111827',
                    700: '#1a2540',
                    600: '#1e2d4d',
                    500: '#243660',
                },
                brand: {
                    DEFAULT: '#4F46E5',
                    dark: '#3730A3',
                    light: '#818CF8',
                    glow: 'rgba(79,70,229,0.25)',
                },
            },
            boxShadow: {
                'brand-glow': '0 0 20px rgba(79,70,229,0.35)',
                'xs': '0 1px 2px 0 rgb(0 0 0 / 0.05)',
            },
        },
    },
    plugins: [],
};
