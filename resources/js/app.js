import jsQR from 'jsqr';

/**
 * Decode a QR code from an image File.
 * Uses the native BarcodeDetector when available, falls back to jsQR.
 * Returns the decoded string, or null when no QR is found.
 */
window.decodeQrFromImage = async function (file) {
    const bitmap = await createImageBitmap(file);

    // 1) native BarcodeDetector (Chrome / Android)
    try {
        if ('BarcodeDetector' in window) {
            const det = new BarcodeDetector({ formats: ['qr_code'] });
            const codes = await det.detect(bitmap);
            if (codes && codes.length && codes[0].rawValue) {
                return codes[0].rawValue.trim();
            }
        }
    } catch (e) { /* fall through to jsQR */ }

    // 2) jsQR fallback (downscale large images for speed)
    const max = 1600;
    let { width, height } = bitmap;
    const scale = Math.min(1, max / Math.max(width, height));
    width = Math.round(width * scale);
    height = Math.round(height * scale);
    const canvas = document.createElement('canvas');
    canvas.width = width; canvas.height = height;
    const ctx = canvas.getContext('2d', { willReadFrequently: true });
    ctx.drawImage(bitmap, 0, 0, width, height);
    const img = ctx.getImageData(0, 0, width, height);
    const res = jsQR(img.data, width, height, { inversionAttempts: 'attemptBoth' });
    return res && res.data ? res.data.trim() : null;
};
