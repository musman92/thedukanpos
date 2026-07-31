import AdminLayout from '@/Layouts/AdminLayout';
import Button from '@/Components/Ui/Button';
import AttendanceFormDrawer from '@/Pages/Admin/Hr/Attendance/AttendanceFormDrawer';
import { confirmAction, confirmDelete } from '@/lib/confirm';
import { Head, router, usePage } from '@inertiajs/react';
import { Coffee, LogIn, LogOut, Pencil, Play, Plus, Trash2, UserX } from 'lucide-react';
import { useState } from 'react';

function formatMinutes(mins) {
    const m = Number(mins || 0);
    if (m <= 0) return '—';
    const h = Math.floor(m / 60);
    const rem = m % 60;
    return `${h}h ${String(rem).padStart(2, '0')}m`;
}

function PhaseBadge({ phase, record }) {
    if (phase === 'leave') {
        const label = record?.status === 'paid_leave' ? 'Paid leave' : 'Unpaid leave';
        return (
            <span className="inline-flex rounded-full bg-sky-50 px-2 py-0.5 text-xs font-semibold text-sky-700">
                {label}
            </span>
        );
    }
    if (phase === 'absent') {
        return (
            <span className="inline-flex rounded-full bg-red-50 px-2 py-0.5 text-xs font-semibold text-red-700">
                Absent
            </span>
        );
    }
    if (phase === 'finished') {
        return (
            <span className="inline-flex rounded-full bg-stone-100 px-2 py-0.5 text-xs font-semibold text-stone-700">
                Finished · {record?.clock_in}–{record?.clock_out}
            </span>
        );
    }
    if (phase === 'on_break') {
        return (
            <span className="inline-flex rounded-full bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-700">
                On break · {record?.break_started_at}
            </span>
        );
    }
    if (phase === 'working') {
        return (
            <span className="inline-flex rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700">
                Working · in {record?.clock_in}
            </span>
        );
    }
    if (phase === 'recorded') {
        return (
            <span className="inline-flex rounded-full bg-stone-100 px-2 py-0.5 text-xs font-semibold text-stone-600">
                Recorded manually
            </span>
        );
    }
    return <span className="text-xs text-theme-ink-muted">Not marked yet</span>;
}

function boardButtonClass(variant) {
    const base =
        'inline-flex h-9 items-center gap-1.5 rounded-lg px-3 text-sm font-medium transition-colors';
    if (variant === 'primary') {
        return `${base} bg-emerald-600 text-white hover:bg-emerald-700`;
    }
    if (variant === 'checkout') {
        return `${base} bg-theme-primary text-white hover:opacity-90`;
    }
    if (variant === 'break') {
        return `${base} bg-amber-50 text-amber-800 hover:bg-amber-100`;
    }
    if (variant === 'resume') {
        return `${base} bg-emerald-50 text-emerald-800 hover:bg-emerald-100`;
    }
    if (variant === 'absent') {
        return `${base} bg-red-50 text-red-700 hover:bg-red-100`;
    }
    return `${base} border border-theme-border bg-theme-surface text-theme-ink-soft hover:bg-theme-bg`;
}

