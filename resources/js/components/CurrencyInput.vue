<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';

/**
 * Tiny, dependency-free VND currency input.
 *
 * Keeps numeric-only state (`modelValue`), displays formatted thousands
 * separators live, and preserves caret position across reformat so
 * editing in the middle does not jump to the end.
 *
 * Non-digit keystrokes are stripped silently. A hidden `<input
 * name="...">` of type `number` is emitted when `name` is provided, so
 * it can be dropped into `<Form>` without extra plumbing.
 */
const props = withDefaults(
    defineProps<{
        modelValue: number;
        max?: number;
        min?: number;
        name?: string;
        id?: string;
        placeholder?: string;
        inputClass?: string;
        ariaInvalid?: boolean;
        disabled?: boolean;
        suffix?: string;
    }>(),
    {
        max: 2_000_000_000,
        min: 0,
        suffix: '₫',
        placeholder: '0',
        inputClass: '',
    },
);

const emit = defineEmits<{
    (e: 'update:modelValue', value: number): void;
}>();

const inputEl = ref<HTMLInputElement | null>(null);
const formatter = new Intl.NumberFormat('vi-VN');

const display = computed(() => (props.modelValue > 0 ? formatter.format(props.modelValue) : ''));

/** Count digits in a string up to position `end` (exclusive). */
function digitsBefore(str: string, end: number): number {
    let n = 0;
    const stop = Math.min(end, str.length);
    for (let i = 0; i < stop; i++) {
        const c = str.charCodeAt(i);
        if (c >= 48 && c <= 57) n++;
    }
    return n;
}

/** Given a desired number of leading digits, find the caret position in a formatted string. */
function caretForDigitIndex(formatted: string, digitIndex: number): number {
    if (digitIndex <= 0) return 0;
    let seen = 0;
    for (let i = 0; i < formatted.length; i++) {
        const c = formatted.charCodeAt(i);
        if (c >= 48 && c <= 57) {
            seen++;
            if (seen === digitIndex) return i + 1;
        }
    }
    return formatted.length;
}

function clamp(n: number): number {
    if (!Number.isFinite(n) || n < 0) return 0;
    return Math.min(Math.max(n, props.min), props.max);
}

async function onInput(e: Event) {
    const el = e.target as HTMLInputElement;
    const raw = el.value;
    const caret = el.selectionStart ?? raw.length;

    const digitCaret = digitsBefore(raw, caret);
    const digitsOnly = raw.replace(/\D+/g, '').replace(/^0+(?=\d)/, '');
    const parsed = digitsOnly === '' ? 0 : Number.parseInt(digitsOnly, 10) || 0;
    const next = clamp(parsed);

    emit('update:modelValue', next);

    // Re-format and restore caret against the formatted string.
    const formatted = next > 0 ? formatter.format(next) : '';
    el.value = formatted;

    await nextTick();
    const input = inputEl.value ?? el;
    const pos = caretForDigitIndex(formatted, digitCaret);
    try {
        input.setSelectionRange(pos, pos);
    } catch {
        /* browsers may throw if input type doesn't support selection */
    }
}

function onKeydown(e: KeyboardEvent) {
    // Allow common navigation / editing keys regardless.
    if (e.metaKey || e.ctrlKey || e.altKey) return;
    const allowed = [
        'Backspace', 'Delete', 'Tab', 'Enter', 'Escape',
        'ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown',
        'Home', 'End',
    ];
    if (allowed.includes(e.key)) return;
    // Single-character keys must be digits.
    if (e.key.length === 1 && !/\d/.test(e.key)) {
        e.preventDefault();
    }
}

function onPaste(e: ClipboardEvent) {
    const text = e.clipboardData?.getData('text') ?? '';
    if (!/\d/.test(text)) {
        e.preventDefault();
        return;
    }
    // Let the browser paste, our `onInput` handler will sanitize.
}

// Keep display in sync if the parent mutates `modelValue` programmatically.
watch(
    () => props.modelValue,
    (n) => {
        const el = inputEl.value;
        if (!el) return;
        const want = n > 0 ? formatter.format(n) : '';
        if (el.value !== want) {
            el.value = want;
        }
    },
);

defineExpose({ focus: () => inputEl.value?.focus() });
</script>

<template>
    <div class="currency-wrap">
        <input
            :id="id"
            ref="inputEl"
            type="text"
            inputmode="numeric"
            autocomplete="off"
            :placeholder="placeholder"
            :value="display"
            :disabled="disabled"
            :aria-invalid="ariaInvalid || undefined"
            :class="['currency-input', inputClass]"
            @input="onInput"
            @keydown="onKeydown"
            @paste="onPaste"
        />
        <span v-if="suffix" class="currency-suffix">{{ suffix }}</span>
        <input v-if="name" type="hidden" :name="name" :value="modelValue" />
    </div>
</template>

<style scoped>
.currency-wrap {
    position: relative;
    display: block;
    width: 100%;
}

.currency-input {
    display: block;
    width: 100%;
    height: 2.75rem;
    padding: 0 2.25rem 0 1rem;
    border: 1.5px solid rgb(231 229 228);
    border-radius: 0.625rem;
    background: white;
    color: rgb(28 25 23);
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace;
    font-size: 1rem;
    font-weight: 600;
    letter-spacing: 0.015em;
    text-align: right;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
    transition: border-color 150ms ease, box-shadow 150ms ease;
    outline: none;
}

.currency-input::placeholder {
    color: rgb(214 211 209);
    font-weight: 400;
}

.currency-input:hover:not(:disabled) {
    border-color: rgb(214 211 209);
}

.currency-input:focus {
    border-color: var(--primary-1, #0d4f9e);
    box-shadow: 0 0 0 3px rgba(13, 79, 158, 0.14);
}

.currency-input[aria-invalid='true'] {
    border-color: rgb(220 38 38);
    box-shadow: 0 0 0 3px rgb(254 226 226);
}

.currency-input:disabled {
    background: rgb(250 250 249);
    color: rgb(120 113 108);
    cursor: not-allowed;
}

.currency-suffix {
    pointer-events: none;
    position: absolute;
    inset-block: 0;
    right: 0.875rem;
    display: flex;
    align-items: center;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace;
    font-size: 1rem;
    font-weight: 700;
    color: rgb(168 162 158);
}
</style>
