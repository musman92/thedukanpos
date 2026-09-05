import { ChevronDown, FileText, Printer, Receipt } from 'lucide-react';
import { createPortal } from 'react-dom';
import { useCallback, useEffect, useId, useRef, useState } from 'react';

const menuLinkClass =
    'flex w-full items-center gap-2 px-4 py-2 text-start text-sm leading-5 text-gray-700 transition duration-150 ease-in-out hover:bg-gray-100 focus:bg-gray-100 focus:outline-none';

function MenuLink({ href, icon: Icon, children, onNavigate }) {
    return (
        <a
            href={href}
            target="_blank"
            rel="noreferrer"
            className={menuLinkClass}
            onClick={onNavigate}
        >
            <Icon className="h-4 w-4 shrink-0 opacity-70" />
            {children}
        </a>
    );
}

function usePrintMenuPosition(open, triggerRef) {
    const [style, setStyle] = useState(null);

    const update = useCallback(() => {
        const el = triggerRef.current;
        if (!el) {
            return;
        }

        const rect = el.getBoundingClientRect();
        const menuWidth = 176;
        const gap = 4;
        let left = rect.right - menuWidth;

        if (left < 8) {
            left = 8;
        }
        if (left + menuWidth > window.innerWidth - 8) {
            left = window.innerWidth - menuWidth - 8;
        }

        setStyle({
            top: rect.bottom + gap,
            left,
            width: menuWidth,
        });
    }, [triggerRef]);

    useEffect(() => {
        if (!open) {
            setStyle(null);
            return undefined;
        }

        update();

        window.addEventListener('resize', update);
        window.addEventListener('scroll', update, true);

        return () => {
            window.removeEventListener('resize', update);
            window.removeEventListener('scroll', update, true);
        };
    }, [open, update]);

    return style;
}

export default function PrintFormatMenu({
    receiptHref,
    invoiceHref,
    invoiceLabel = 'Invoice (PDF)',
    variant = 'icon',
}) {
    const [open, setOpen] = useState(false);
    const triggerRef = useRef(null);
    const menuId = useId();
    const menuStyle = usePrintMenuPosition(open, triggerRef);

    useEffect(() => {
        if (!open) {
            return undefined;
        }

        const onKeyDown = (event) => {
            if (event.key === 'Escape') {
                setOpen(false);
            }
        };

        document.addEventListener('keydown', onKeyDown);

        return () => document.removeEventListener('keydown', onKeyDown);
    }, [open]);

    const close = () => setOpen(false);

    const triggerButton =
        variant === 'button' ? (
            <button
                ref={triggerRef}
                type="button"
                aria-haspopup="menu"
                aria-expanded={open}
                aria-controls={menuId}
                onClick={() => setOpen((value) => !value)}
                className="inline-flex h-9 items-center gap-1.5 rounded-lg bg-[var(--color-primary)] px-3 text-sm font-semibold text-[var(--color-on-primary)] hover:bg-[var(--color-primary-hover)]"
            >
                <Printer className="h-4 w-4" />
                Print
                <ChevronDown className="h-4 w-4 opacity-80" />
            </button>
        ) : (
            <button
                ref={triggerRef}
                type="button"
                aria-haspopup="menu"
                aria-expanded={open}
                aria-controls={menuId}
                onClick={() => setOpen((value) => !value)}
                className="inline-flex rounded-md p-1.5 text-theme-ink-muted hover:bg-theme-bg hover:text-theme-ink"
                title="Print"
            >
                <Printer className="h-4 w-4" />
            </button>
        );

    const menu =
        open &&
        menuStyle &&
        createPortal(
            <>
                <div
                    className="fixed inset-0 z-[200]"
                    aria-hidden="true"
                    onClick={close}
                />
                <div
                    id={menuId}
                    role="menu"
                    className="fixed z-[201] rounded-md bg-white py-1 shadow-lg ring-1 ring-black/5"
                    style={{
                        top: menuStyle.top,
                        left: menuStyle.left,
                        width: menuStyle.width,
                    }}
                >
                    <MenuLink href={receiptHref} icon={Receipt} onNavigate={close}>
                        POS receipt
                    </MenuLink>
                    <MenuLink href={invoiceHref} icon={FileText} onNavigate={close}>
                        {invoiceLabel}
                    </MenuLink>
                </div>
            </>,
            document.body,
        );

    return (
        <>
            {triggerButton}
            {menu}
        </>
    );
}