export default function Index({
    records = [],
    employees = [],
    board,
    filters,
    branch,
    statuses = [],
}) {
    const { errors, flash } = usePage().props;
    const [showForm, setShowForm] = useState(false);
    const [editing, setEditing] = useState(null);
    const [date, setDate] = useState(filters.date || new Date().toISOString().slice(0, 10));
    const [busyUserId, setBusyUserId] = useState(null);

    const boardEmployees = board?.employees || [];
    const boardDate = board?.date || filters.date;

    const applyDate = (e) => {
        e.preventDefault();
        router.get(
            route('admin.hr.attendance.index'),
            { date },
            { preserveState: true },
        );
    };

    const openCreate = () => {
        setEditing(null);
        setShowForm(true);
    };

    const openEdit = (row) => {
        setEditing(row);
        setShowForm(true);
    };

    const closeForm = () => {
        setShowForm(false);
        setEditing(null);
    };

    const destroyRow = async (row) => {
        const label = `${row.user?.name || 'Record'} · ${row.attendance_date}`;
        const ok = await confirmDelete(label, 'attendance record');
        if (!ok) return;

        router.delete(route('admin.hr.attendance.destroy', row.id), {
            preserveScroll: true,
        });
    };

    const runAction = async (userId, action, name) => {
        if (action === 'absent') {
            const ok = await confirmAction({
                title: `Mark ${name} absent?`,
                text: 'This will record them as absent for today.',
                confirmText: 'Mark absent',
                icon: 'warning',
            });
            if (!ok) return;
        }
        if (action === 'check_out') {
            const ok = await confirmAction({
                title: `Check ${name} out?`,
                text: 'This ends their workday for today.',
                confirmText: 'Check out',
                icon: 'question',
            });
            if (!ok) return;
        }

        setBusyUserId(userId);
        router.post(
            route('admin.hr.attendance.action', userId),
            { action },
            {
                preserveScroll: true,
                onFinish: () => setBusyUserId(null),
            },
        );
    };

    return (
        <AdminLayout
            title="Attendance"
            description={
                branch?.name
                    ? `Check in, manage breaks, and finish the day at ${branch.name}.`
                    : 'Check employees in, manage breaks, and finish their workday.'
            }
            actions={
                <Button onClick={openCreate}>
                    <Plus className="h-4 w-4" strokeWidth={2.25} />
                    Manual entry
                </Button>
            }
        >
            <Head title="Attendance" />

            {(errors?.attendance || errors?.action || flash?.error) && (
                <div className="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    {errors?.attendance || errors?.action || flash?.error}
                </div>
            )}

            <section className="dp-card mb-6 overflow-hidden">
                <div className="flex flex-wrap items-center justify-between gap-3 border-b border-theme-border px-4 py-4">
                    <div>
                        <h2 className="text-base font-semibold text-theme-ink">Today’s team</h2>
                        <p className="text-sm text-theme-ink-muted">
                            {boardDate} · {boardEmployees.length} employee
                            {boardEmployees.length === 1 ? '' : 's'}
                        </p>
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-3 p-4 xl:grid-cols-2">
                    {boardEmployees.length === 0 && (
                        <div className="xl:col-span-2 py-10 text-center text-theme-ink-muted">
                            No active employees are assigned to this branch.
                        </div>
                    )}
                    {boardEmployees.map((employee) => {
                        const phase = employee.phase;
                        const record = employee.record;
                        const busy = busyUserId === employee.id;
                        const subtitle =
                            employee.designation ||
                            (employee.employee_number
                                ? `#${employee.employee_number}`
                                : 'Employee');
                        const initial = (employee.name || '?').slice(0, 1).toUpperCase();

                        return (
                            <article
                                key={employee.id}
                                className="flex flex-col gap-4 rounded-xl border border-theme-border p-4 sm:flex-row sm:items-center"
                            >
                                <div className="flex min-w-0 flex-1 items-center gap-3">
                                    <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-theme-bg text-sm font-semibold text-theme-ink-soft">
                                        {initial}
                                    </div>
                                    <div className="min-w-0">
                                        <h3 className="truncate font-semibold text-theme-ink">
                                            {employee.name}
                                        </h3>
                                        <p className="truncate text-xs text-theme-ink-muted">
                                            {subtitle}
                                        </p>
                                        <div className="mt-1">
                                            <PhaseBadge phase={phase} record={record} />
                                        </div>
                                    </div>
                                </div>

                                <div className="flex flex-wrap items-center gap-2 sm:justify-end">
                                    {phase === 'not_marked' && (
                                        <>
                                            <button
                                                type="button"
                                                disabled={busy}
                                                onClick={() =>
                                                    runAction(employee.id, 'check_in', employee.name)
                                                }
                                                className={boardButtonClass('primary')}
                                            >
                                                <LogIn className="h-3.5 w-3.5" />
                                                Check in
                                            </button>
                                            <button
                                                type="button"
                                                disabled={busy}
                                                onClick={() =>
                                                    runAction(employee.id, 'absent', employee.name)
                                                }
                                                className={boardButtonClass('absent')}
                                            >
                                                <UserX className="h-3.5 w-3.5" />
                                                Absent
                                            </button>
                                        </>
                                    )}

                                    {(phase === 'working' || phase === 'on_break') && (
                                        <>
                                            {phase === 'on_break' ? (
                                                <button
                                                    type="button"
                                                    disabled={busy}
                                                    onClick={() =>
                                                        runAction(
                                                            employee.id,
                                                            'end_break',
                                                            employee.name,
                                                        )
                                                    }
                                                    className={boardButtonClass('resume')}
                                                >
                                                    <Play className="h-3.5 w-3.5" />
                                                    Resume
                                                </button>
                                            ) : (
                                                <button
                                                    type="button"
                                                    disabled={busy}
                                                    onClick={() =>
                                                        runAction(
                                                            employee.id,
                                                            'start_break',
                                                            employee.name,
                                                        )
                                                    }
                                                    className={boardButtonClass('break')}
                                                >
                                                    <Coffee className="h-3.5 w-3.5" />
                                                    Break
                                                </button>
                                            )}
                                            <button
                                                type="button"
                                                disabled={busy}
                                                onClick={() =>
                                                    runAction(
                                                        employee.id,
                                                        'check_out',
                                                        employee.name,
                                                    )
                                                }
                                                className={boardButtonClass('checkout')}
                                            >
                                                <LogOut className="h-3.5 w-3.5" />
                                                Check out
                                            </button>
                                        </>
                                    )}
                                </div>
                            </article>
                        );
                    })}
                </div>
            </section>

            <div className="mb-3">
                <h2 className="text-base font-semibold text-theme-ink">Attendance history</h2>
                <p className="text-sm text-theme-ink-muted">
                    Review and correct entries for a selected date.
                </p>
            </div>

            <div className="dp-card overflow-hidden">
                <div className="flex flex-wrap items-center justify-between gap-3 border-b border-theme-border px-4 py-3">
                    <form onSubmit={applyDate} className="flex items-center gap-2">
                        <input
                            type="date"
                            value={date}
                            onChange={(e) => setDate(e.target.value)}
                            className="h-9 rounded-lg border border-theme-border bg-theme-surface px-2 text-sm"
                        />
                        <Button type="submit" variant="secondary" size="sm">
                            Go
                        </Button>
                    </form>
                    <p className="text-sm text-theme-ink-muted">
                        {records.length} record{records.length === 1 ? '' : 's'}
                    </p>
                </div>

                <div className="overflow-x-auto">
                    <table className="min-w-full text-left text-sm">
                        <thead className="bg-theme-bg text-[11px] uppercase tracking-wide text-theme-ink-muted">
                            <tr>
                                <th className="px-3 py-3 font-semibold">Employee</th>
                                <th className="px-3 py-3 font-semibold">Status</th>
                                <th className="px-3 py-3 font-semibold">In</th>
                                <th className="px-3 py-3 font-semibold">Out</th>
                                <th className="px-3 py-3 font-semibold">Break</th>
                                <th className="px-3 py-3 font-semibold">Worked</th>
                                <th className="px-3 py-3 font-semibold">Notes</th>
                                <th className="px-3 py-3 text-right font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {records.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={8}
                                        className="px-3 py-10 text-center text-theme-ink-muted"
                                    >
                                        No attendance marked for this date.
                                    </td>
                                </tr>
                            )}
                            {records.map((row) => (
                                <tr key={row.id} className="border-t border-theme-border">
                                    <td className="px-3 py-3 font-medium text-theme-ink">
                                        {row.user?.name || '—'}
                                    </td>
                                    <td className="px-3 py-3 capitalize text-theme-ink-soft">
                                        {row.on_break
                                            ? 'On break'
                                            : String(row.status || '').replace(/_/g, ' ')}
                                    </td>
                                    <td className="px-3 py-3 tabular-nums text-theme-ink-soft">
                                        {row.clock_in || '—'}
                                    </td>
                                    <td className="px-3 py-3 tabular-nums text-theme-ink-soft">
                                        {row.clock_out || '—'}
                                    </td>
                                    <td className="px-3 py-3 tabular-nums text-theme-ink-soft">
                                        {formatMinutes(row.break_minutes)}
                                        {row.on_break && row.break_started_at
                                            ? ` · since ${row.break_started_at}`
                                            : ''}
                                    </td>
                                    <td className="px-3 py-3 tabular-nums text-theme-ink-soft">
                                        {formatMinutes(row.worked_minutes)}
                                    </td>
                                    <td className="max-w-[10rem] truncate px-3 py-3 text-theme-ink-muted">
                                        {row.notes || '—'}
                                    </td>
                                    <td className="px-3 py-3 text-right">
                                        <div className="flex items-center justify-end gap-1">
                                            <button
                                                type="button"
                                                title="Edit"
                                                aria-label="Edit"
                                                onClick={() => openEdit(row)}
                                                className="inline-flex rounded-md p-1.5 text-theme-primary hover:bg-theme-bg"
                                            >
                                                <Pencil className="h-4 w-4" />
                                            </button>
                                            <button
                                                type="button"
                                                title="Delete"
                                                aria-label="Delete"
                                                onClick={() => destroyRow(row)}
                                                className="inline-flex rounded-md p-1.5 text-theme-danger hover:bg-theme-danger/10"
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            <AttendanceFormDrawer
                open={showForm}
                record={editing}
                employees={employees}
                statuses={statuses}
                defaultDate={filters.date}
                onClose={closeForm}
            />
        </AdminLayout>
    );
}
