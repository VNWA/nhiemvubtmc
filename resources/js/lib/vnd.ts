/** Định dạng hiển thị VND theo chuẩn vi-VN. */
export function formatVnd(amount: number): string {
    if (!Number.isFinite(amount) || amount < 0) {
        return '0 đ';
    }
    return new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 0 }).format(amount) + ' đ';
}

/** Bỏ ký tự không phải số, trả về số nguyên (0 nếu rỗng). */
export function parseVndInput(raw: string): number {
    const digits = raw.replace(/\D/g, '');

    if (digits === '') {
        return 0;
    }

    return Math.min(2_000_000_000, Number.parseInt(digits, 10) || 0);
}

/**
 * Dùng cho ô nhập: giữ số, hiển thị nhóm nghìn. `value` là chuỗi đang gõ, `onChange` nhận số nguyên.
 */
export function vndInputProps(valueVnd: number, onValue: (n: number) => void) {
    const display = valueVnd > 0 ? new Intl.NumberFormat('vi-VN').format(valueVnd) : '';

    return {
        display,
        onInput: (e: Event) => {
            const t = e.target as HTMLInputElement;
            onValue(parseVndInput(t.value));
        },
    };
}
