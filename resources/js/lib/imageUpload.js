/**
 * App-wide image upload limits.
 * Keep in sync with App\Services\ImageUploadService (MAX_UPLOAD_KB / MAX_EDGE).
 */
export const IMAGE_UPLOAD = {
    /** Max raw upload size in megabytes (before server compression). */
    MAX_UPLOAD_MB: 2,
    /** Max raw upload size in bytes. */
    MAX_UPLOAD_BYTES: 2 * 1024 * 1024,
    /** Longest edge after server resize (px) — for UI hints only. */
    MAX_EDGE: 800,
    /** Accept attribute for file inputs. */
    ACCEPT: 'image/jpeg,image/png,image/webp,image/gif',
};

export function imageUploadHint({
    optional = true,
    compressNote = `we resize to ${IMAGE_UPLOAD.MAX_EDGE}px and compress before saving`,
} = {}) {
    const prefix = optional ? 'Optional. ' : '';

    return `${prefix}Max ${IMAGE_UPLOAD.MAX_UPLOAD_MB} MB upload — ${compressNote}.`;
}

/**
 * @param {File|null|undefined} file
 * @returns {{ ok: true } | { ok: false, message: string }}
 */
export function validateImageFile(file) {
    if (!file) {
        return { ok: false, message: 'Please choose an image file.' };
    }

    if (file.size > IMAGE_UPLOAD.MAX_UPLOAD_BYTES) {
        return {
            ok: false,
            message: `Image must be at most ${IMAGE_UPLOAD.MAX_UPLOAD_MB} MB.`,
        };
    }

    return { ok: true };
}
