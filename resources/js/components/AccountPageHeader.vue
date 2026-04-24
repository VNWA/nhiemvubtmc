<script setup lang="ts">
import AccountController from '@/actions/App/Http/Controllers/Client/AccountController';
import { Link } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';

defineProps<{
    title: string;
    description?: string;
}>();
</script>

<template>
    <header class="flex items-start gap-2">
        <Link
            :href="AccountController.show.url()"
            class="mt-0.5 inline-flex size-8 shrink-0 items-center justify-center rounded-full bg-stone-100 text-stone-600 transition active:scale-95 hover:bg-stone-200"
            aria-label="Quay lại"
        >
            <ArrowLeft class="size-4" />
        </Link>
        <div class="min-w-0 flex-1">
            <h1 class="text-base font-bold leading-tight text-stone-800">{{ title }}</h1>
            <p v-if="description" class="mt-0.5 text-xs text-stone-500">{{ description }}</p>
        </div>
    </header>
</template>
