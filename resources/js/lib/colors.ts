/**
 * Color helpers used to assign a random, visually balanced color pair
 * (background + foreground text) to event-room result options.
 *
 * The background is sampled in HSL with a clamped saturation/lightness so
 * results stay legible, then we pick black or white text based on contrast.
 */

function clamp(value: number, min: number, max: number): number {
    return Math.max(min, Math.min(max, value));
}

function hslToRgb(h: number, s: number, l: number): [number, number, number] {
    const sNorm = clamp(s, 0, 100) / 100;
    const lNorm = clamp(l, 0, 100) / 100;
    const c = (1 - Math.abs(2 * lNorm - 1)) * sNorm;
    const hh = (((h % 360) + 360) % 360) / 60;
    const x = c * (1 - Math.abs((hh % 2) - 1));

    let r1 = 0;
    let g1 = 0;
    let b1 = 0;
    if (hh >= 0 && hh < 1) {
        r1 = c;
        g1 = x;
    } else if (hh < 2) {
        r1 = x;
        g1 = c;
    } else if (hh < 3) {
        g1 = c;
        b1 = x;
    } else if (hh < 4) {
        g1 = x;
        b1 = c;
    } else if (hh < 5) {
        r1 = x;
        b1 = c;
    } else {
        r1 = c;
        b1 = x;
    }

    const m = lNorm - c / 2;

    return [Math.round((r1 + m) * 255), Math.round((g1 + m) * 255), Math.round((b1 + m) * 255)];
}

function toHex(channel: number): string {
    return clamp(Math.round(channel), 0, 255).toString(16).padStart(2, '0');
}

function rgbToHex(r: number, g: number, b: number): string {
    return `#${toHex(r)}${toHex(g)}${toHex(b)}`;
}

/**
 * Relative luminance per WCAG; used to decide on white vs black text.
 */
function relativeLuminance(r: number, g: number, b: number): number {
    const [rl, gl, bl] = [r, g, b].map((v) => {
        const c = v / 255;
        return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
    });
    return 0.2126 * rl + 0.7152 * gl + 0.0722 * bl;
}

export type ColorPair = { bg_color: string; text_color: string };

/**
 * Returns a fresh background/text color pair. The background uses random hue
 * with mid saturation/lightness so it pops without being blinding, and the
 * text is set to white or near-black depending on background luminance.
 */
export function randomColorPair(): ColorPair {
    const hue = Math.floor(Math.random() * 360);
    const saturation = 55 + Math.floor(Math.random() * 30);
    const lightness = 38 + Math.floor(Math.random() * 22);

    const [r, g, b] = hslToRgb(hue, saturation, lightness);
    const bg = rgbToHex(r, g, b);
    const text = relativeLuminance(r, g, b) > 0.5 ? '#1f1f1f' : '#ffffff';

    return { bg_color: bg, text_color: text };
}
