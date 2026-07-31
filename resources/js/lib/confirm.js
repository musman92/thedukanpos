import Swal from 'sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';

/**
 * @param {object} options
 * @param {string} options.title
 * @param {string} [options.text]
 * @param {string} [options.confirmText]
 * @param {string} [options.cancelText]
 * @param {'warning'|'question'|'error'|'info'|'success'} [options.icon]
 * @returns {Promise<boolean>}
 */
export async function confirmAction({
    title,
    text = '',
    confirmText = 'Yes, delete it',
    cancelText = 'Cancel',
    icon = 'warning',
} = {}) {
    const result = await Swal.fire({
        title,
        text,
        icon,
        showCancelButton: true,
        focusCancel: true,
        reverseButtons: true,
        confirmButtonText: confirmText,
        cancelButtonText: cancelText,
        confirmButtonColor: 'var(--color-danger, #dc2626)',
        cancelButtonColor: 'var(--color-ink-soft, #78716c)',
        buttonsStyling: true,
    });

    return result.isConfirmed;
}

/**
 * @param {string} name
 * @param {string} [entity]
 * @returns {Promise<boolean>}
 */
export function confirmDelete(name, entity = 'item') {
    return confirmAction({
        title: `Delete ${entity}?`,
        text: `"${name}" will be permanently deleted. This cannot be undone.`,
        confirmText: 'Yes, delete it',
        cancelText: 'Cancel',
        icon: 'warning',
    });
}
