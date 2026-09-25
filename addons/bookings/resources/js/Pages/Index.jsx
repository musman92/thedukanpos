import AdminLayout from '@/Layouts/AdminLayout';
import Button from '@/Components/Ui/Button';
import PageLimitSelect from '@/Components/Ui/PageLimitSelect';
import Pagination from '@/Components/Ui/Pagination';
import { confirmDelete } from '@/lib/confirm';
import { Head, router } from '@inertiajs/react';
import { CalendarDays, List, Pencil, Plus, Receipt, Search, Trash2 } from 'lucide-react';
import { useState } from 'react';
import BookingCalendar from '../components/BookingCalendar';
import BookingFormDrawer from './BookingFormDrawer';

const filterClass =
    'h-9 rounded-lg border border-theme-border bg-theme-surface px-3 text-sm text-theme-ink outline-none focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20';

function ViewToggle({ view, onChange }) {
    const options = [
        { id: 'list', label: 'List', icon: List },
        { id: 'calendar', label: 'Calendar', icon: CalendarDays },
    ];

    return (
        <div className="inline-flex rounded-lg border border-theme-border bg-theme-surface p-0.5" role="group" aria-label="Bookings view">
            {options.map((option) => {
                const Icon = option.icon;
                const active = view === option.id;

                return (
                    <button
                        key={option.id}
                        type="button"
                        aria-pressed={active}
                        onClick={() => onChange(option.id)}
                        className={`inline-flex h-9 items-center gap-1.5 rounded-md px-2.5 text-sm font-semibold transition ${
                            active
                                ? 'bg-theme-primary-soft text-theme-primary'
                                : 'text-theme-ink-soft hover:bg-theme-bg hover:text-theme-ink'
                        }`}
                    >
                        <Icon className="h-4 w-4" strokeWidth={2} />
                        <span className="hidden sm:inline">{option.label}</span>
                    </button>
                );
            })}
        </div>
    );
}

