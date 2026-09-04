import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    darkMode: 'class',

    theme: {
        extend: {
            fontFamily: {
                sans: ['Outfit', 'Figtree', ...defaultTheme.fontFamily.sans],
                note: ['"Source Serif 4"', 'Georgia', 'serif'],
            },
            colors: {
                paper: {
                    50: '#fbf7ef',
                    100: '#f4ead6',
                    900: '#1c1917',
                },
                ink: {
                    700: '#292524',
                    900: '#1c1917',
                },
            },
            boxShadow: {
                sticky: '0 10px 24px rgba(28, 25, 23, 0.12)',
            },
        },
    },

    plugins: [forms],
};
