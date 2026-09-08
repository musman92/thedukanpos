import { filterOptions, pickExactOption } from '@/lib/catalogSearch';
import { Check, ChevronDown, Search } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';

/**
 * @param {{
 *   options: Array<{ value: string|number, label: string, meta?: string, keywords?: string }>,
 *   value: string|number|null,
 *   onChange: (value: string|number|null) => void,
 *   placeholder?: string,
 *   searchable?: boolean,
 *   asSearch?: boolean,
 *   autoFocus?: boolean,
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
    asSearch = false,
    autoFocus = false,
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

    const filtered = useMemo(() => filterOptions(options, query), [options, query]);

    const commitValue = (nextValue) => {
        onChange(nextValue);
        setOpen(false);
        setQuery('');
    };

    const commitFromQuery = (rawQuery) => {
        const match = pickExactOption(options, rawQuery);
        if (match) {
            commitValue(match.value);
            return true;
        }
        return false;
    };

    const onSearchKeyDown = (e) => {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        e.stopPropagation();
        commitFromQuery(e.currentTarget.value);
    };

    useEffect(() => {
        const onDoc = (e) => {
            if (rootRef.current && !rootRef.current.contains(e.target)) {
                setOpen(false);
                if (!asSearch) setQuery('');
            }
        };
        document.addEventListener('mousedown', onDoc);
        return () => document.removeEventListener('mousedown', onDoc);
    }, [asSearch]);

    useEffect(() => {
        if (open && searchable && !asSearch) {
            setTimeout(() => inputRef.current?.focus(), 0);
        }
    }, [open, searchable, asSearch]);

    useEffect(() => {
        if (asSearch && autoFocus) {
            setTimeout(() => inputRef.current?.focus(), 0);
        }
    }, [asSearch, autoFocus]);

    const triggerSize =
        size === 'sm' ? 'px-2.5 py-1.5 text-xs' : 'px-3.5 py-2.5 text-sm';

    const dropdown = open && (
        <div className="dp-card absolute z-50 mt-1.5 w-full overflow-hidden">
            {searchable && !asSearch && (
                <div className="flex items-center gap-2 border-b border-theme-border px-3 py-2">
                    <Search className="h-4 w-4 text-theme-ink-muted" />
                    <input
                        ref={inputRef}
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        onKeyDown={onSearchKeyDown}
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
                                onClick={() => commitValue(opt.value)}
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
    );

    if (asSearch) {
        return (
            <div className={`relative ${className}`} ref={rootRef}>
                <div
                    className={`flex w-full items-center gap-2 rounded-lg border bg-theme-surface px-3 outline-none transition focus-within:border-theme-primary focus-within:ring-2 focus-within:ring-theme-primary/20 ${triggerSize} ${
                        error ? 'border-theme-danger' : 'border-theme-border'
                    } ${disabled ? 'cursor-not-allowed opacity-60' : ''}`}
                >
                    <Search className="h-4 w-4 shrink-0 text-theme-ink-muted" />
                    <input
                        ref={inputRef}
                        value={query}
                        disabled={disabled}
                        autoFocus={autoFocus}
                        onChange={(e) => {
                            setQuery(e.target.value);
                            setOpen(true);
                        }}
                        onFocus={() => !disabled && setOpen(true)}
                        onKeyDown={onSearchKeyDown}
                        placeholder={placeholder}
                        className="w-full border-0 bg-transparent p-0 text-sm text-theme-ink outline-none ring-0 placeholder:text-theme-ink-muted focus:ring-0 disabled:cursor-not-allowed"
                    />
                </div>
                {dropdown}
            </div>
        );
    }

    return (
        <div className={`relative ${className}`} ref={rootRef}>
            <button
                type="button"
                disabled={disabled}
                onClick={() => !disabled && setOpen((v) => !v)}
                className={`flex w-full items-center gap-1.5 rounded-lg border bg-theme-surface text-left outline-none transition focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20 disabled:cursor-not-allowed disabled:opacity-60 ${triggerSize} ${
                    error ? 'border-theme-danger' : 'border-theme-border'
                }`}
            >
                <span
                    className={`min-w-0 flex-1 truncate ${selected ? 'text-theme-ink' : 'text-theme-ink-muted'}`}
                >
                    {selected ? selected.label : placeholder}
                </span>
                <ChevronDown className="h-3.5 w-3.5 shrink-0 text-theme-ink-muted" />
            </button>
            {dropdown}
        </div>
    );
}
