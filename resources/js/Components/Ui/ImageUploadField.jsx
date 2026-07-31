import { Field } from '@/Components/Ui/Input';
import { IMAGE_UPLOAD, imageUploadHint, validateImageFile } from '@/lib/imageUpload';
import { ImagePlus, X } from 'lucide-react';
import { useEffect, useRef } from 'react';

/**
 * Reusable image picker with preview + shared size limit.
 *
 * Parent owns preview URL (blob or existing server URL) and form file value.
 *
 * @param {object} props
 * @param {string} [props.label]
 * @param {boolean} [props.required]
 * @param {string|null} [props.previewUrl]
 * @param {string} [props.error]
 * @param {string} [props.hint]
 * @param {(file: File) => void} props.onSelect
 * @param {() => void} props.onClear
 * @param {(message: string) => void} [props.onReject]
 * @param {string|number} [props.resetKey] - change to clear the native file input
 * @param {string} [props.className]
 */
export default function ImageUploadField({
    label = 'Image',
    required = false,
    previewUrl = null,
    error = null,
    hint = imageUploadHint(),
    onSelect,
    onClear,
    onReject,
    resetKey,
    className = '',
}) {
    const fileRef = useRef(null);

    useEffect(() => {
        if (fileRef.current) {
            fileRef.current.value = '';
        }
    }, [resetKey]);

    const handleChange = (e) => {
        const file = e.target.files?.[0];
        if (!file) return;

        const check = validateImageFile(file);
        if (!check.ok) {
            onReject?.(check.message);
            e.target.value = '';
            return;
        }

        onSelect(file);
    };

    const handleClear = () => {
        if (fileRef.current) {
            fileRef.current.value = '';
        }
        onClear();
    };

    return (
        <Field label={label} required={required} error={error} hint={!error ? hint : undefined} className={className}>
            <div className="flex items-start gap-3">
                <div className="relative h-20 w-20 shrink-0 overflow-hidden rounded-lg bg-theme-bg ring-1 ring-theme-border">
                    {previewUrl ? (
                        <img src={previewUrl} alt="" className="h-full w-full object-cover" />
                    ) : (
                        <div className="flex h-full w-full items-center justify-center text-theme-ink-muted">
                            <ImagePlus className="h-5 w-5" />
                        </div>
                    )}
                    {previewUrl && (
                        <button
                            type="button"
                            onClick={handleClear}
                            className="absolute right-1 top-1 rounded-full bg-theme-surface/90 p-0.5 text-theme-ink shadow-sm ring-1 ring-theme-border"
                            aria-label="Remove image"
                        >
                            <X className="h-3.5 w-3.5" />
                        </button>
                    )}
                </div>
                <div className="min-w-0 flex-1">
                    <input
                        ref={fileRef}
                        type="file"
                        accept={IMAGE_UPLOAD.ACCEPT}
                        onChange={handleChange}
                        className="block w-full text-sm text-theme-ink file:mr-3 file:rounded-lg file:border-0 file:bg-theme-bg file:px-3 file:py-2 file:text-sm file:font-medium file:text-theme-ink hover:file:bg-theme-border/40"
                    />
                </div>
            </div>
        </Field>
    );
}
