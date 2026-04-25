<template>
    <div>

    </div>
</template>

<script lang="ts" setup>
import { onMounted } from 'vue';
import { login } from '@/routes';
import { router } from '@inertiajs/vue3';
onMounted(() => {
    router.visit(login());
});
</script>

<style></style>
