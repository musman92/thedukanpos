import { Moon, Sun } from 'lucide-react';
import { useEffect, useState } from 'react';
import { THEME_DARK, initTheme, toggleTheme } from '@/theme';

export default function ThemeToggle({ className = '' }) {
    const [theme, setTheme] = useState(() =>
        typeof document !== 'undefined'
            ? document.documentElement.getAttribute('data-theme') || 'light'
            : 'light',
    );

    useEffect(() => {
        setTheme(initTheme());
    }, []);

    const isDark = theme === THEME_DARK;

    return (
        <button
            type="button"
            onClick={() => setTheme(toggleTheme(theme))}
            className={`dp-icon-btn ${className}`}
            title={isDark ? 'Switch to light theme' : 'Switch to dark theme'}
            aria-label={isDark ? 'Switch to light theme' : 'Switch to dark theme'}
        >
            {isDark ? (
                <Sun className="h-[18px] w-[18px]" strokeWidth={1.75} />
            ) : (
                <Moon className="h-[18px] w-[18px]" strokeWidth={1.75} />
            )}
            <span className="sr-only">{isDark ? 'Light' : 'Dark'}</span>
        </button>
    );
}
