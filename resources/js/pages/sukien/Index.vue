<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ChevronRight } from 'lucide-vue-next';
import SukienEventRoomController from '@/actions/App/Http/Controllers/Sukien/SukienEventRoomController';

type Room = { id: number; name: string; slug: string; avatar_url: string | null };

defineProps<{
    rooms: Room[];
}>();
</script>

<template>

    <Head title="Sự kiện" />
    <div class="px-3 pb-8 pt-1">
        <h2 class="mb-1 text-center text-base font-bold text-stone-800">Danh sách sự kiện</h2>
        <p class="mb-4 text-center text-sm text-stone-500">Chọn sự kiện để tham gia</p>
        <ul v-if="rooms.length" class="space-y-2">
            <li v-for="r in rooms" :key="r.id">
                <Link :href="SukienEventRoomController.show.url(r.slug)"
                    class="flex items-center justify-between gap-3 rounded-2xl border border-stone-200 bg-white p-3 shadow-sm transition active:scale-[0.99] active:bg-amber-50/80">
                    <div class="flex items-center gap-3">
                        <div
                            class="flex size-12 shrink-0 items-center justify-center overflow-hidden rounded-full border border-stone-200 bg-stone-100 text-xs text-stone-500">
                            <img v-if="r.avatar_url" :src="r.avatar_url" :alt="r.name" class="size-full object-cover" />
                            <span v-else>{{ r.name.charAt(0).toUpperCase() }}</span>
                        </div>
                        <span class="font-medium text-stone-900">{{ r.name }}</span>
                    </div>
                    <ChevronRight class="size-5 shrink-0 text-amber-700" />
                </Link>
            </li>
        </ul>
        <p v-else
            class="rounded-2xl border border-dashed border-stone-200 bg-amber-50/50 p-6 text-center text-sm text-stone-600">
            Chưa có sự kiện nào. Vui lòng quay lại sau.
        </p>
        <div class="mt-6 text-center">
            <Link href="/" class="text-sm text-amber-800 underline decoration-amber-400 underline-offset-2">
                ← Về trang chủ
            </Link>
        </div>
    </div>
</template>
