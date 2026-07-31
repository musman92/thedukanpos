import Button from '@/Components/Ui/Button';
import { Download } from 'lucide-react';
import { useState } from 'react';

export default function CategoryExportMenu() {
    const [open, setOpen] = useState(false);

    return (
        <div className="relative">
            <Button variant="secondary" onClick={() => setOpen((v) => !v)}>
                <Download className="h-4 w-4" strokeWidth={2.25} />
                Export
            </Button>
            {open && (
                <>
                    <button
                        type="button"
                        className="fixed inset-0 z-10 cursor-default"
                        aria-label="Close export menu"
                        onClick={() => setOpen(false)}
                    />
                    <div className="absolute right-0 z-20 mt-1 min-w-[10rem] overflow-hidden rounded-lg border border-theme-border bg-theme-surface py-1 shadow-lg">
                        <a
                            href={route('admin.import-export.categories.export', { format: 'csv' })}
                            className="block px-3 py-2 text-sm text-theme-ink hover:bg-theme-bg"
                            onClick={() => setOpen(false)}
                        >
                            Export CSV
                        </a>
                        <a
                            href={route('admin.import-export.categories.export', { format: 'xlsx' })}
                            className="block px-3 py-2 text-sm text-theme-ink hover:bg-theme-bg"
                            onClick={() => setOpen(false)}
                        >
                            Export Excel
                        </a>
                    </div>
                </>
            )}
        </div>
    );
}
