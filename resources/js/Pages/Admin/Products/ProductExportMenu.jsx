import Button from '@/Components/Ui/Button';
import { Download } from 'lucide-react';

export default function ProductExportMenu() {
    return (
        <Button
            variant="secondary"
            onClick={() => {
                window.location.href = route('admin.import-export.products.export', {
                    format: 'xlsx',
                });
            }}
        >
            <Download className="h-4 w-4" strokeWidth={2.25} />
            Export Excel
        </Button>
    );
}
