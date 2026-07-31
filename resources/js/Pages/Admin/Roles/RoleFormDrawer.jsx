import Button from '@/Components/Ui/Button';
import Drawer from '@/Components/Ui/Drawer';
import Input, { Field } from '@/Components/Ui/Input';
import { useForm } from '@inertiajs/react';
import { useEffect, useMemo } from 'react';

const emptyData = () => ({
    name: '',
    permissions: [],
});

const STANDARD_ACTIONS = [
    { key: 'index', label: 'View' },
    { key: 'store', label: 'Create' },
    { key: 'update', label: 'Update' },
    { key: 'destroy', label: 'Delete' },
];

const STANDARD_KEYS = STANDARD_ACTIONS.map((a) => a.key);

function buildRows(permissionGroups) {
    return permissionGroups.map((group) => {
        const byAction = Object.fromEntries(
            group.permissions.map((p) => [p.action, p]),
        );
        const standard = STANDARD_ACTIONS.map((a) => byAction[a.key] || null);
        const extras = group.permissions.filter((p) => !STANDARD_KEYS.includes(p.action));
        const names = group.permissions.map((p) => p.name);

        return {
            title: group.title,
            standard,
            extras,
            names,
        };
    });
}

function MatrixCheckbox({ checked, onChange, title, disabled = false }) {
    if (disabled) {
        return <span className="inline-block h-4 w-4" aria-hidden="true" />;
    }

    return (
        <input
            type="checkbox"
            checked={checked}
            onChange={onChange}
            title={title}
            aria-label={title}
            className="h-4 w-4 cursor-pointer rounded border-2 border-stone-500 bg-white text-theme-primary accent-theme-primary checked:border-theme-primary focus:ring-2 focus:ring-theme-primary/30"
        />
    );
}

