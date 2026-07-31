import Button from '@/Components/Ui/Button';
import Drawer from '@/Components/Ui/Drawer';
import { Field, TextArea } from '@/Components/Ui/Input';
import { useForm } from '@inertiajs/react';
import { useEffect } from 'react';

export default function LeaveReviewDrawer({ open, leave = null, onClose }) {
    const form = useForm({
        status: 'approved',
        review_notes: '',
    });

    useEffect(() => {
        if (!open) {
            return undefined;
        }

        form.clearErrors();
        form.setData({
            status: 'approved',
            review_notes: '',
        });

        return undefined;
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, leave?.id]);

    const submit = (e) => {
        e.preventDefault();
        if (!leave) return;

        form.post(route('admin.hr.leaves.review', leave.id), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onClose();
            },
        });
    };

    return (
        <Drawer
            open={open}
            onClose={onClose}
            title="Review leave"
            description={
                leave
                    ? `${leave.user?.name || 'Employee'} · ${leave.start_date} → ${leave.end_date}`
                    : undefined
            }
            width="md"
        >
            <form onSubmit={submit} className="flex h-full flex-col">
                <div className="space-y-4">
                    <Field label="Decision" required error={form.errors.status}>
                        <div className="flex gap-2">
                            <button
                                type="button"
                                onClick={() => form.setData('status', 'approved')}
                                className={`h-10 flex-1 rounded-lg border text-sm font-medium ${
                                    form.data.status === 'approved'
                                        ? 'border-emerald-600 bg-emerald-50 text-emerald-800'
                                        : 'border-theme-border bg-theme-surface text-theme-ink-soft'
                                }`}
                            >
                                Approve
                            </button>
                            <button
                                type="button"
                                onClick={() => form.setData('status', 'rejected')}
                                className={`h-10 flex-1 rounded-lg border text-sm font-medium ${
                                    form.data.status === 'rejected'
                                        ? 'border-red-600 bg-red-50 text-red-800'
                                        : 'border-theme-border bg-theme-surface text-theme-ink-soft'
                                }`}
                            >
                                Reject
                            </button>
                        </div>
                    </Field>

                    <Field label="Notes" error={form.errors.review_notes}>
                        <TextArea
                            rows={4}
                            value={form.data.review_notes}
                            onChange={(e) => form.setData('review_notes', e.target.value)}
                            error={!!form.errors.review_notes}
                            placeholder="Optional"
                            autoFocus
                        />
                    </Field>

                    {leave?.reason && (
                        <p className="rounded-lg bg-theme-bg px-3 py-2 text-sm text-theme-ink-soft">
                            <span className="font-medium text-theme-ink">Reason: </span>
                            {leave.reason}
                        </p>
                    )}
                </div>

                <div className="mt-auto flex justify-end gap-2 border-t border-theme-border pt-5">
                    <Button type="button" variant="secondary" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button type="submit" disabled={form.processing || !leave}>
                        Save decision
                    </Button>
                </div>
            </form>
        </Drawer>
    );
}
