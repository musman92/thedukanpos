import Button from '@/Components/Ui/Button';
import TenantRestoreBackupDrawer from '@/Pages/Platform/Tenants/TenantRestoreBackupDrawer';
import { router } from '@inertiajs/react';
import { DatabaseBackup, Download, RotateCcw, Upload } from 'lucide-react';
import { useState } from 'react';

export default function TenantBackupsPanel({ tenant, backups = [] }) {
    const [restoreOpen, setRestoreOpen] = useState(false);
    const [restoreMode, setRestoreMode] = useState('stored');
    const [selectedBackup, setSelectedBackup] = useState(null);
    const [creating, setCreating] = useState(false);

    const createBackup = () => {
        if (
            !window.confirm(
                `Create a full database backup for ${tenant.name}? This may take a moment on large databases.`,
            )
        ) {
            return;
        }

        setCreating(true);
        router.post(route('platform.tenants.backups.store', tenant.id), {}, {
            preserveScroll: true,
            onFinish: () => setCreating(false),
        });
    };

    const openRestore = (backup) => {
        setSelectedBackup(backup);
        setRestoreMode('stored');
        setRestoreOpen(true);
    };

    const openUploadRestore = () => {
        setSelectedBackup(null);
        setRestoreMode('upload');
        setRestoreOpen(true);
    };

    return (
        <>
            <div className="dp-card overflow-hidden">
                <div className="flex flex-col gap-3 border-b border-theme-border px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-start gap-3">
                        <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-[var(--color-primary-soft)] text-theme-primary">
                            <DatabaseBackup className="h-4 w-4" strokeWidth={2.25} />
                        </div>
                        <div>
                            <h2 className="text-base font-semibold text-theme-ink">Database backups</h2>
                            <p className="mt-0.5 text-sm text-theme-ink-muted">
                                Full tenant database snapshots stored on this server. Download for
                                safekeeping or restore to roll back.
                            </p>
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button
                            variant="secondary"
                            disabled={!tenant.is_active || creating}
                            onClick={openUploadRestore}
                        >
                            <Upload className="h-4 w-4" />
                            Restore upload
                        </Button>
                        <Button disabled={!tenant.is_active || creating} onClick={createBackup}>
                            <DatabaseBackup className="h-4 w-4" />
                            {creating ? 'Creating…' : 'Create backup'}
                        </Button>
                    </div>
                </div>

                {backups.length === 0 ? (
                    <div className="px-4 py-10 text-center text-sm text-theme-ink-muted">
                        No backups yet. Create one before major changes or when onboarding is
                        complete.
                    </div>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-left text-sm">
                            <thead className="bg-theme-bg text-[11px] uppercase tracking-wide text-theme-ink-muted">
                                <tr>
                                    <th className="px-4 py-3 font-semibold">File</th>
                                    <th className="px-4 py-3 font-semibold">Created</th>
                                    <th className="px-4 py-3 font-semibold">Size</th>
                                    <th className="px-4 py-3 text-right font-semibold">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {backups.map((backup) => (
                                    <tr key={backup.file} className="border-t border-theme-border">
                                        <td className="px-4 py-3 font-mono text-xs text-theme-ink">
                                            {backup.file}
                                        </td>
                                        <td className="px-4 py-3 text-theme-ink-soft">
                                            {backup.created_at}
                                        </td>
                                        <td className="px-4 py-3 text-theme-ink-soft">
                                            {backup.size_label}
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex items-center justify-end gap-1">
                                                <a
                                                    href={route('platform.tenants.backups.download', {
                                                        tenant: tenant.id,
                                                        file: backup.file,
                                                    })}
                                                    className="inline-flex rounded-lg p-2 text-theme-ink-muted hover:bg-theme-bg hover:text-theme-ink"
                                                    title="Download"
                                                >
                                                    <Download className="h-4 w-4" />
                                                </a>
                                                <button
                                                    type="button"
                                                    disabled={!tenant.is_active}
                                                    onClick={() => openRestore(backup)}
                                                    className="inline-flex rounded-lg p-2 text-theme-ink-muted hover:bg-theme-bg hover:text-theme-ink disabled:opacity-50"
                                                    title="Restore"
                                                >
                                                    <RotateCcw className="h-4 w-4" />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>

            <TenantRestoreBackupDrawer
                open={restoreOpen}
                tenant={tenant}
                backup={selectedBackup}
                mode={restoreMode}
                onClose={() => setRestoreOpen(false)}
            />
        </>
    );
}
