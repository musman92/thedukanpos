import { Check, ChevronDown, Search } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';

/**
 * @param {{
 *   options: Array<{ value: string|number, label: string, meta?: string }>,
 *   value: string|number|null,
 *   onChange: (value: string|number|null) => void,
 *   placeholder?: string,
 *   searchable?: boolean,
 *   disabled?: boolean,
 *   error?: boolean,
 *   className?: string,
 * }} props
 */
export default function SearchableSelect({
    options = [],
    value,
    onChange,
    placeholder = 'Select…',
    searchable = true,
    disabled = false,
    error = false,
    size = 'md',
    className = '',
}) {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const rootRef = useRef(null);
    const inputRef = useRef(null);

    const selected = useMemo(
        () => options.find((o) => String(o.value) === String(value)) || null,
        [options, value],
    );

    const filtered = useMemo(() => {
        const q = query.trim().toLowerCase();
        if (!q) return options;
        return options.filter(
            (o) =>
                o.label.toLowerCase().includes(q) ||
                String(o.meta || '')
                    .toLowerCase()
                    .includes(q),
        );
    }, [options, query]);

    useEffect(() => {
        const onDoc = (e) => {
            if (rootRef.current && !rootRef.current.contains(e.target)) {
                setOpen(false);
                setQuery('');
            }
        };
        document.addEventListener('mousedown', onDoc);
        return () => document.removeEventListener('mousedown', onDoc);
    }, []);

    useEffect(() => {
        if (open && searchable) {
            setTimeout(() => inputRef.current?.focus(), 0);
        }
    }, [open, searchable]);

    const triggerSize =
        size === 'sm' ? 'px-2.5 py-1.5 text-xs' : 'px-3.5 py-2.5 text-sm';

    return (
        <div className={`relative ${className}`} ref={rootRef}>
            <button
                type="button"
                disabled={disabled}
                onClick={() => !disabled && setOpen((v) => !v)}
                className={`flex w-full items-center gap-1.5 rounded-lg border bg-theme-surface text-left outline-none transition focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20 disabled:cursor-not-allowed disabled:opacity-60 ${triggerSize} ${
                    error
                        ? 'border-theme-danger'
                        : 'border-theme-border'
                }`}
            >
                <span className={`min-w-0 flex-1 truncate ${selected ? 'text-theme-ink' : 'text-theme-ink-muted'}`}>
                    {selected ? selected.label : placeholder}
                </span>
                <ChevronDown className="h-3.5 w-3.5 shrink-0 text-theme-ink-muted" />
            </button>

            {open && (
                <div className="dp-card absolute z-50 mt-1.5 w-full overflow-hidden">
                    {searchable && (
                        <div className="flex items-center gap-2 border-b border-theme-border px-3 py-2">
                            <Search className="h-4 w-4 text-theme-ink-muted" />
                            <input
                                ref={inputRef}
                                value={query}
                                onChange={(e) => setQuery(e.target.value)}
                                placeholder="Search…"
                                className="w-full border-0 bg-transparent p-0 text-sm text-theme-ink outline-none ring-0 focus:ring-0"
                            />
                        </div>
                    )}
                    <ul className="max-h-56 overflow-y-auto py-1">
                        {filtered.length === 0 && (
                            <li className="px-3 py-2 text-sm text-theme-ink-muted">No results</li>
                        )}
                        {filtered.map((opt) => {
                            const active = String(opt.value) === String(value);
                            return (
                                <li key={String(opt.value)}>
                                    <button
                                        type="button"
                                        onClick={() => {
                                            onChange(opt.value);
                                            setOpen(false);
                                            setQuery('');
                                        }}
                                        className={`flex w-full items-center gap-2 px-3 py-2 text-left text-sm ${
                                            active
                                                ? 'bg-theme-primary-soft font-medium text-theme-primary'
                                                : 'text-theme-ink hover:bg-theme-bg'
                                        }`}
                                    >
                                        <span className="min-w-0 flex-1">
                                            <span className="block truncate">{opt.label}</span>
                                            {opt.meta && (
                                                <span className="block truncate text-[11px] uppercase text-theme-ink-muted">
                                                    {opt.meta}
                                                </span>
                                            )}
                                        </span>
                                        {active && <Check className="h-3.5 w-3.5 shrink-0" />}
                                    </button>
                                </li>
                            );
                        })}
                    </ul>
                </div>
            )}
        </div>
    );
}
