<script setup lang="ts">
defineOptions({ inheritAttrs: false });

withDefaults(
    defineProps<{
        for?: string;
        required?: boolean;
    }>(),
    {
        required: false,
    },
);
</script>

<template>
    <label :for="$props.for" class="c-label" :class="{ 'is-required': required }" v-bind="$attrs">
        <slot />
    </label>
</template>
