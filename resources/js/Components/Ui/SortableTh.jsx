import { ArrowDown, ArrowUp, ArrowUpDown } from 'lucide-react';

/**
 * Clickable table header for server-side sorting.
 */
export default function SortableTh({
    label,
    column,
    sort,
    direction = 'asc',
    onSort,
    className = '',
    align = 'left',
}) {
    const active = sort === column;
    const ariaSort = active ? (direction === 'desc' ? 'descending' : 'ascending') : 'none';

    return (
        <th
            className={`px-3 py-3 font-semibold ${align === 'right' ? 'text-right' : 'text-left'} ${className}`}
            aria-sort={ariaSort}
        >
            <button
                type="button"
                onClick={() => onSort(column)}
                className={`inline-flex items-center gap-1 uppercase tracking-wide transition hover:text-theme-ink ${
                    active ? 'text-theme-ink' : 'text-theme-ink-muted'
                }`}
            >
                {label}
                {active ? (
                    direction === 'desc' ? (
                        <ArrowDown className="h-3.5 w-3.5" strokeWidth={2.25} />
                    ) : (
                        <ArrowUp className="h-3.5 w-3.5" strokeWidth={2.25} />
                    )
                ) : (
                    <ArrowUpDown className="h-3.5 w-3.5 opacity-50" strokeWidth={2} />
                )}
            </button>
        </th>
    );
}
