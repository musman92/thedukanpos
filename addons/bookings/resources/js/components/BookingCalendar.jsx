import { ChevronLeft, ChevronRight, Plus } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import Button from '@/Components/Ui/Button';

const WEEKDAYS = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];

const STATUS_CLASS = {
    pending: 'bg-theme-warning/15 text-theme-warning',
    confirmed: 'bg-theme-primary-soft text-theme-primary',
    done: 'bg-theme-success/15 text-theme-success',
    cancelled: 'bg-theme-bg text-theme-ink-muted line-through',
};

function shiftMonth(month, delta) {
    const [year, m] = month.split('-').map(Number);
    const date = new Date(Date.UTC(year, m - 1 + delta, 1));

    return `${date.getUTCFullYear()}-${String(date.getUTCMonth() + 1).padStart(2, '0')}`;
}

function todayKey() {
    return new Date().toLocaleDateString('en-CA');
}

function monthLabel(month) {
    const [year, m] = month.split('-').map(Number);

    return new Intl.DateTimeFormat(undefined, {
        month: 'long',
        year: 'numeric',
        timeZone: 'UTC',
    }).format(new Date(Date.UTC(year, m - 1, 1)));
}

function weekdayLabels(weekStartsOn) {
    const start = Math.max(0, WEEKDAYS.indexOf(weekStartsOn));
    const ordered = [...WEEKDAYS.slice(start), ...WEEKDAYS.slice(0, start)];

    return ordered.map((day) =>
        new Intl.DateTimeFormat(undefined, { weekday: 'short' }).format(
            new Date(Date.UTC(2024, 0, 7 + WEEKDAYS.indexOf(day))),
        ),
    );
}

