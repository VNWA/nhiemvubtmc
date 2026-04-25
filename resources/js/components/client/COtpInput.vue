<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        modelValue?: string;
        length?: number;
        disabled?: boolean;
    }>(),
    {
        modelValue: '',
        length: 6,
        disabled: false,
    },
);

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
}>();

const inputs = ref<HTMLInputElement[]>([]);

const slots = computed(() =>
    Array.from({ length: props.length }, (_, i) => props.modelValue[i] ?? ''),
);

function setRef(idx: number) {
    return (el: Element | null) => {
        if (el instanceof HTMLInputElement) {
            inputs.value[idx] = el;
        }
    };
}

function emitFromSlots(values: string[]) {
    emit('update:modelValue', values.join(''));
}

async function focusIndex(idx: number) {
    await nextTick();
    const el = inputs.value[idx];
    if (el) {
        el.focus();
        el.select();
    }
}

function onInput(idx: number, e: Event) {
    const target = e.target as HTMLInputElement;
    const raw = target.value.replace(/\D+/g, '');
    if (!raw) {
        const next = slots.value.slice();
        next[idx] = '';
        emitFromSlots(next);
        return;
    }
    const next = slots.value.slice();
    let cursor = idx;
    for (const ch of raw) {
        if (cursor >= props.length) {
            break;
        }
        next[cursor] = ch;
        cursor += 1;
    }
    emitFromSlots(next);
    if (cursor < props.length) {
        focusIndex(cursor);
    } else {
        focusIndex(props.length - 1);
    }
}

function onKeydown(idx: number, e: KeyboardEvent) {
    if (e.key === 'Backspace') {
        if (slots.value[idx]) {
            const next = slots.value.slice();
            next[idx] = '';
            emitFromSlots(next);
        } else if (idx > 0) {
            const next = slots.value.slice();
            next[idx - 1] = '';
            emitFromSlots(next);
            focusIndex(idx - 1);
        }
        e.preventDefault();
    } else if (e.key === 'ArrowLeft' && idx > 0) {
        focusIndex(idx - 1);
        e.preventDefault();
    } else if (e.key === 'ArrowRight' && idx < props.length - 1) {
        focusIndex(idx + 1);
        e.preventDefault();
    }
}

function onPaste(idx: number, e: ClipboardEvent) {
    const text = (e.clipboardData?.getData('text') ?? '').replace(/\D+/g, '');
    if (!text) {
        return;
    }
    e.preventDefault();
    const next = slots.value.slice();
    let cursor = idx;
    for (const ch of text) {
        if (cursor >= props.length) {
            break;
        }
        next[cursor] = ch;
        cursor += 1;
    }
    emitFromSlots(next);
    focusIndex(Math.min(cursor, props.length - 1));
}

watch(
    () => props.modelValue,
    () => {
        // noop — slots is computed from modelValue
    },
);

defineExpose({
    focus: () => focusIndex(0),
});
</script>

<template>
    <div class="c-otp">
        <input
            v-for="i in length"
            :key="i"
            :ref="setRef(i - 1)"
            type="text"
            inputmode="numeric"
            autocomplete="one-time-code"
            :disabled="disabled"
            :value="slots[i - 1]"
            class="c-otp-slot"
            maxlength="1"
            @input="onInput(i - 1, $event)"
            @keydown="onKeydown(i - 1, $event)"
            @paste="onPaste(i - 1, $event)"
        />
    </div>
</template>

<style scoped>
.c-otp {
    display: inline-flex;
    gap: 0.5rem;
    justify-content: center;
}

.c-otp-slot {
    width: 2.5rem;
    height: 3rem;
    text-align: center;
    border: 1.5px solid var(--border, #dbe4ed);
    border-radius: 0.5rem;
    background: #ffffff;
    color: var(--text-body, #102a43);
    font-size: 1.125rem;
    font-weight: 700;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    transition: border-color 150ms ease, box-shadow 150ms ease;
    outline: none;
}

.c-otp-slot:focus {
    border-color: var(--primary-1, #0d4f9e);
    box-shadow: 0 0 0 3px rgba(13, 79, 158, 0.16);
}

.c-otp-slot:disabled {
    background: #f8fafc;
    color: var(--text-muted, #5a6b7e);
    cursor: not-allowed;
}
</style>
