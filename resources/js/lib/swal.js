import Swal from 'sweetalert2';

const classes = {
    popup: 'ora-swal-popup',
    title: 'ora-swal-title',
    htmlContainer: 'ora-swal-html',
    confirmButton: 'ora-swal-confirm',
    cancelButton: 'ora-swal-cancel',
    input: 'ora-swal-input',
    actions: 'ora-swal-actions',
    timerProgressBar: 'ora-swal-timer',
};

function theme() {
    return document.documentElement.classList.contains('dark') ? 'dark' : 'light';
}

function fire(options) {
    return Swal.fire({
        theme: theme(),
        buttonsStyling: false,
        reverseButtons: true,
        customClass: classes,
        confirmButtonText: 'OK',
        ...options,
        customClass: {
            ...classes,
            ...(options.customClass || {}),
        },
    });
}

export function toast(message, icon = 'success') {
    return fire({
        toast: true,
        position: 'top-end',
        icon,
        title: message,
        showConfirmButton: false,
        timer: 2800,
        timerProgressBar: true,
    });
}

export function success(title, text = '') {
    return fire({
        icon: 'success',
        title,
        text,
        confirmButtonText: 'OK',
    });
}

export function error(title, text = '') {
    return fire({
        icon: 'error',
        title,
        text,
        confirmButtonText: 'OK',
    });
}

export function info(title, text = '') {
    return fire({
        icon: 'info',
        title,
        text,
        confirmButtonText: 'OK',
    });
}

/**
 * @param {{
 *   title: string,
 *   text?: string,
 *   confirmText?: string,
 *   cancelText?: string,
 *   destructive?: boolean,
 * }} options
 * @returns {Promise<boolean>}
 */
export async function confirm({
    title,
    text = '',
    confirmText = 'Confirmer',
    cancelText = 'Annuler',
    destructive = false,
} = {}) {
    const result = await fire({
        icon: destructive ? 'warning' : 'question',
        title,
        text,
        showCancelButton: true,
        focusCancel: destructive,
        confirmButtonText: confirmText,
        cancelButtonText: cancelText,
        customClass: {
            confirmButton: destructive ? 'ora-swal-confirm ora-swal-confirm-danger' : 'ora-swal-confirm',
        },
    });

    return result.isConfirmed;
}

/**
 * @param {{
 *   title: string,
 *   text?: string,
 *   inputLabel?: string,
 *   inputPlaceholder?: string,
 *   inputValue?: string,
 *   confirmText?: string,
 *   cancelText?: string,
 *   expected?: string,
 *   destructive?: boolean,
 * }} options
 * @returns {Promise<string|null>}
 */
export async function prompt({
    title,
    text = '',
    inputLabel = '',
    inputPlaceholder = '',
    inputValue = '',
    confirmText = 'Confirmer',
    cancelText = 'Annuler',
    expected,
    destructive = false,
} = {}) {
    const result = await fire({
        icon: destructive ? 'warning' : 'question',
        title,
        text,
        input: 'text',
        inputLabel,
        inputPlaceholder,
        inputValue,
        showCancelButton: true,
        focusCancel: destructive,
        confirmButtonText: confirmText,
        cancelButtonText: cancelText,
        customClass: {
            confirmButton: destructive ? 'ora-swal-confirm ora-swal-confirm-danger' : 'ora-swal-confirm',
        },
        inputValidator: (value) => {
            const trimmed = (value ?? '').trim();
            if (!trimmed) {
                return 'Saisissez une valeur.';
            }
            if (expected !== undefined && value !== expected) {
                return `Tapez exactement « ${expected} » pour confirmer.`;
            }

            return undefined;
        },
    });

    if (!result.isConfirmed) {
        return null;
    }

    return typeof result.value === 'string' ? result.value : null;
}
