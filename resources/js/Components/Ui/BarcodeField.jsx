import Button from '@/Components/Ui/Button';
import Input, { Field } from '@/Components/Ui/Input';
import { ScanBarcode, Sparkles } from 'lucide-react';
import { useRef, useState } from 'react';

function ean13CheckDigit(digits12) {
    let sum = 0;
    for (let i = 0; i < 12; i++) {
        const n = Number(digits12[i]);
        sum += i % 2 === 0 ? n : n * 3;
    }
    return String((10 - (sum % 10)) % 10);
}

export function generateEan13() {
    let body = '';
    for (let i = 0; i < 12; i++) {
        body += String(Math.floor(Math.random() * 10));
    }
    return body + ean13CheckDigit(body);
}

/**
 * Barcode input with Generate + Scan (USB/keyboard wedge focus, camera when available).
 */
export default function BarcodeField({
    label = 'Barcode',
    value,
    onChange,
    error,
    hint,
    disabled = false,
}) {
    const inputRef = useRef(null);
    const [scanHint, setScanHint] = useState('');
    const [scanning, setScanning] = useState(false);
    const videoRef = useRef(null);
    const streamRef = useRef(null);
    const rafRef = useRef(null);

    const stopCamera = () => {
        if (rafRef.current) {
            cancelAnimationFrame(rafRef.current);
            rafRef.current = null;
        }
        if (streamRef.current) {
            streamRef.current.getTracks().forEach((t) => t.stop());
            streamRef.current = null;
        }
        setScanning(false);
    };

    const startScan = async () => {
        setScanHint('');
        inputRef.current?.focus();
        inputRef.current?.select();

        if (!('BarcodeDetector' in window) || !navigator.mediaDevices?.getUserMedia) {
            setScanHint('Scanner ready — use a USB/Bluetooth scanner or type the code.');
            return;
        }

        try {
            const stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'environment' },
            });
            streamRef.current = stream;
            setScanning(true);
            setScanHint('Point the camera at a barcode.');

            // Wait for video element
            requestAnimationFrame(async () => {
                const video = videoRef.current;
                if (!video) return;
                video.srcObject = stream;
                await video.play();

                const detector = new window.BarcodeDetector({
                    formats: ['ean_13', 'ean_8', 'code_128', 'qr_code', 'upc_a', 'upc_e'],
                });

                const tick = async () => {
                    if (!videoRef.current || videoRef.current.readyState < 2) {
                        rafRef.current = requestAnimationFrame(tick);
                        return;
                    }
                    try {
                        const codes = await detector.detect(videoRef.current);
                        if (codes?.[0]?.rawValue) {
                            onChange(codes[0].rawValue);
                            stopCamera();
                            setScanHint('Barcode captured.');
                            return;
                        }
                    } catch {
                        // keep scanning
                    }
                    rafRef.current = requestAnimationFrame(tick);
                };
                rafRef.current = requestAnimationFrame(tick);
            });
        } catch {
            setScanHint('Camera unavailable — use a USB scanner or type the code.');
            setScanning(false);
        }
    };

    return (
        <Field label={label} error={error} hint={hint || scanHint || undefined}>
            <div className="flex gap-2">
                <Input
                    ref={inputRef}
                    value={value || ''}
                    onChange={(e) => onChange(e.target.value)}
                    disabled={disabled}
                    error={!!error}
                    placeholder="Scan or enter barcode"
                    className="flex-1"
                />
                <Button
                    type="button"
                    variant="secondary"
                    disabled={disabled}
                    onClick={() => onChange(generateEan13())}
                    title="Generate barcode"
                >
                    <Sparkles className="h-4 w-4" strokeWidth={2.25} />
                    Generate
                </Button>
                <Button
                    type="button"
                    variant="secondary"
                    disabled={disabled}
                    onClick={scanning ? stopCamera : startScan}
                    title="Scan barcode"
                >
                    <ScanBarcode className="h-4 w-4" strokeWidth={2.25} />
                    {scanning ? 'Stop' : 'Scan'}
                </Button>
            </div>
            {scanning && (
                <div className="mt-2 overflow-hidden rounded-lg border border-theme-border bg-black">
                    <video ref={videoRef} className="max-h-48 w-full object-cover" muted playsInline />
                </div>
            )}
        </Field>
    );
}