function timeLabel(value) {
    if (!value) return '';

    return new Date(value).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

function dayHeading(key) {
    return new Intl.DateTimeFormat(undefined, {
        weekday: 'long',
        month: 'long',
        day: 'numeric',
        timeZone: 'UTC',
    }).format(new Date(`${key}T12:00:00Z`));
}

export default function BookingCalendar({
    calendar,
    month,
    onMonthChange,
    onSelect,
    onCreate,
}) {
    const today = todayKey();
    const days = calendar?.days || [];
    const byDay = useMemo(() => {
        const grouped = {};
        (calendar?.items || []).forEach((booking) => {
            if (!booking.date) return;
            (grouped[booking.date] ||= []).push(booking);
        });

        return grouped;
    }, [calendar?.items]);

    const [selected, setSelected] = useState(() =>
        days.includes(today) ? today : days.find((day) => day.startsWith(month)) || days[0] || '',
    );

    useEffect(() => {
        setSelected((current) => {
            if (current && days.includes(current)) return current;
            if (days.includes(today)) return today;

            return days.find((day) => day.startsWith(month)) || days[0] || '';
        });
        // Re-anchor only when the visible month changes.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [month]);

    const selectedItems = byDay[selected] || [];
    const labels = weekdayLabels(calendar?.week_starts_on || 'monday');

    return (
        <div className="space-y-4">
            <div className="flex flex-wrap items-center gap-2">
                <div className="flex items-center gap-1">
                    <button
                        type="button"
                        className="dp-icon-btn"
                        aria-label="Previous month"
                        onClick={() => onMonthChange(shiftMonth(month, -1))}
                    >
                        <ChevronLeft className="h-4 w-4" />
                    </button>
                    <h2 className="min-w-[10rem] text-center text-base font-semibold capitalize">
                        {monthLabel(month)}
                    </h2>
                    <button
                        type="button"
                        className="dp-icon-btn"
                        aria-label="Next month"
                        onClick={() => onMonthChange(shiftMonth(month, 1))}
                    >
                        <ChevronRight className="h-4 w-4" />
                    </button>
                </div>
                <Button
                    size="sm"
                    variant="secondary"
                    onClick={() => onMonthChange(today.slice(0, 7))}
                >
                    Today
                </Button>
            </div>

            <div className="overflow-x-auto">
                <div className="grid min-w-[36rem] grid-cols-7 border-b border-theme-border text-center text-[11px] font-semibold uppercase tracking-wide text-theme-ink-muted">
                    {labels.map((label) => (
                        <div key={label} className="px-1 py-2">
                            {label}
                        </div>
                    ))}
                </div>
                <div className="grid min-w-[36rem] grid-cols-7 border-l border-theme-border">
                    {days.map((day) => {
                        const inMonth = day.startsWith(month);
                        const items = byDay[day] || [];
                        const isToday = day === today;
                        const isSelected = day === selected;

                        return (
                            <div
                                key={day}
                                className={`min-h-[5.5rem] border-b border-r border-theme-border p-1.5 sm:min-h-[7.5rem] ${
                                    isSelected
                                        ? 'bg-theme-primary-soft'
                                        : inMonth
                                          ? 'bg-theme-surface'
                                          : 'bg-theme-bg/60 text-theme-ink-muted'
                                }`}
                            >
                                <button
                                    type="button"
                                    onClick={() => setSelected(day)}
                                    className="flex w-full items-center justify-between gap-1 rounded-md text-left"
                                >
                                    <span
                                        className={`inline-flex h-7 min-w-7 items-center justify-center rounded-full text-xs font-semibold ${
                                            isToday
                                                ? 'bg-theme-primary text-[var(--color-on-primary)]'
                                                : 'hover:bg-theme-bg'
                                        }`}
                                    >
                                        {Number(day.slice(-2))}
                                    </span>
                                    {items.length > 0 && (
                                        <span className="rounded-full bg-theme-bg px-1.5 text-[10px] font-semibold text-theme-ink-soft sm:hidden">
                                            {items.length}
                                        </span>
                                    )}
                                </button>
                                <div className="mt-1 hidden space-y-0.5 sm:block">
                                    {items.slice(0, 3).map((booking) => (
                                        <button
                                            key={booking.id}
                                            type="button"
                                            onClick={() => onSelect(booking)}
                                            className={`block w-full truncate rounded px-1 py-0.5 text-left text-[11px] font-medium ${
                                                STATUS_CLASS[booking.status] || STATUS_CLASS.pending
                                            }`}
                                        >
                                            {timeLabel(booking.scheduled_at)} {booking.service_name}
                                        </button>
                                    ))}
                                    {items.length > 3 && (
                                        <button
                                            type="button"
                                            onClick={() => setSelected(day)}
                                            className="block w-full px-1 text-left text-[11px] text-theme-ink-muted"
                                        >
                                            +{items.length - 3} more
                                        </button>
                                    )}
                                </div>
                            </div>
                        );
                    })}
                </div>
            </div>

            <section className="rounded-lg border border-theme-border p-4">
                <div className="flex items-start justify-between gap-3">
                    <div>
                        <h3 className="font-semibold">{selected ? dayHeading(selected) : 'Select a day'}</h3>
                        <p className="text-xs text-theme-ink-muted">
                            {selectedItems.length === 1 ? '1 booking' : `${selectedItems.length} bookings`}
                        </p>
                    </div>
                    {selected && (
                        <Button
                            size="sm"
                            variant="secondary"
                            onClick={() => onCreate(`${selected}T09:00`)}
                        >
                            <Plus className="h-4 w-4" />
                            Add
                        </Button>
                    )}
                </div>
                {selectedItems.length === 0 ? (
                    <p className="mt-3 text-sm text-theme-ink-muted">No bookings on this day.</p>
                ) : (
                    <ul className="mt-3 space-y-2">
                        {selectedItems.map((booking) => (
                            <li key={booking.id}>
                                <button
                                    type="button"
                                    onClick={() => onSelect(booking)}
                                    className="flex w-full items-start justify-between gap-3 rounded-lg border border-theme-border bg-theme-bg px-3 py-2.5 text-left"
                                >
                                    <span>
                                        <span className="block text-sm font-medium">
                                            {timeLabel(booking.scheduled_at)} · {booking.service_name}
                                        </span>
                                        <span className="block text-xs text-theme-ink-muted">
                                            {booking.customer_name} · {booking.number}
                                        </span>
                                    </span>
                                    <span
                                        className={`shrink-0 rounded-full px-2 py-0.5 text-[11px] font-semibold capitalize ${
                                            STATUS_CLASS[booking.status] || STATUS_CLASS.pending
                                        }`}
                                    >
                                        {booking.status}
                                    </span>
                                </button>
                            </li>
                        ))}
                    </ul>
                )}
            </section>
        </div>
    );
}
