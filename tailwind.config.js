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
                /*-- Discord branding and role colors --*/
                discord: {
                    DEFAULT: '#5865F2',
                    officer: '#aa3b3b',
                    raider: '#e67e22',
                    member: '#1f8b4c',
                    lootcouncillor: '#a335ee',
                    guest: '#9b59b6',
                },
                /*-- WoW item quality colors --*/
                quality: {
                    DEFAULT: '#ffffff',
                    poor: '#9d9d9d',
                    common: '#ffffff',
                    uncommon: '#1eff00',
                    rare: '#0070dd',
                    epic: '#a335ee',
                    legendary: '#ff8000',
                    artifact: '#e6cc80',
                    heirloom: '#00ccff',
                },
                'guild-rank': {
                    DEFAULT: '#1f8b4c',
                    'guild-master': '#aa3b3b',
                    'officer': '#aa3b3b',
                    'raider': '#e67e22',
                    'trial-raider': '#e67e22',
                    'warden': '#1f8b4c',
                    'champion': '#1f8b4c',
                    'veteran': '#1f8b4c',
                    'member': '#1f8b4c',
                    'initiate': '#1f8b4c',
                    'inactive': '#1f8b4c',
                },
                wowhead: {
                    DEFAULT: '#a71a19',
                    'links': '#ffd100',
                },
                primary: '#f8b700',
            },
        },
    },

    plugins: [forms],

    safelist: [
        { pattern: /(bg|text|border)-discord-(officer|raider|member|lootcouncillor|guest)/, },
        { pattern: /(bg|text|border)-quality-(poor|common|uncommon|rare|epic|legendary|artifact|heirloom)/, },
        { pattern: /(bg|text|border)-guild-rank-(guild-master|officer|raider|trial-raider|warden|champion|veteran|member|initiate|inactive)/, },
    ],
};
