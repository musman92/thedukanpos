import Drawer from '@/Components/Ui/Drawer';
import Button from '@/Components/Ui/Button';
import Input, { Field } from '@/Components/Ui/Input';
import { router, useForm } from '@inertiajs/react';
import { useEffect } from 'react';

export default function TenantRestoreBackupDrawer({
    open,
    onClose,
    tenant,
    backup = null,
    mode = 'stored',
}) {
    const form = useForm({
        confirm_code: '',
        backup: null,
    });

    useEffect(() => {
        if (!open) return;
        form.clearErrors();
        form.setData({
            confirm_code: '',
            backup: null,
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, tenant?.id, backup?.file, mode]);

    if (!tenant) return null;

    const canSubmit =
        form.data.confirm_code.trim().toLowerCase() === tenant.code.toLowerCase() &&
        (mode === 'stored' ? !!backup?.file : !!form.data.backup);

    const submit = (e) => {
        e.preventDefault();
        if (!canSubmit) return;

        const label =
            mode === 'stored'
                ? backup.file
                : form.data.backup?.name || 'uploaded backup';

        if (
            !window.confirm(
                `Restore ${tenant.name} from “${label}”?\n\nAll current company data in this database will be replaced. This cannot be undone.`,
            )
        ) {
            return;
        }

        if (mode === 'stored') {
            form.post(route('platform.tenants.backups.restore', { tenant: tenant.id, file: backup.file }), {
                preserveScroll: true,
                onSuccess: () => onClose(),
            });
            return;
        }

        form.post(route('platform.tenants.backups.restore-upload', tenant.id), {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => onClose(),
        });
    };

    return (
        <Drawer
            open={open}
            onClose={onClose}
            title="Restore database backup"
            description={`${tenant.name} · full database replace`}
            width="sm"
        >
            <div className="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
                <p className="font-semibold">This replaces all tenant data</p>
                <p className="mt-1">
                    Branches, products, sales, settings — everything in this company database
                    will be overwritten by the backup.
                </p>
            </div>

            <form onSubmit={submit} className="space-y-4">
                {mode === 'stored' ? (
                    <div className="rounded-lg border border-theme-border bg-theme-bg px-3 py-2 text-sm">
                        <p className="text-xs uppercase tracking-wide text-theme-ink-muted">Backup file</p>
                        <p className="mt-1 font-mono text-xs text-theme-ink">{backup?.file}</p>
                        {backup?.created_at && (
                            <p className="mt-1 text-xs text-theme-ink-muted">{backup.created_at}</p>
                        )}
                    </div>
                ) : (
                    <Field label="Backup file" required error={form.errors.backup}>
                        <input
                            type="file"
                            accept=".sql,.gz,application/gzip,application/sql"
                            className="block w-full text-sm text-theme-ink file:mr-3 file:rounded-lg file:border-0 file:bg-theme-bg file:px-3 file:py-2 file:text-sm file:font-semibold file:text-theme-ink"
                            onChange={(e) => form.setData('backup', e.target.files?.[0] ?? null)}
                        />
                    </Field>
                )}

                <Field
                    label={`Type ${tenant.code} to confirm`}
                    required
                    error={form.errors.confirm_code}
                >
                    <Input
                        value={form.data.confirm_code}
                        onChange={(e) => form.setData('confirm_code', e.target.value)}
                        placeholder={tenant.code}
                        autoComplete="off"
                        error={!!form.errors.confirm_code}
                    />
                </Field>

                <div className="flex justify-end gap-2 border-t border-theme-border pt-4">
                    <Button type="button" variant="secondary" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button type="submit" variant="danger" disabled={!canSubmit || form.processing}>
                        {form.processing ? 'Restoring…' : 'Restore backup'}
                    </Button>
                </div>
            </form>
        </Drawer>
    );
}
