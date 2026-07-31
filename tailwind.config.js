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
                sans: ['"Outfit"', ...defaultTheme.fontFamily.sans],
                display: ['"Outfit"', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                theme: {
                    primary: 'var(--color-primary)',
                    'primary-hover': 'var(--color-primary-hover)',
                    'primary-soft': 'var(--color-primary-soft)',
                    bg: 'var(--color-bg)',
                    surface: 'var(--color-surface)',
                    ink: 'var(--color-ink)',
                    'ink-soft': 'var(--color-ink-soft)',
                    'ink-muted': 'var(--color-ink-muted)',
                    border: 'var(--color-border)',
                    success: 'var(--color-success)',
                    warning: 'var(--color-warning)',
                    danger: 'var(--color-danger)',
                    info: 'var(--color-info)',
                    brand: 'var(--color-brand-mark)',
                },
            },
            boxShadow: {
                card: 'var(--shadow-card)',
            },
            borderRadius: {
                theme: 'var(--radius-md)',
            },
        },
    },

    plugins: [forms],
};
