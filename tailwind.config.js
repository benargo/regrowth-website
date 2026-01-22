import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.jsx',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                brown: {
                    DEFAULT: '#231a13',
                    100: '#f5f0eb',
                    200: '#e6ddd3',
                    300: '#d1c3b4',
                    400: '#b8a48f',
                    500: '#9a826a',
                    600: '#7a6249',
                    700: '#5c472f',
                    800: '#4a3d32',
                    900: '#372d22',
                },
                primary: '#f8b700',
            },
        },
    },

    plugins: [forms],
};
