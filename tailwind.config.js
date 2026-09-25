import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/**
 * Theme colors live in CSS variables, so Tailwind cannot split them into
 * channels for `/alpha` modifiers — without this wrapper `theme-primary/20`
 * silently compiles to nothing. color-mix keeps one token per color.
 */
const themeColor =
    (variable) =>
    ({ opacityValue } = {}) => {
        if (opacityValue === undefined) return `var(${variable})`;

        const alpha = Number(opacityValue);
        const percent = Number.isFinite(alpha)
            ? `${alpha * 100}%`
            : `calc(${opacityValue} * 100%)`;

        return `color-mix(in srgb, var(${variable}) ${percent}, transparent)`;
    };

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.jsx',
        './addons/*/resources/js/**/*.jsx',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['"Outfit"', ...defaultTheme.fontFamily.sans],
                display: ['"Outfit"', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                theme: {
                    primary: themeColor('--color-primary'),
                    'primary-hover': themeColor('--color-primary-hover'),
                    'primary-soft': themeColor('--color-primary-soft'),
                    bg: themeColor('--color-bg'),
                    surface: themeColor('--color-surface'),
                    ink: themeColor('--color-ink'),
                    'ink-soft': themeColor('--color-ink-soft'),
                    'ink-muted': themeColor('--color-ink-muted'),
                    border: themeColor('--color-border'),
                    success: themeColor('--color-success'),
                    warning: themeColor('--color-warning'),
                    danger: themeColor('--color-danger'),
                    info: themeColor('--color-info'),
                    brand: themeColor('--color-brand-mark'),
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
