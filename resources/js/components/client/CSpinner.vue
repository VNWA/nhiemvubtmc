<script setup lang="ts">
withDefaults(
    defineProps<{
        size?: number;
    }>(),
    { size: 16 },
);
</script>

<template>
    <span
        class="c-spinner"
        :style="{ width: `${size}px`, height: `${size}px` }"
        role="status"
        aria-label="Đang xử lý"
    />
</template>
