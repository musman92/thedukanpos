import Button from '@/Components/Ui/Button';
import Drawer from '@/Components/Ui/Drawer';
import Input, { Field } from '@/Components/Ui/Input';
import { useForm } from '@inertiajs/react';
import { useEffect } from 'react';

const emptyData = (roles = []) => ({
    name: '',
    email: '',
    phone: '',
    is_active: true,
    can_login: true,
    username: '',
    password: '',
    role: roles[0] || '',
    branch_id: '',
    is_employee: false,
    employee_number: '',
    designation: '',
    department: '',
    hire_date: '',
    employment_status: 'active',
    pay_frequency: 'monthly',
    pay_rate: '',
    employee_branch_id: '',
    address: '',
    notes: '',
});

export default function UserFormDrawer({
    open,
    person = null,
    onClose,
    roles = [],
    branches = [],
}) {
    const editing = !!person;
    const form = useForm(emptyData(roles));

    useEffect(() => {
        if (!open) {
            return undefined;
        }

        form.clearErrors();

        if (person) {
            const profile = person.employee_profile;
            form.setData({
                name: person.name || '',
                email: person.email || '',
                phone: person.phone || '',
                is_active: !!person.is_active,
                can_login: !!person.can_login,
                username: person.username || '',
                password: '',
                role: person.roles?.[0] || roles[0] || '',
                branch_id: person.branch_id ? String(person.branch_id) : '',
                is_employee: !!person.is_employee,
                employee_number: profile?.employee_number || '',
                designation: profile?.designation || '',
                department: profile?.department || '',
                hire_date: profile?.hire_date || '',
                employment_status: profile?.employment_status || 'active',
                pay_frequency: profile?.pay_frequency || 'monthly',
                pay_rate: profile?.pay_rate != null ? String(profile.pay_rate) : '',
                employee_branch_id: profile?.branch_id ? String(profile.branch_id) : '',
                address: profile?.address || '',
                notes: profile?.notes || '',
            });
        } else {
            form.setData(emptyData(roles));
        }

        return undefined;
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, person?.id]);

    const submit = (e) => {
        e.preventDefault();

        form.transform((data) => ({
            ...data,
            branch_id: data.branch_id || null,
            employee_branch_id: data.employee_branch_id || null,
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
            form.post(route('admin.users.update', person.id), options);
            return;
        }

        form.post(route('admin.users.store'), options);
    };

    return (
        <Drawer
            open={open}
            onClose={onClose}
            title={editing ? 'Edit user' : 'Add user'}
            description="Login accounts and optional employee / payroll details in one place."
            width="lg"
        >
            <form onSubmit={submit} className="flex h-full flex-col">
                <div className="space-y-5">
                    <section className="space-y-4">
                        <h3 className="text-xs font-semibold uppercase tracking-wide text-theme-ink-muted">
                            Identity
                        </h3>
                        <Field label="Name" required error={form.errors.name}>
                            <Input
                                value={form.data.name}
                                onChange={(e) => form.setData('name', e.target.value)}
                                error={!!form.errors.name}
                                autoFocus
                            />
                        </Field>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field label="Phone" error={form.errors.phone}>
                                <Input
                                    value={form.data.phone}
                                    onChange={(e) => form.setData('phone', e.target.value)}
                                    error={!!form.errors.phone}
                                />
                            </Field>
                            <Field label="Email" error={form.errors.email}>
                                <Input
                                    type="email"
                                    value={form.data.email}
                                    onChange={(e) => form.setData('email', e.target.value)}
                                    error={!!form.errors.email}
                                />
                            </Field>
                        </div>
                        <label className="flex items-center gap-2 text-sm text-theme-ink">
                            <input
                                type="checkbox"
                                checked={form.data.is_active}
                                onChange={(e) => form.setData('is_active', e.target.checked)}
                                className="rounded border-theme-border text-theme-primary focus:ring-theme-primary"
                            />
                            Active
                        </label>
                    </section>

                    <section className="space-y-4 border-t border-theme-border pt-4">
                        <label className="flex items-center gap-2 text-sm font-medium text-theme-ink">
                            <input
                                type="checkbox"
                                checked={form.data.can_login}
                                onChange={(e) => form.setData('can_login', e.target.checked)}
                                className="rounded border-theme-border text-theme-primary focus:ring-theme-primary"
                            />
                            Can log in to POS / admin
                        </label>
                        {form.data.can_login && (
                            <>
                                <Field label="Username" required error={form.errors.username}>
                                    <Input
                                        value={form.data.username}
                                        onChange={(e) => form.setData('username', e.target.value)}
                                        error={!!form.errors.username}
                                        placeholder="e.g. ali"
                                    />
                                </Field>
                                <Field
                                    label={editing ? 'Password' : 'Password'}
                                    required={!editing}
                                    error={form.errors.password}
                                    hint={editing ? 'Leave blank to keep current password.' : null}
                                >
                                    <Input
                                        type="password"
                                        value={form.data.password}
                                        onChange={(e) => form.setData('password', e.target.value)}
                                        error={!!form.errors.password}
                                        autoComplete="new-password"
                                    />
                                </Field>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <Field label="Role" required error={form.errors.role}>
                                        <select
                                            value={form.data.role}
                                            onChange={(e) => form.setData('role', e.target.value)}
                                            className="h-10 w-full rounded-lg border border-theme-border bg-theme-surface px-3 text-sm text-theme-ink outline-none focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20"
                                        >
                                            <option value="">Select role</option>
                                            {roles.map((r) => (
                                                <option key={r} value={r}>{r}</option>
                                            ))}
                                        </select>
                                    </Field>
                                    <Field label="Branch" error={form.errors.branch_id}>
                                        <select
                                            value={form.data.branch_id}
                                            onChange={(e) => form.setData('branch_id', e.target.value)}
                                            className="h-10 w-full rounded-lg border border-theme-border bg-theme-surface px-3 text-sm text-theme-ink outline-none focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20"
                                        >
                                            <option value="">No branch</option>
                                            {branches.map((b) => (
                                                <option key={b.id} value={b.id}>{b.name}</option>
                                            ))}
                                        </select>
                                    </Field>
                                </div>
                            </>
                        )}
                    </section>

                    <section className="space-y-4 border-t border-theme-border pt-4">
                        <label className="flex items-center gap-2 text-sm font-medium text-theme-ink">
                            <input
                                type="checkbox"
                                checked={form.data.is_employee}
                                onChange={(e) => form.setData('is_employee', e.target.checked)}
                                className="rounded border-theme-border text-theme-primary focus:ring-theme-primary"
                            />
                            Employee (HR / payroll)
                        </label>
                        {form.errors.is_employee && (
                            <p className="text-sm text-theme-danger">{form.errors.is_employee}</p>
                        )}
                        {form.data.is_employee && (
                            <>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <Field
                                        label="Employee #"
                                        error={form.errors.employee_number}
                                        hint="Blank assigns E01, E02…"
                                    >
                                        <Input
                                            value={form.data.employee_number}
                                            onChange={(e) => form.setData('employee_number', e.target.value)}
                                            error={!!form.errors.employee_number}
                                        />
                                    </Field>
                                    <Field label="Designation" error={form.errors.designation}>
                                        <Input
                                            value={form.data.designation}
                                            onChange={(e) => form.setData('designation', e.target.value)}
                                            error={!!form.errors.designation}
                                        />
                                    </Field>
                                    <Field label="Department" error={form.errors.department}>
                                        <Input
                                            value={form.data.department}
                                            onChange={(e) => form.setData('department', e.target.value)}
                                            error={!!form.errors.department}
                                        />
                                    </Field>
                                    <Field label="Hire date" error={form.errors.hire_date}>
                                        <Input
                                            type="date"
                                            value={form.data.hire_date}
                                            onChange={(e) => form.setData('hire_date', e.target.value)}
                                            error={!!form.errors.hire_date}
                                        />
                                    </Field>
                                    <Field label="Pay frequency" error={form.errors.pay_frequency}>
                                        <select
                                            value={form.data.pay_frequency}
                                            onChange={(e) => form.setData('pay_frequency', e.target.value)}
                                            className="h-10 w-full rounded-lg border border-theme-border bg-theme-surface px-3 text-sm text-theme-ink outline-none focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20"
                                        >
                                            <option value="daily">Daily</option>
                                            <option value="weekly">Weekly</option>
                                            <option value="fortnight">Fortnight</option>
                                            <option value="monthly">Monthly</option>
                                        </select>
                                    </Field>
                                    <Field label="Pay rate" error={form.errors.pay_rate}>
                                        <Input
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            value={form.data.pay_rate}
                                            onChange={(e) => form.setData('pay_rate', e.target.value)}
                                            error={!!form.errors.pay_rate}
                                        />
                                    </Field>
                                    <Field label="Employment status" error={form.errors.employment_status}>
                                        <select
                                            value={form.data.employment_status}
                                            onChange={(e) => form.setData('employment_status', e.target.value)}
                                            className="h-10 w-full rounded-lg border border-theme-border bg-theme-surface px-3 text-sm text-theme-ink outline-none focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20"
                                        >
                                            <option value="active">Active</option>
                                            <option value="suspended">Suspended</option>
                                            <option value="resigned">Resigned</option>
                                            <option value="terminated">Terminated</option>
                                        </select>
                                    </Field>
                                    <Field label="HR branch" error={form.errors.employee_branch_id}>
                                        <select
                                            value={form.data.employee_branch_id}
                                            onChange={(e) => form.setData('employee_branch_id', e.target.value)}
                                            className="h-10 w-full rounded-lg border border-theme-border bg-theme-surface px-3 text-sm text-theme-ink outline-none focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20"
                                        >
                                            <option value="">Same as login / none</option>
                                            {branches.map((b) => (
                                                <option key={b.id} value={b.id}>{b.name}</option>
                                            ))}
                                        </select>
                                    </Field>
                                </div>
                                <Field label="Address" error={form.errors.address}>
                                    <textarea
                                        value={form.data.address}
                                        onChange={(e) => form.setData('address', e.target.value)}
                                        rows={2}
                                        className="w-full rounded-lg border border-theme-border bg-theme-surface px-3 py-2 text-sm text-theme-ink outline-none focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20"
                                    />
                                </Field>
                                <Field label="Notes" error={form.errors.notes}>
                                    <textarea
                                        value={form.data.notes}
                                        onChange={(e) => form.setData('notes', e.target.value)}
                                        rows={2}
                                        className="w-full rounded-lg border border-theme-border bg-theme-surface px-3 py-2 text-sm text-theme-ink outline-none focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20"
                                    />
                                </Field>
                            </>
                        )}
                    </section>
                </div>

                <div className="mt-auto flex justify-end gap-2 border-t border-theme-border pt-5">
                    <Button type="button" variant="secondary" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button type="submit" disabled={form.processing}>
                        {editing ? 'Save changes' : 'Create user'}
                    </Button>
                </div>
            </form>
        </Drawer>
    );
}