export default function Index({ bookings, filters, services, calendar }) {
    const view = filters.view === 'calendar' ? 'calendar' : 'list';
    const month = filters.month;
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState(null);
    const [defaults, setDefaults] = useState(null);
    const [q, setQ] = useState(filters.q || '');

    const visit = (overrides = {}, options = {}) => router.get(route('admin.bookings.index'), {
        q: filters.q || '',
        status: filters.status || '',
        from: view === 'list' ? (filters.from || '') : '',
        to: view === 'list' ? (filters.to || '') : '',
        per_page: filters.per_page,
        view,
        month: view === 'calendar' ? month : undefined,
        ...overrides,
    }, { preserveState: true, ...options });

    const openCreate = (scheduledAt = '') => {
        setEditing(null);
        setDefaults(scheduledAt ? { scheduled_at: scheduledAt } : null);
        setOpen(true);
    };

    const openEdit = (booking) => {
        setDefaults(null);
        setEditing(booking);
        setOpen(true);
    };

    const closeForm = () => {
        setOpen(false);
        setEditing(null);
        setDefaults(null);
    };

    const remove = async (booking) => {
        if (await confirmDelete(booking.number, 'booking')) {
            router.delete(route('admin.bookings.destroy', booking.id), { preserveScroll: true });
        }
    };

    return (
        <AdminLayout
            title="Bookings"
            description="Service requests, schedule, status, and order conversion."
            actions={
                <div className="flex items-center gap-2">
                    <ViewToggle
                        view={view}
                        onChange={(next) => visit({
                            view: next,
                            month: next === 'calendar' ? month : undefined,
                            from: next === 'calendar' ? '' : filters.from,
                            to: next === 'calendar' ? '' : filters.to,
                        }, { preserveState: false })}
                    />
                    <Button onClick={() => openCreate()}>
                        <Plus className="h-4 w-4" /> Add Booking
                    </Button>
                </div>
            }
        >
            <Head title="Bookings" />
            <div className="dp-card overflow-hidden">
                <div className="flex flex-wrap gap-3 border-b border-theme-border p-4">
                    {view === 'list' && (
                        <PageLimitSelect pageKey="bookings" routeName="admin.bookings.index"
                            current={filters.per_page} companyDefault={filters.company_page_limit || 25}
                            extraQuery={{
                                q: filters.q || '',
                                status: filters.status || '',
                                from: filters.from || '',
                                to: filters.to || '',
                                view: 'list',
                            }} />
                    )}
                    <form className="ml-auto flex flex-wrap gap-2" onSubmit={(e) => { e.preventDefault(); visit({ q }); }}>
                        <div className="relative">
                            <Search className="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-theme-ink-muted" />
                            <input className={`${filterClass} w-full pl-8 sm:w-52`} value={q}
                                onChange={(e) => setQ(e.target.value)}
                                placeholder="Customer, phone, number" />
                        </div>
                        <select className={filterClass} value={filters.status || ''}
                            onChange={(e) => visit({ status: e.target.value })}>
                            <option value="">All statuses</option>
                            {['pending', 'confirmed', 'done', 'cancelled'].map((status) => (
                                <option key={status} value={status}>{status}</option>
                            ))}
                        </select>
                        {view === 'list' && (
                            <>
                                <input type="date" className={filterClass} value={filters.from || ''}
                                    onChange={(e) => visit({ from: e.target.value })} aria-label="From date" />
                                <input type="date" className={filterClass} value={filters.to || ''}
                                    onChange={(e) => visit({ to: e.target.value })} aria-label="To date" />
                            </>
                        )}
                        <Button type="submit" variant="secondary">Search</Button>
                    </form>
                </div>

                {view === 'calendar' ? (
                    <div className="p-4">
                        <BookingCalendar
                            calendar={calendar}
                            month={month}
                            onMonthChange={(next) => visit({ month: next, view: 'calendar' })}
                            onSelect={openEdit}
                            onCreate={openCreate}
                        />
                    </div>
                ) : (
                    <>
                        <div className="overflow-x-auto">
                            <table className="min-w-full text-left text-sm">
                                <thead className="bg-theme-bg text-xs uppercase text-theme-ink-muted">
                                    <tr>
                                        <th className="px-4 py-3">Booking</th><th className="px-4 py-3">Customer</th>
                                        <th className="px-4 py-3">Service</th><th className="px-4 py-3">Scheduled</th>
                                        <th className="px-4 py-3">Status</th><th className="px-4 py-3 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {bookings.data.length === 0 && <tr><td colSpan="6" className="px-4 py-12 text-center text-theme-ink-muted">No bookings yet.</td></tr>}
                                    {bookings.data.map((booking) => (
                                        <tr key={booking.id} className="border-t border-theme-border">
                                            <td className="px-4 py-3 font-mono text-xs">{booking.number}</td>
                                            <td className="px-4 py-3"><div className="font-medium">{booking.customer_name}</div><div className="text-xs text-theme-ink-muted">{booking.customer_phone}</div></td>
                                            <td className="px-4 py-3">{booking.service_name}</td>
                                            <td className="px-4 py-3">{new Date(booking.scheduled_at).toLocaleString()}</td>
                                            <td className="px-4 py-3 capitalize">{booking.status}</td>
                                            <td className="px-4 py-3">
                                                <div className="flex justify-end gap-1">
                                                    <Button size="sm" variant="secondary" onClick={() => openEdit(booking)}><Pencil className="h-4 w-4" /></Button>
                                                    {!booking.sale_id && ['confirmed', 'done'].includes(booking.status) && (
                                                        <Button size="sm" onClick={() => router.post(route('admin.bookings.convert', booking.id))}><Receipt className="h-4 w-4" /> Order</Button>
                                                    )}
                                                    <Button size="sm" variant="danger" onClick={() => remove(booking)}><Trash2 className="h-4 w-4" /></Button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        <Pagination paginator={bookings} />
                    </>
                )}
            </div>
            <BookingFormDrawer open={open} booking={editing} services={services} defaults={defaults}
                onClose={closeForm} />
        </AdminLayout>
    );
}
