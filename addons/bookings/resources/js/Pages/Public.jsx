import Button from '@/Components/Ui/Button';
import Input, { Field, TextArea } from '@/Components/Ui/Input';
import Modal from '@/Components/Modal';
import ThemeToggle from '@/Components/ThemeToggle';
import { formatMoney } from '@/lib/money';
import { Head, useForm, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    CalendarDays,
    Check,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    Clock,
    MapPin,
    Search,
    X,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';

const WEEK = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
const STEPS = ['Service', 'Time', 'Details'];
const DAYS_AHEAD = 21;
/* Same-day slots need a little breathing room before the visitor arrives. */
const LEAD_MINUTES = 15;

const ERROR_STEP = {
    product_id: 0,
    scheduled_at: 1,
    booking: 1,
    customer_name: 2,
    customer_phone: 2,
    address: 2,
    notes: 2,
};

const empty = {
    product_id: '',
    customer_name: '',
    customer_phone: '',
    address: '',
    notes: '',
    scheduled_at: '',
    website: '',
};

/** Business-local calendar date as YYYY-MM-DD. */
function dateKeyIn(timeZone, date = new Date()) {
    return new Intl.DateTimeFormat('en-CA', {
        timeZone,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).format(date);
}

/** Minutes past business-local midnight. */
function minutesIn(timeZone, date = new Date()) {
    const parts = new Intl.DateTimeFormat('en-GB', {
        timeZone,
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    }).formatToParts(date);
    const read = (type) => Number(parts.find((part) => part.type === type)?.value || 0);

    return (read('hour') % 24) * 60 + read('minute');
}

function minutesFromTime(value) {
    const [hour, minute] = String(value ?? '').split(':').map(Number);

    return Number.isFinite(hour) ? hour * 60 + (minute || 0) : null;
}

function pad(value) {
    return String(value).padStart(2, '0');
}

function slotLabel(minutes) {
    return new Intl.DateTimeFormat(undefined, {
        hour: 'numeric',
        minute: '2-digit',
        timeZone: 'UTC',
    }).format(new Date(Date.UTC(2000, 0, 1, Math.floor(minutes / 60), minutes % 60)));
}

/**
 * Upcoming calendar days anchored at noon UTC so day arithmetic never trips
 * over a DST shift, then labelled from the date itself.
 */
function buildDays(timeZone) {
    const [year, month, day] = dateKeyIn(timeZone).split('-').map(Number);
    const anchor = Date.UTC(year, month - 1, day, 12);
    const label = (options, date) =>
        new Intl.DateTimeFormat(undefined, { ...options, timeZone: 'UTC' }).format(date);

    return Array.from({ length: DAYS_AHEAD }, (_, index) => {
        const date = new Date(anchor + index * 86400000);

        return {
            index,
            key: date.toISOString().slice(0, 10),
            weekday: new Intl.DateTimeFormat('en-US', { weekday: 'long', timeZone: 'UTC' })
                .format(date)
                .toLowerCase(),
            weekdayLabel: label({ weekday: 'short' }, date),
            dayLabel: label({ day: 'numeric' }, date),
            monthLabel: label({ month: 'short' }, date),
        };
    });
}

function weekRangeLabel(week) {
    if (!week?.length) return '';
    const first = week[0];
    const last = week[week.length - 1];
    if (first.monthLabel === last.monthLabel) {
        return `${first.monthLabel} ${first.dayLabel} – ${last.dayLabel}`;
    }

    return `${first.monthLabel} ${first.dayLabel} – ${last.monthLabel} ${last.dayLabel}`;
}

function buildSlots(day, hours, duration, todayKey, nowMinutes) {
    const open = minutesFromTime(hours?.open);
    const close = minutesFromTime(hours?.close);
    if (!day || !hours?.enabled || open === null || close === null || close <= open) {
        return [];
    }

    const earliest = day.key === todayKey ? nowMinutes + LEAD_MINUTES : 0;
    const step = Math.max(15, duration);
    const slots = [];
    for (let start = open; start + duration <= close; start += step) {
        if (start >= earliest) slots.push(start);
    }

    return slots;
}

function Monogram({ name, className = '' }) {
    return (
        <span
            className={`flex shrink-0 items-center justify-center rounded-xl bg-theme-primary-soft font-bold text-theme-primary ${className}`}
        >
            {(name || '?').trim().charAt(0).toUpperCase()}
        </span>
    );
}

function Chip({ children }) {
    return (
        <span className="inline-flex items-center gap-1 rounded-full bg-theme-bg px-2 py-0.5 text-[11px] font-medium text-theme-ink-soft">
            {children}
        </span>
    );
}

function RequiredMark() {
    return <span className="text-theme-ink-muted"> *</span>;
}

function errorText(errors, key) {
    const value = errors?.[key];
    if (Array.isArray(value)) return value.filter(Boolean).join(' ');

    return value ? String(value) : '';
}

function phoneDigits(value) {
    const hasPlus = value.trim().startsWith('+');
    const digits = value.replace(/\D/g, '');

    return hasPlus ? `+${digits}` : digits;
}

function SummaryRow({ label, value, onChange }) {
    return (
        <div className="flex items-center gap-3 border-b border-theme-border px-4 py-3 text-sm sm:px-5">
            <div className="min-w-0 flex-1">
                <p className="text-[11px] font-semibold uppercase tracking-wide text-theme-ink-muted">
                    {label}
                </p>
                <p className="truncate font-medium text-theme-ink">{value}</p>
            </div>
            <button
                type="button"
                onClick={onChange}
                className="shrink-0 text-sm font-semibold text-theme-primary"
            >
                Change
            </button>
        </div>
    );
}

export default function Public({ services, business_hours: hours, tenant_code: tenantCode }) {
    const { tenant, company, flash } = usePage().props;
    const shopName = company?.shop_name || tenant?.name || tenantCode;
    const timeZone = company?.timezone || undefined;

    const [step, setStep] = useState(0);
    const [dayKey, setDayKey] = useState('');
    const [weekIndex, setWeekIndex] = useState(0);
    const [slot, setSlot] = useState(null);
    const [dismissed, setDismissed] = useState(false);
    const [revealErrors, setRevealErrors] = useState(false);
    const [serviceQuery, setServiceQuery] = useState('');
    /* A single-service business has nothing to choose, so skip that tap. */
    const blank = () => ({
        ...empty,
        product_id: services.length === 1 ? String(services[0].id) : '',
    });
    const form = useForm(blank());

    const todayKey = useMemo(() => dateKeyIn(timeZone), [timeZone]);
    const nowMinutes = useMemo(() => minutesIn(timeZone), [timeZone]);
    const today = useMemo(
        () =>
            new Intl.DateTimeFormat('en-US', { weekday: 'long', timeZone })
                .format(new Date())
                .toLowerCase(),
        [timeZone],
    );

    const service = services.find((item) => String(item.id) === String(form.data.product_id));
    const duration = service?.duration_minutes || 60;
    const serviceSearch = serviceQuery.trim().toLowerCase();
    const visibleServices = useMemo(() => {
        if (!serviceSearch) return services;

        return services.filter((item) =>
            `${item.name} ${item.description || ''}`.toLowerCase().includes(serviceSearch),
        );
    }, [services, serviceSearch]);

    const days = useMemo(
        () =>
            buildDays(timeZone).map((day) => ({
                ...day,
                slots: buildSlots(day, hours?.[day.weekday], duration, todayKey, nowMinutes),
            })),
        [timeZone, hours, duration, todayKey, nowMinutes],
    );
    const openDays = days.filter((day) => day.slots.length > 0);
    const weeks = useMemo(() => {
        const chunks = [];
        for (let index = 0; index < days.length; index += 7) {
            chunks.push(days.slice(index, index + 7));
        }

        return chunks;
    }, [days]);
    const safeWeek = Math.min(weekIndex, Math.max(weeks.length - 1, 0));
    const visibleWeek = weeks[safeWeek] || [];
    const selectedDay = days.find((day) => day.key === dayKey && day.slots.length > 0);

    /* Land on the first bookable day, on the week that contains it. */
    useEffect(() => {
        if (step !== 1) return;
        const key = dayKey || openDays[0]?.key || '';
        const index = days.findIndex((day) => day.key === key);
        if (index >= 0) setWeekIndex(Math.floor(index / 7));
        if (!days.some((day) => day.key === dayKey && day.slots.length > 0)) {
            setDayKey(openDays[0]?.key || '');
            setSlot(null);
        }
        // Only when the visitor arrives on the time step.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [step]);

    const showWeek = (index) => {
        const week = weeks[index] || [];
        const firstOpen = week.find((day) => day.slots.length > 0);
        setWeekIndex(index);
        setDayKey(firstOpen?.key || '');
        setSlot(null);
    };

    const pickService = (id) => {
        form.setData('product_id', String(id));
        form.clearErrors();
        setDayKey('');
        setSlot(null);
    };

    const pickSlot = (day, minutes) => {
        setSlot(minutes);
        form.setData(
            'scheduled_at',
            `${day.key}T${pad(Math.floor(minutes / 60))}:${pad(minutes % 60)}`,
        );
        form.clearErrors('scheduled_at');
    };

    const canContinue = step === 0 ? !!service : slot !== null;
    const scheduleLabel = selectedDay && slot !== null
        ? `${selectedDay.weekdayLabel} ${selectedDay.dayLabel} ${selectedDay.monthLabel} · ${slotLabel(slot)}`
        : '';

    const fieldError = (key) => (revealErrors ? errorText(form.errors, key) : '');
    const keepErrors = useRef(false);

    /* Arriving on a step is not a submit. Drop any errors left by an earlier
       attempt, unless this step change was caused by a failed request. */
    useEffect(() => {
        if (keepErrors.current) {
            keepErrors.current = false;

            return;
        }
        setRevealErrors(false);
        form.clearErrors();
        // form.clearErrors is stable; only step changes should reset errors.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [step]);

    const moveTo = (next) => {
        setRevealErrors(false);
        form.clearErrors();
        setStep(next);
    };

    const submit = () => {
        if (step !== 2 || form.processing) return;
        setRevealErrors(false);
        form.post(route('bookings.public.store', tenantCode), {
            /* Keep the visitor next to the field that failed; a success lands
               on the confirmation card at the top instead. */
            preserveScroll: (page) => Object.keys(page.props.errors || {}).length > 0,
            onSuccess: () => {
                setRevealErrors(false);
                setStep(0);
                setSlot(null);
                setDayKey('');
                form.setData(blank());
            },
            /* Capacity and hours are settled server side, so failures can
               belong to an earlier step than the one being submitted. */
            onError: (errors) => {
                setRevealErrors(true);
                const steps = Object.keys(errors)
                    .map((key) => ERROR_STEP[key])
                    .filter((value) => value !== undefined);
                if (steps.includes(step)) return;
                const target = [...steps].sort()[0];
                if (target !== undefined) {
                    keepErrors.current = true;
                    setStep(target);
                }
            },
        });
    };

    if (flash?.status && !dismissed) {
        return (
            <Shell shopName={shopName} hours={hours} today={today}>
                <section className="dp-card p-6 text-center sm:p-8">
                    <CheckCircle2 className="mx-auto h-12 w-12 text-theme-success" strokeWidth={1.5} />
                    <h1 className="mt-4 text-xl font-bold">Request sent</h1>
                    <p className="mx-auto mt-2 max-w-sm text-sm text-theme-ink-soft">{flash.status}</p>
                    <Button
                        variant="secondary"
                        className="mt-6 w-full justify-center sm:w-auto"
                        onClick={() => setDismissed(true)}
                    >
                        Book another time
                    </Button>
                </section>
            </Shell>
        );
    }

    if (services.length === 0) {
        return (
            <Shell shopName={shopName} hours={hours} today={today}>
                <section className="dp-card p-6 text-center sm:p-8">
                    <CalendarDays className="mx-auto h-10 w-10 text-theme-ink-muted" strokeWidth={1.5} />
                    <h1 className="mt-4 text-lg font-semibold">Booking is not open yet</h1>
                    <p className="mt-2 text-sm text-theme-ink-soft">
                        {shopName} has no services available for online booking right now.
                    </p>
                </section>
            </Shell>
        );
    }

    const blocker = fieldError('booking');

    return (
        <Shell shopName={shopName} hours={hours} today={today}>
            <div className="text-center">
                <h1 className="text-2xl font-bold tracking-tight sm:text-3xl">Book an appointment</h1>
                <p className="mt-1.5 text-sm text-theme-ink-soft">
                    Pick a service and a time. {shopName} confirms every request.
                </p>
            </div>

            {/* Only the Request booking button submits. Enter in a field must not. */}
            <form noValidate onSubmit={(event) => event.preventDefault()} className="dp-card">
                <div className="flex gap-2 px-4 pt-4 sm:px-5">
                    {STEPS.map((label, index) => (
                        <div key={label} className="flex flex-1 flex-col gap-1.5">
                            <span
                                className={`h-1 rounded-full transition-colors ${
                                    index <= step ? 'bg-theme-primary' : 'bg-theme-border'
                                }`}
                            />
                            <span
                                className={`text-[11px] font-semibold uppercase tracking-wide ${
                                    index === step ? 'text-theme-primary' : 'text-theme-ink-muted'
                                }`}
                            >
                                {label}
                            </span>
                        </div>
                    ))}
                </div>

                <div className="mt-4 border-t border-theme-border">
                    {step > 0 && service && (
                        <SummaryRow
                            label="Service"
                            value={`${service.name} · ${formatMoney(service.price, company)}`}
                            onChange={() => moveTo(0)}
                        />
                    )}
                    {step > 1 && scheduleLabel && (
                        <SummaryRow label="When" value={scheduleLabel} onChange={() => moveTo(1)} />
                    )}
                </div>

                {blocker && (
                    <p className="mx-4 mt-4 flex items-start gap-2 rounded-lg border border-theme-danger/30 bg-theme-danger/10 px-3 py-2 text-sm text-theme-danger sm:mx-5">
                        <AlertCircle className="mt-0.5 h-4 w-4 shrink-0" strokeWidth={2} />
                        {blocker}
                    </p>
                )}

                <div className="p-4 sm:p-5">
                    {step === 0 && (
                        <div className="space-y-3">
                            <div>
                                    <label htmlFor="service-search" className="sr-only">
                                        Search services
                                    </label>
                                    <div className="relative">
                                        <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-theme-ink-muted" />
                                        <input
                                            id="service-search"
                                            value={serviceQuery}
                                            onChange={(e) => setServiceQuery(e.target.value)}
                                            placeholder="Search services"
                                            className="h-11 w-full rounded-lg border border-theme-border bg-theme-surface py-2 pl-9 pr-9 text-sm text-theme-ink outline-none placeholder:text-theme-ink-muted focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20"
                                        />
                                        {serviceQuery && (
                                            <button
                                                type="button"
                                                aria-label="Clear search"
                                                onClick={() => setServiceQuery('')}
                                                className="absolute right-2 top-1/2 -translate-y-1/2 rounded-md p-1 text-theme-ink-muted hover:bg-theme-bg hover:text-theme-ink"
                                            >
                                                <X className="h-4 w-4" />
                                            </button>
                                        )}
                                    </div>
                                    <p className="mt-1.5 text-xs text-theme-ink-muted">
                                        {serviceSearch
                                            ? `${visibleServices.length} of ${services.length}`
                                            : `${services.length} ${services.length === 1 ? 'service' : 'services'}`}
                                    </p>
                                </div>
                            {visibleServices.length === 0 ? (
                                <p className="rounded-xl border border-theme-border bg-theme-bg px-4 py-6 text-center text-sm text-theme-ink-soft">
                                    No services match “{serviceQuery.trim()}”.
                                </p>
                            ) : (
                                <ul className="max-h-[28rem] space-y-2.5 overflow-y-auto pr-1">
                                    {visibleServices.map((item) => {
                                const active = String(item.id) === String(form.data.product_id);

                                    return (
                                        <li key={item.id}>
                                            <button
                                                type="button"
                                                aria-pressed={active}
                                                onClick={() => pickService(item.id)}
                                                className={`flex w-full items-start gap-3 rounded-xl border p-3 text-left transition sm:p-4 ${
                                                    active
                                                        ? 'border-theme-primary bg-theme-primary-soft'
                                                        : 'border-theme-border bg-theme-surface hover:border-theme-primary/60'
                                                }`}
                                            >
                                                <Monogram name={item.name} className="h-11 w-11 text-base" />
                                                <span className="min-w-0 flex-1">
                                                    <span className="flex items-start justify-between gap-3">
                                                        <span className="font-semibold text-theme-ink">{item.name}</span>
                                                        <span className="whitespace-nowrap text-sm font-semibold text-theme-ink">
                                                            {item.price_mode === 'starting_from' && (
                                                                <span className="text-[11px] font-medium text-theme-ink-muted">
                                                                    from{' '}
                                                                </span>
                                                            )}
                                                            {formatMoney(item.price, company)}
                                                        </span>
                                                    </span>
                                                    <span className="mt-1.5 flex flex-wrap gap-1.5">
                                                        <Chip>
                                                            <Clock className="h-3 w-3" strokeWidth={2} />
                                                            {item.duration_minutes} min
                                                        </Chip>
                                                        {item.requires_address && (
                                                            <Chip>
                                                                <MapPin className="h-3 w-3" strokeWidth={2} />
                                                                At your address
                                                            </Chip>
                                                        )}
                                                    </span>
                                                    {item.description && (
                                                        <span className="mt-1.5 line-clamp-2 text-xs text-theme-ink-soft">
                                                            {item.description}
                                                        </span>
                                                    )}
                                                </span>
                                                {active && (
                                                    <Check
                                                        className="mt-1 h-4 w-4 shrink-0 text-theme-primary"
                                                        strokeWidth={2.5}
                                                    />
                                                )}
                                            </button>
                                        </li>
                                    );
                                })}
                                </ul>
                            )}
                        </div>
                    )}

                    {step === 1 && (
                        <div className="space-y-5">
                            {openDays.length === 0 ? (
                                <p className="rounded-xl border border-theme-border bg-theme-bg px-4 py-6 text-center text-sm text-theme-ink-soft">
                                    No times are open in the next {DAYS_AHEAD} days. Please call us instead.
                                </p>
                            ) : (
                                <>
                                    <div>
                                        <p className="text-sm font-medium text-theme-ink">Choose a day</p>
                                        <div className="mb-2 flex items-center justify-center gap-1">
                                            <button
                                                type="button"
                                                className="inline-flex h-9 w-9 items-center justify-center rounded-lg text-theme-ink-soft hover:bg-theme-bg disabled:cursor-not-allowed disabled:opacity-40"
                                                aria-label="Previous week"
                                                disabled={safeWeek === 0}
                                                onClick={() => showWeek(safeWeek - 1)}
                                            >
                                                <ChevronLeft className="h-4 w-4" />
                                            </button>
                                            <span className="min-w-[8rem] text-center text-sm font-semibold text-theme-ink">
                                                {weekRangeLabel(visibleWeek)}
                                            </span>
                                            <button
                                                type="button"
                                                className="inline-flex h-9 w-9 items-center justify-center rounded-lg text-theme-ink-soft hover:bg-theme-bg disabled:cursor-not-allowed disabled:opacity-40"
                                                aria-label="Next week"
                                                disabled={safeWeek >= weeks.length - 1}
                                                onClick={() => showWeek(safeWeek + 1)}
                                            >
                                                <ChevronRight className="h-4 w-4" />
                                            </button>
                                        </div>
                                        <div className="grid grid-cols-7 gap-1.5">
                                            {visibleWeek.map((day) => {
                                                const active = day.key === dayKey;
                                                const bookable = day.slots.length > 0;

                                                return (
                                                    <button
                                                        key={day.key}
                                                        type="button"
                                                        aria-pressed={active}
                                                        disabled={!bookable}
                                                        onClick={() => {
                                                            setDayKey(day.key);
                                                            setSlot(null);
                                                        }}
                                                        className={`min-w-0 rounded-xl border px-1 py-2 text-center transition ${
                                                            active
                                                                ? 'border-theme-primary bg-theme-primary-soft text-theme-primary'
                                                                : bookable
                                                                  ? 'border-theme-border bg-theme-surface text-theme-ink hover:border-theme-primary/60'
                                                                  : 'cursor-not-allowed border-theme-border bg-theme-bg text-theme-ink-muted opacity-50'
                                                        }`}
                                                    >
                                                        <span className="block truncate text-[10px] font-semibold uppercase sm:text-[11px]">
                                                            {day.index === 0 ? 'Today' : day.weekdayLabel}
                                                        </span>
                                                        <span className="mt-0.5 block text-base font-bold leading-tight sm:text-lg">
                                                            {day.dayLabel}
                                                        </span>
                                                        <span className="block truncate text-[10px] text-theme-ink-muted">
                                                            {day.monthLabel}
                                                        </span>
                                                    </button>
                                                );
                                            })}
                                        </div>
                                    </div>

                                    <div>
                                        <p className="mb-2 text-sm font-medium text-theme-ink">
                                            Choose a time
                                            <span className="ml-1 font-normal text-theme-ink-muted">
                                                ({duration} min)
                                            </span>
                                        </p>
                                        {!selectedDay ? (
                                            <p className="rounded-xl border border-theme-border bg-theme-bg px-4 py-6 text-center text-sm text-theme-ink-soft">
                                                No open times this week. Try the next week.
                                            </p>
                                        ) : (
                                        <div className="grid grid-cols-3 gap-2 sm:grid-cols-4">
                                            {(selectedDay?.slots || []).map((minutes) => {
                                                const active = slot === minutes;

                                                return (
                                                    <button
                                                        key={minutes}
                                                        type="button"
                                                        aria-pressed={active}
                                                        onClick={() => pickSlot(selectedDay, minutes)}
                                                        className={`min-h-11 rounded-lg border text-sm font-semibold transition ${
                                                            active
                                                                ? 'border-theme-primary bg-theme-primary-soft text-theme-primary'
                                                                : 'border-theme-border bg-theme-surface text-theme-ink hover:border-theme-primary/60'
                                                        }`}
                                                    >
                                                        {slotLabel(minutes)}
                                                    </button>
                                                );
                                            })}
                                        </div>
                                        )}
                                        {fieldError('scheduled_at') && (
                                            <p className="mt-2 text-xs text-theme-danger">
                                                {fieldError('scheduled_at')}
                                            </p>
                                        )}
                                        {timeZone && (
                                            <p className="mt-3 text-xs text-theme-ink-muted">
                                                Times shown in {timeZone.replace(/_/g, ' ')}.
                                            </p>
                                        )}
                                    </div>
                                </>
                            )}
                        </div>
                    )}

                    {step === 2 && (
                        <div className="space-y-4">
                            <div className="hidden">
                                <Input
                                    tabIndex="-1"
                                    autoComplete="off"
                                    value={form.data.website}
                                    onChange={(e) => form.setData('website', e.target.value)}
                                />
                            </div>
                            <Field label={<>Your name<RequiredMark /></>} error={fieldError('customer_name')}>
                                <Input
                                    autoComplete="name"
                                    error={!!fieldError('customer_name')}
                                    value={form.data.customer_name}
                                    onChange={(e) => {
                                        form.setData('customer_name', e.target.value);
                                        form.clearErrors('customer_name');
                                    }}
                                />
                            </Field>
                            <Field label={<>Phone<RequiredMark /></>} error={fieldError('customer_phone')}>
                                <Input
                                    type="tel"
                                    inputMode="numeric"
                                    autoComplete="tel"
                                    error={!!fieldError('customer_phone')}
                                    value={form.data.customer_phone}
                                    onChange={(e) => {
                                        form.setData('customer_phone', phoneDigits(e.target.value));
                                        form.clearErrors('customer_phone');
                                    }}
                                />
                            </Field>
                            {service?.requires_address && (
                                <Field label={<>Service address<RequiredMark /></>} error={fieldError('address')}>
                                    <TextArea
                                        rows={2}
                                        autoComplete="street-address"
                                        error={!!fieldError('address')}
                                        value={form.data.address}
                                        onChange={(e) => {
                                            form.setData('address', e.target.value);
                                            form.clearErrors('address');
                                        }}
                                    />
                                </Field>
                            )}
                            <Field
                                label="Anything we should know?"
                                hint="Optional"
                                error={fieldError('notes')}
                            >
                                <TextArea
                                    rows={3}
                                    error={!!fieldError('notes')}
                                    value={form.data.notes}
                                    onChange={(e) => form.setData('notes', e.target.value)}
                                />
                            </Field>
                        </div>
                    )}
                </div>

                <div className="sticky bottom-0 flex items-center gap-3 rounded-b-theme border-t border-theme-border bg-theme-surface/95 px-4 py-3 backdrop-blur sm:px-5">
                    {step > 0 && (
                        <Button type="button" variant="secondary" onClick={() => moveTo(step - 1)}>
                            <ChevronLeft className="h-4 w-4" strokeWidth={2.25} />
                            Back
                        </Button>
                    )}
                    {step === 2 ? (
                        <Button
                            key="request"
                            type="button"
                            onClick={submit}
                            size="lg"
                            disabled={form.processing}
                            className="ml-auto flex-1 sm:flex-none"
                        >
                            {form.processing ? 'Sending…' : 'Request booking'}
                        </Button>
                    ) : (
                        <Button
                            key="continue"
                            type="button"
                            size="lg"
                            disabled={!canContinue}
                            onClick={() => moveTo(step + 1)}
                            className="ml-auto flex-1 sm:flex-none"
                        >
                            Continue
                        </Button>
                    )}
                </div>
            </form>
        </Shell>
    );
}

function Shell({ shopName, hours, today, children }) {
    const [hoursOpen, setHoursOpen] = useState(false);

    return (
        <div className="min-h-screen bg-gradient-to-b from-theme-primary/10 via-theme-bg to-theme-bg text-theme-ink">
            <Head title={`Book with ${shopName}`} />
            <header className="sticky top-0 z-20 border-b border-theme-border bg-theme-surface/90 backdrop-blur">
                <div className="mx-auto flex max-w-2xl items-center gap-3 px-4 py-2.5">
                    <Monogram name={shopName} className="h-9 w-9 text-sm" />
                    <div className="min-w-0">
                        <p className="truncate font-semibold leading-tight">{shopName}</p>
                        <p className="text-[11px] uppercase tracking-wide text-theme-ink-muted">
                            Online booking
                        </p>
                    </div>
                    <div className="ml-auto flex items-center">
                        <button
                            type="button"
                            className="dp-icon-btn"
                            aria-label="Opening hours"
                            title="Opening hours"
                            onClick={() => setHoursOpen(true)}
                        >
                            <Clock className="h-[18px] w-[18px]" strokeWidth={1.75} />
                        </button>
                        <ThemeToggle />
                    </div>
                </div>
            </header>
            <main className="mx-auto max-w-2xl space-y-5 px-4 py-6 sm:py-10">{children}</main>
            <HoursDialog
                open={hoursOpen}
                hours={hours}
                today={today}
                onClose={() => setHoursOpen(false)}
            />
        </div>
    );
}

function HoursDialog({ open, hours, today, onClose }) {
    return (
        <Modal show={open} onClose={onClose} maxWidth="sm">
            <div className="flex items-center justify-between gap-3 border-b border-theme-border px-4 py-3 sm:px-5">
                <h2 className="flex items-center gap-2 text-base font-semibold">
                    <Clock className="h-4 w-4 text-theme-ink-muted" strokeWidth={2} />
                    Opening hours
                </h2>
                <button type="button" className="dp-icon-btn" aria-label="Close" onClick={onClose}>
                    <X className="h-4 w-4" />
                </button>
            </div>
            <dl className="space-y-1.5 px-4 py-4 text-sm sm:px-5">
                {WEEK.map((day) => {
                    const value = hours?.[day];
                    const isToday = day === today;

                    return (
                        <div
                            key={day}
                            className={`flex justify-between gap-3 ${
                                isToday ? 'font-semibold text-theme-ink' : 'text-theme-ink-soft'
                            }`}
                        >
                            <dt className="capitalize">
                                {day}
                                {isToday && (
                                    <span className="ml-1.5 text-[11px] font-medium text-theme-primary">
                                        Today
                                    </span>
                                )}
                            </dt>
                            <dd>
                                {value?.enabled
                                    ? `${slotLabel(minutesFromTime(value.open) || 0)} – ${slotLabel(minutesFromTime(value.close) || 0)}`
                                    : 'Closed'}
                            </dd>
                        </div>
                    );
                })}
            </dl>
        </Modal>
    );
}
