import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', 'Figtree', ...defaultTheme.fontFamily.sans],
            },
            fontSize: {
                'xs': ['0.875rem', { lineHeight: '1.25rem' }],   // 14px (was 12px)
                'sm': ['0.95rem', { lineHeight: '1.45rem' }],    // ~15.2px (was 14px)
                'base': ['1.05rem', { lineHeight: '1.65rem' }],  // ~16.8px (was 16px)
                'lg': ['1.2rem', { lineHeight: '1.75rem' }],     // ~19.2px (was 18px)
                'xl': ['1.35rem', { lineHeight: '1.85rem' }],    // ~21.6px (was 20px)
                '2xl': ['1.65rem', { lineHeight: '2.15rem' }],   // ~26.4px (was 24px)
                '3xl': ['2rem', { lineHeight: '2.45rem' }],      // 32px
            },
        },
    },

    plugins: [forms],
};
