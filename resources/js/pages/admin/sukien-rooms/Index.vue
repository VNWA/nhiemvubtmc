<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import EventRoomController from '@/actions/App/Http/Controllers/Admin/EventRoomController';
import SukienEventRoomController from '@/actions/App/Http/Controllers/Sukien/SukienEventRoomController';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';

type Row = {
    id: number;
    name: string;
    slug: string;
    avatar_url: string | null;
    is_active: boolean;
    options_count: number;
};

defineProps<{
    rooms: Row[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Sự kiện (phòng)', href: EventRoomController.index.url() },
        ],
    },
});
</script>

<template>
    <Head title="Phòng sự kiện" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
            <Heading title="Phòng sự kiện" description="Tạo phòng, cấu hình các mặt kết quả, điều hành kỳ tại trang phòng" />
            <div class="flex flex-wrap gap-2">
                <Button as-child>
                    <Link :href="EventRoomController.create.url()">Tạo phòng</Link>
                </Button>
            </div>
        </div>

        <ul class="space-y-2">
            <li
                v-for="r in rooms"
                :key="r.id"
                class="flex flex-col gap-2 rounded-lg border p-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="flex items-center gap-3">
                    <div class="flex size-12 shrink-0 items-center justify-center overflow-hidden rounded-full border bg-muted">
                        <img v-if="r.avatar_url" :src="r.avatar_url" :alt="r.name" class="size-full object-cover" />
                        <span v-else class="text-xs text-muted-foreground">N/A</span>
                    </div>
                    <div>
                        <p class="font-medium">
                            <Link :href="EventRoomController.manage.url(r.id)" class="text-foreground hover:underline">
                                {{ r.name }}
                            </Link>
                        </p>
                        <p class="text-sm text-muted-foreground">/{{ r.slug }} · {{ r.options_count }} mặt cược · {{ r.is_active ? 'Đang bật' : 'Tắt' }}</p>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button as-child size="sm">
                        <Link :href="EventRoomController.manage.url(r.id)">Quản lí phòng</Link>
                    </Button>
                    <Button as-child variant="outline" size="sm">
                        <Link :href="SukienEventRoomController.show.url(r.slug)">Xem như user</Link>
                    </Button>
                    <Button as-child variant="secondary" size="sm">
                        <Link :href="EventRoomController.edit.url(r.id)">Sửa</Link>
                    </Button>
                </div>
            </li>
        </ul>
    </div>
</template>
