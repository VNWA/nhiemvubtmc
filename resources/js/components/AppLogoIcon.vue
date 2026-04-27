<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <div>
        <img src="/images/logo-blue.png" alt="SJC" class="size-8" />
    </div>
</template>
