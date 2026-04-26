<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { RefreshCw } from 'lucide-vue-next';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';

const props = withDefaults(
    defineProps<{
        /**
         * Inertia `only` keys to refresh (cùng key với `Pagination` khi chuyển trang).
         */
        only: string[];
        label?: string;
    }>(),
    {
        label: 'Tải lại',
    },
);

const busy = ref(false);

function reload() {
    if (busy.value) {
        return;
    }

    busy.value = true;
    router.reload({
        only: props.only,
        onFinish: () => {
            busy.value = false;
        },
    });
}
</script>

<template>
    <Button
        type="button"
        variant="outline"
        size="sm"
        class="shrink-0"
        :disabled="busy"
        :title="label"
        :aria-label="label"
        @click="reload"
    >
        <RefreshCw
            class="size-4"
            :class="{ 'animate-spin': busy }"
        />
        <span class="ms-1.5 hidden min-[380px]:inline">{{ label }}</span>
    </Button>
</template>
