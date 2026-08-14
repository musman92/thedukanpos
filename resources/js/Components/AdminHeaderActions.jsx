import ThemeToggle from '@/Components/ThemeToggle';
import { useI18n } from '@/hooks/useI18n';
import { BRANCH_HEADER, setTabBranchId } from '@/lib/branchTab';
import { Link, router, usePage } from '@inertiajs/react';
import {
    Check,
    ChevronDown,
    GitBranch,
    LayoutDashboard,
    LogOut,
    Play,
    Settings,
    Square,
    Store,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

function useClickOutside(ref, onClose) {
    useEffect(() => {
        const handler = (e) => {
            if (ref.current && !ref.current.contains(e.target)) onClose();
        };
        document.addEventListener('mousedown', handler);
        return () => document.removeEventListener('mousedown', handler);
    }, [ref, onClose]);
}

function OpenPosButton({ t }) {
    return (
        <a
            href="/pos"
            onClick={(e) => {
                e.preventDefault();
                window.location.assign('/pos');
            }}
            className="dp-btn-primary hidden min-h-11 items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-semibold lg:inline-flex"
            title={t('header.open_pos')}
        >
            <Store className="h-4 w-4" strokeWidth={2} />
            <span>{t('header.open_pos')}</span>
        </a>
    );
}

function ShiftButton({ openShift, t }) {
    if (!openShift?.id) {
        return (
            <Link
                href={route('admin.shifts.create')}
                className="inline-flex min-h-11 min-w-11 items-center justify-center gap-1.5 rounded-md px-2.5 text-sm font-semibold text-white sm:px-3"
                style={{ background: 'var(--color-success)' }}
                title={t('header.start_shift')}
                aria-label={t('header.start_shift')}
            >
                <Play className="h-4 w-4 shrink-0" fill="currentColor" strokeWidth={1.5} />
                <span className="hidden md:inline">{t('header.start_shift')}</span>
            </Link>
        );
    }

    return (
        <Link
            href={route('admin.shifts.show', { shift: openShift.id })}
            className="inline-flex min-h-11 min-w-11 items-center justify-center gap-1.5 rounded-md px-2.5 text-sm font-semibold text-white sm:px-3"
            style={{ background: 'var(--color-danger)' }}
            title={t('header.end_shift')}
            aria-label={t('header.end_shift')}
        >
            <Square className="h-4 w-4 shrink-0" fill="currentColor" strokeWidth={1.5} />
            <span className="hidden md:inline">{t('header.end_shift')}</span>
        </Link>
    );
}

function BranchDropdown({ branch, branches }) {
    const [open, setOpen] = useState(false);
    const ref = useRef(null);
    useClickOutside(ref, () => setOpen(false));

    const label = branch?.name || 'Select branch';

    const switchBranch = (b) => {
        setOpen(false);
        if (branch?.id === b.id) return;

        setTabBranchId(b.id);

        router.post(
            route('admin.branches.switch'),
            { branch_id: b.id },
            {
                preserveScroll: true,
                headers: { [BRANCH_HEADER]: String(b.id) },
            },
        );
    };

    return (
        <div className="relative" ref={ref}>
            <button
                type="button"
                onClick={() => setOpen((v) => !v)}
                className="inline-flex min-h-11 min-w-11 items-center justify-center gap-2 rounded-md border border-theme-border bg-theme-surface px-2.5 text-sm text-theme-ink sm:max-w-[12rem] sm:justify-start"
                title={label}
                aria-label={label}
            >
                <GitBranch className="h-4 w-4 shrink-0 text-theme-ink-muted" strokeWidth={1.75} />
                <span className="hidden truncate font-medium lg:inline">{label}</span>
                <ChevronDown className="hidden h-3.5 w-3.5 shrink-0 text-theme-ink-muted lg:block" />
            </button>

            {open && (
                <div className="dp-card fixed inset-x-3 top-[calc(3.75rem+env(safe-area-inset-top))] z-50 max-h-[65dvh] overflow-y-auto py-1 sm:absolute sm:inset-x-auto sm:end-0 sm:top-auto sm:mt-1.5 sm:w-56">
                    {(branches || []).map((b) => {
                        const active = branch?.id === b.id;
                        return (
                            <button
                                key={b.id}
                                type="button"
                                onClick={() => switchBranch(b)}
                                className={`flex min-h-11 w-full items-center gap-2 px-3 py-2 text-start text-sm ${
                                    active
                                        ? 'bg-theme-primary-soft font-medium text-theme-primary'
                                        : 'text-theme-ink hover:bg-theme-bg'
                                }`}
                            >
                                <span className="min-w-0 flex-1 truncate">{b.name}</span>
                                {active && <Check className="h-3.5 w-3.5 shrink-0" />}
                            </button>
                        );
                    })}
                    {(!branches || branches.length === 0) && (
                        <p className="px-3 py-2 text-sm text-theme-ink-muted">No branches</p>
                    )}
                </div>
            )}
        </div>
    );
}

function UserMenu({ user, tenant, t }) {
    const [open, setOpen] = useState(false);
    const ref = useRef(null);
    useClickOutside(ref, () => setOpen(false));

    if (!user) return null;

    const roleLabel = user.role
        ? String(user.role).replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())
        : 'User';

    return (
        <div className="relative" ref={ref}>
            <button
                type="button"
                onClick={() => setOpen((v) => !v)}
                className="inline-flex min-h-11 items-center gap-2 rounded-full border border-theme-primary/40 bg-theme-surface py-1 ps-1 pe-2"
                aria-label={user.name || 'Account menu'}
            >
                <span
                    className="flex h-8 w-8 items-center justify-center rounded-full text-xs font-bold text-white"
                    style={{ background: 'var(--color-primary)' }}
                >
                    {user.initial || 'U'}
                </span>
                <span className="hidden max-w-[7rem] truncate text-sm font-medium text-theme-ink xl:inline">
                    {user.name}
                </span>
                <ChevronDown className="hidden h-3.5 w-3.5 text-theme-primary sm:block" />
            </button>

            {open && (
                <div className="dp-card fixed inset-x-3 top-[calc(3.75rem+env(safe-area-inset-top))] z-50 overflow-hidden sm:absolute sm:inset-x-auto sm:end-0 sm:top-auto sm:mt-1.5 sm:w-64">
                    <div className="border-b border-theme-border px-3.5 py-3">
                        <p className="font-semibold text-theme-ink">{user.name}</p>
                        <p className="truncate text-xs text-theme-ink-muted">
                            {user.email || `${user.username}@${tenant?.code || 'shop'}`}
                        </p>
                        <span
                            className="mt-2 inline-block rounded px-1.5 py-0.5 text-[11px] font-medium"
                            style={{
                                background: 'var(--color-primary-soft)',
                                color: 'var(--color-primary)',
                            }}
                        >
                            {roleLabel}
                        </span>
                    </div>
                    <div className="py-1">
                        <a
                            href="/pos"
                            onClick={(e) => {
                                e.preventDefault();
                                setOpen(false);
                                window.location.assign('/pos');
                            }}
                            className="flex min-h-11 items-center gap-2.5 px-3.5 py-2 text-sm text-theme-ink-soft hover:bg-theme-bg hover:text-theme-ink lg:hidden"
                        >
                            <Store className="h-4 w-4" strokeWidth={1.75} />
                            {t('header.open_pos')}
                        </a>
                        <Link
                            href={route('admin.dashboard')}
                            className="flex min-h-11 items-center gap-2.5 px-3.5 py-2 text-sm text-theme-ink-soft hover:bg-theme-bg hover:text-theme-ink"
                            onClick={() => setOpen(false)}
                        >
                            <LayoutDashboard className="h-4 w-4" strokeWidth={1.75} />
                            {t('header.dashboard')}
                        </Link>
                        <Link
                            href={route('admin.settings.edit')}
                            className="flex min-h-11 items-center gap-2.5 px-3.5 py-2 text-sm text-theme-ink-soft hover:bg-theme-bg hover:text-theme-ink"
                            onClick={() => setOpen(false)}
                        >
                            <Settings className="h-4 w-4" strokeWidth={1.75} />
                            {t('header.settings')}
                        </Link>
                    </div>
                    <div className="border-t border-theme-border py-1">
                        <button
                            type="button"
                            onClick={() => router.post(route('logout'))}
                            className="flex min-h-11 w-full items-center gap-2.5 px-3.5 py-2 text-sm text-theme-danger hover:bg-theme-bg"
                        >
                            <LogOut className="h-4 w-4" strokeWidth={1.75} />
                            {t('header.logout')}
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
}

export default function AdminHeaderActions() {
    const { auth, branch, branches, openShift, tenant } = usePage().props;
    const { t } = useI18n();

    useEffect(() => {
        if (branch?.id) {
            setTabBranchId(branch.id);
        }
    }, [branch?.id]);

    return (
        <div className="flex min-w-0 items-center gap-1 sm:gap-2">
            <OpenPosButton t={t} />
            <ShiftButton openShift={openShift} t={t} />
            <BranchDropdown branch={branch} branches={branches} />
            <div className="hidden sm:block">
                <ThemeToggle />
            </div>
            <UserMenu user={auth?.user} tenant={tenant} t={t} />
        </div>
    );
}
