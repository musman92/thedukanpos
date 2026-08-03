/**
 * POS keyboard helpers — function keys work even while search is focused.
 */

export function isEditableTarget(target) {
    if (!target || !(target instanceof HTMLElement)) return false;
    if (target.isContentEditable) return true;

    const tag = target.tagName;
    if (tag === 'TEXTAREA' || tag === 'SELECT') return true;

    if (tag === 'INPUT') {
        const type = String(target.getAttribute('type') || 'text').toLowerCase();
        return ![
            'button',
            'submit',
            'checkbox',
            'radio',
            'file',
            'reset',
            'hidden',
            'range',
            'color',
        ].includes(type);
    }

    return false;
}

export function isConfirmDialogOpen() {
    return Boolean(document.querySelector('.swal2-container'));
}

export const POS_HOTKEYS = [
    { keys: 'F2 /', action: 'Focus search' },
    { keys: 'F3', action: 'Save for later' },
    { keys: 'F4', action: 'Pay' },
    { keys: 'F6', action: 'Saved bills' },
    { keys: 'F7', action: 'Today' },
    { keys: 'F8', action: 'Delivery (if enabled)' },
    { keys: 'F9', action: 'Clear cart' },
    { keys: 'Esc', action: 'Close panel' },
    { keys: '?', action: 'Show shortcuts' },
];
