<script setup lang="ts">
import { computed, useTemplateRef } from 'vue';

defineOptions({ inheritAttrs: false });

const props = withDefaults(
    defineProps<{
        modelValue?: string | number | null;
        type?: string;
        size?: 'sm' | 'md';
        ariaInvalid?: boolean;
        hasTrailing?: boolean;
    }>(),
    {
        modelValue: undefined,
        type: 'text',
        size: 'md',
        ariaInvalid: false,
        hasTrailing: false,
    },
);

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
}>();

const inputRef = useTemplateRef<HTMLInputElement>('inputRef');

const classes = computed(() => {
    const cls = ['c-input'];
    if (props.size === 'sm') {
        cls.push('c-input-sm');
    }
    if (props.hasTrailing) {
        cls.push('has-trailing');
    }
    return cls;
});

function onInput(e: Event) {
    emit('update:modelValue', (e.target as HTMLInputElement).value);
}

defineExpose({
    focus: () => inputRef.value?.focus(),
    el: inputRef,
});
</script>

<template>
    <input
        ref="inputRef"
        :type="type"
        :class="classes"
        :value="modelValue ?? ''"
        :aria-invalid="ariaInvalid || undefined"
        v-bind="$attrs"
        @input="onInput"
    />
</template>
