import jsQR from 'jsqr';

/**
 * Decode a QR code from an image File.
 * Tries the native BarcodeDetector first, then jsQR over several scales,
 * a center crop, and a contrast-stretched pass (helps glossy badge photos).
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

    // 2) jsQR over several renditions
    const draw = (w, h, sx, sy, sw, sh, boost) => {
        const canvas = document.createElement('canvas');
        canvas.width = w; canvas.height = h;
        const ctx = canvas.getContext('2d', { willReadFrequently: true });
        ctx.drawImage(bitmap, sx, sy, sw, sh, 0, 0, w, h);
        const img = ctx.getImageData(0, 0, w, h);
        if (boost) contrastStretch(img.data);
        return img;
    };

    const W = bitmap.width, H = bitmap.height;
    const attempts = [];
    for (const max of [2000, 1400, 1000, 700]) {
        const s = Math.min(1, max / Math.max(W, H));
        attempts.push([Math.round(W * s), Math.round(H * s), 0, 0, W, H, false]);
        attempts.push([Math.round(W * s), Math.round(H * s), 0, 0, W, H, true]);
    }
    // center crop (60%) — QR is usually mid-card
    const cw = Math.round(W * 0.6), ch = Math.round(H * 0.6);
    const cx = Math.round((W - cw) / 2), cy = Math.round((H - ch) / 2);
    for (const max of [1400, 900]) {
        const s = Math.min(1, max / Math.max(cw, ch));
        attempts.push([Math.round(cw * s), Math.round(ch * s), cx, cy, cw, ch, false]);
        attempts.push([Math.round(cw * s), Math.round(ch * s), cx, cy, cw, ch, true]);
    }

    for (const [w, h, sx, sy, sw, sh, boost] of attempts) {
        try {
            const img = draw(w, h, sx, sy, sw, sh, boost);
            const res = jsQR(img.data, w, h, { inversionAttempts: 'attemptBoth' });
            if (res && res.data && res.data.trim() !== '') return res.data.trim();
        } catch (e) { /* try next */ }
    }
    return null;
};

function contrastStretch(d) {
    let min = 255, max = 0;
    for (let i = 0; i < d.length; i += 4) {
        const g = (d[i] * 299 + d[i + 1] * 587 + d[i + 2] * 114) / 1000;
        if (g < min) min = g;
        if (g > max) max = g;
    }
    const range = Math.max(1, max - min);
    for (let i = 0; i < d.length; i += 4) {
        const g = (d[i] * 299 + d[i + 1] * 587 + d[i + 2] * 114) / 1000;
        const v = Math.max(0, Math.min(255, ((g - min) / range) * 255));
        d[i] = d[i + 1] = d[i + 2] = v;
    }
}