export default function RoleFormDrawer({
    open,
    role = null,
    onClose,
    permissionGroups = [],
}) {
    const editing = !!role;
    const protectedRole = !!role?.is_protected;
    const form = useForm(emptyData());

    useEffect(() => {
        if (!open) {
            return undefined;
        }

        form.clearErrors();

        if (role) {
            form.setData({
                name: role.name || '',
                permissions: [...(role.permissions || [])],
            });
        } else {
            form.setData(emptyData());
        }

        return undefined;
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, role?.id]);

    const rows = useMemo(() => buildRows(permissionGroups), [permissionGroups]);

    const allPermissionNames = useMemo(
        () => rows.flatMap((row) => row.names),
        [rows],
    );

    const selected = form.data.permissions;
    const selectedCount = selected.length;

    const isOn = (name) => selected.includes(name);

    const toggle = (name) => {
        if (!name) return;
        const next = isOn(name)
            ? selected.filter((p) => p !== name)
            : [...selected, name];
        form.setData('permissions', next);
    };

    const toggleRow = (row) => {
        const allOn = row.names.every((n) => isOn(n));
        if (allOn) {
            form.setData(
                'permissions',
                selected.filter((p) => !row.names.includes(p)),
            );
            return;
        }
        form.setData('permissions', [...new Set([...selected, ...row.names])]);
    };

    const toggleColumn = (actionKey) => {
        const names = rows
            .map((row) => row.standard.find((p) => p?.action === actionKey)?.name)
            .filter(Boolean);
        if (names.length === 0) return;

        const allOn = names.every((n) => isOn(n));
        if (allOn) {
            form.setData(
                'permissions',
                selected.filter((p) => !names.includes(p)),
            );
            return;
        }
        form.setData('permissions', [...new Set([...selected, ...names])]);
    };

    const selectAll = () => form.setData('permissions', [...allPermissionNames]);
    const clearAll = () => form.setData('permissions', []);

    const submit = (e) => {
        e.preventDefault();

        form.transform((data) => ({
            ...data,
            ...(editing ? { _method: 'put' } : {}),
        }));

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                if (!editing) {
                    form.reset();
                }
                onClose();
            },
            onFinish: () => form.transform((data) => data),
        };

        if (editing) {
            form.post(route('admin.roles.update', role.id), options);
            return;
        }

        form.post(route('admin.roles.store'), options);
    };

    return (
        <Drawer
            open={open}
            onClose={onClose}
            title={editing ? 'Edit role' : 'Add role'}
            description="Assign module permissions. System roles can change permissions but not their name."
            width="xl"
            bodyClassName="flex flex-col overflow-hidden"
        >
            <form onSubmit={submit} className="flex h-full min-h-0 flex-col">
                <div className="flex min-h-0 flex-1 flex-col gap-4">
                    <Field
                        label="Role name"
                        required
                        error={form.errors.name}
                        hint={protectedRole ? 'System role — name is locked.' : null}
                    >
                        <Input
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                            error={!!form.errors.name}
                            disabled={protectedRole}
                            autoFocus={!protectedRole}
                            placeholder="e.g. Store Supervisor"
                        />
                    </Field>

                    <div className="flex shrink-0 flex-wrap items-center justify-between gap-2">
                        <p className="text-sm font-medium text-theme-ink">
                            Permissions
                            <span className="ml-2 font-normal text-theme-ink-muted">
                                {selectedCount} selected
                            </span>
                        </p>
                        <div className="flex gap-2">
                            <button
                                type="button"
                                onClick={selectAll}
                                className="text-xs font-medium text-theme-primary hover:underline"
                            >
                                Select all
                            </button>
                            <button
                                type="button"
                                onClick={clearAll}
                                className="text-xs font-medium text-theme-ink-soft hover:underline"
                            >
                                Clear
                            </button>
                        </div>
                    </div>
                    {form.errors.permissions && (
                        <p className="shrink-0 text-sm text-theme-danger">{form.errors.permissions}</p>
                    )}

                    <div className="min-h-0 flex-1 overflow-auto rounded-lg border border-theme-border">
                        <table className="min-w-full border-collapse text-left text-sm">
                            <thead className="sticky top-0 z-10 bg-theme-bg text-[11px] uppercase tracking-wide text-theme-ink-muted">
                                <tr>
                                    <th className="whitespace-nowrap px-3 py-2.5 font-semibold">
                                        Module
                                    </th>
                                    <th className="w-12 px-2 py-2.5 text-center font-semibold" title="Toggle entire module">
                                        All
                                    </th>
                                    {STANDARD_ACTIONS.map((action) => (
                                        <th
                                            key={action.key}
                                            className="w-20 px-2 py-2.5 text-center font-semibold"
                                        >
                                            <button
                                                type="button"
                                                onClick={() => toggleColumn(action.key)}
                                                className="hover:text-theme-primary hover:underline"
                                                title={`Toggle ${action.label} for all modules`}
                                            >
                                                {action.label}
                                            </button>
                                        </th>
                                    ))}
                                    <th className="min-w-[12rem] px-3 py-2.5 font-semibold">
                                        Other
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {rows.map((row) => {
                                    const rowAllOn =
                                        row.names.length > 0 &&
                                        row.names.every((n) => isOn(n));

                                    return (
                                        <tr
                                            key={row.title}
                                            className="border-t border-theme-border hover:bg-theme-bg/60"
                                        >
                                            <td className="whitespace-nowrap px-3 py-2 font-medium text-theme-ink">
                                                {row.title}
                                            </td>
                                            <td className="px-2 py-2 text-center">
                                                <MatrixCheckbox
                                                    checked={rowAllOn}
                                                    onChange={() => toggleRow(row)}
                                                    title={`Toggle all for ${row.title}`}
                                                />
                                            </td>
                                            {row.standard.map((perm, idx) => (
                                                <td key={STANDARD_ACTIONS[idx].key} className="px-2 py-2 text-center">
                                                    <MatrixCheckbox
                                                        checked={!!perm && isOn(perm.name)}
                                                        onChange={() => toggle(perm?.name)}
                                                        title={perm?.name || ''}
                                                        disabled={!perm}
                                                    />
                                                </td>
                                            ))}
                                            <td className="px-3 py-2">
                                                {row.extras.length === 0 ? (
                                                    <span className="text-theme-ink-muted">—</span>
                                                ) : (
                                                    <div className="flex flex-wrap gap-x-3 gap-y-1.5">
                                                        {row.extras.map((perm) => (
                                                            <label
                                                                key={perm.name}
                                                                className="inline-flex items-center gap-1.5 text-xs text-theme-ink"
                                                                title={perm.name}
                                                            >
                                                                <input
                                                                    type="checkbox"
                                                                    checked={isOn(perm.name)}
                                                                    onChange={() => toggle(perm.name)}
                                                                    className="h-3.5 w-3.5 cursor-pointer rounded border-2 border-stone-500 bg-white text-theme-primary accent-theme-primary checked:border-theme-primary focus:ring-2 focus:ring-theme-primary/30"
                                                                />
                                                                {perm.label}
                                                            </label>
                                                        ))}
                                                    </div>
                                                )}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                </div>

                <div className="mt-4 flex shrink-0 justify-end gap-2 border-t border-theme-border pt-4">
                    <Button type="button" variant="secondary" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button type="submit" disabled={form.processing}>
                        {editing ? 'Save changes' : 'Create role'}
                    </Button>
                </div>
            </form>
        </Drawer>
    );
}
