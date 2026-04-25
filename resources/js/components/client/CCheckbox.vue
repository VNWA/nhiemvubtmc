<script setup lang="ts">
defineOptions({ inheritAttrs: false });

const props = withDefaults(
    defineProps<{
        modelValue?: boolean;
        value?: string | number;
    }>(),
    {
        modelValue: undefined,
        value: undefined,
    },
);

const emit = defineEmits<{
    (e: 'update:modelValue', value: boolean): void;
}>();

function onChange(e: Event) {
    emit('update:modelValue', (e.target as HTMLInputElement).checked);
}
</script>

<template>
    <input
        type="checkbox"
        class="c-checkbox"
        :checked="!!modelValue"
        :value="value"
        v-bind="$attrs"
        @change="onChange"
    />
</template>
