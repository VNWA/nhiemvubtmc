<script setup lang="ts">
import { computed } from 'vue';

defineOptions({ inheritAttrs: false });

type Variant = 'primary' | 'gold' | 'outline' | 'ghost' | 'danger' | 'link';
type Size = 'sm' | 'md' | 'lg';

const props = withDefaults(
    defineProps<{
        variant?: Variant;
        size?: Size;
        type?: 'button' | 'submit' | 'reset';
        block?: boolean;
        disabled?: boolean;
    }>(),
    {
        variant: 'primary',
        size: 'md',
        type: 'button',
        block: false,
        disabled: false,
    },
);

const classes = computed(() => {
    const cls: string[] = ['btn', `btn-${props.variant}`];
    if (props.size === 'sm') {
        cls.push('btn-sm');
    } else if (props.size === 'lg') {
        cls.push('btn-lg');
    }
    if (props.block) {
        cls.push('btn-block');
    }
    return cls;
});
</script>

<template>
    <button :type="type" :disabled="disabled" :class="classes" v-bind="$attrs">
        <slot />
    </button>
</template>
