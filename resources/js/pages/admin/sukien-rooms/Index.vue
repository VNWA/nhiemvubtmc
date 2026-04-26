<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { Trash2 } from 'lucide-vue-next';
import EventRoomController from '@/actions/App/Http/Controllers/Admin/EventRoomController';
import SukienEventRoomController from '@/actions/App/Http/Controllers/Sukien/SukienEventRoomController';
import AdminListReloadButton from '@/components/admin/AdminListReloadButton.vue';
import Heading from '@/components/Heading.vue';
import Pagination from '@/components/Pagination.vue';
import type { PaginationLink } from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';

type Row = {
    id: number;
    name: string;
    slug: string;
    avatar_url: string | null;
    is_active: boolean;
    options_count: number;
};

type Paginator = {
    data: Row[];
    current_page: number;
    from: number | null;
    to: number | null;
    total: number;
    per_page: number;
    last_page: number;
    links: PaginationLink[];
};

defineProps<{
    rooms: Paginator;
}>();

function confirmDelete(name: string): boolean {
    return window.confirm(
        `Xóa phòng "${name}"?\n\nTất cả phiên, cược và cấu hình mặt cược sẽ bị xóa. Các cược trong phiên đang mở sẽ được hoàn tiền vào số dư người chơi.\n\nHành động này không thể hoàn tác.`,
    );
}

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
            <Heading title="Phòng sự kiện"
                description="Tạo phòng, cấu hình các mặt kết quả, điều hành phiên tại trang phòng" />
            <div class="flex flex-wrap items-center justify-end gap-2">
                <AdminListReloadButton :only="['rooms']" />
                <Button as-child>
                    <Link :href="EventRoomController.create.url()">Tạo phòng</Link>
                </Button>
            </div>
        </div>

        <div
            v-if="rooms.data.length === 0"
            class="rounded-lg border border-dashed border-border/60 p-8 text-center text-sm text-muted-foreground"
        >
            Chưa có phòng nào. Bấm &quot;Tạo phòng&quot; để bắt đầu.
        </div>

        <ul v-else class="space-y-2">
            <li v-for="r in rooms.data" :key="r.id"
                class="flex flex-col gap-2 rounded-lg border p-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-3">
                    <div
                        class="flex size-12 shrink-0 items-center justify-center overflow-hidden rounded-full border bg-muted">
                        <img v-if="r.avatar_url" :src="r.avatar_url" :alt="r.name" class="size-full object-cover" />
                        <span v-else class="text-xs text-muted-foreground">N/A</span>
                    </div>
                    <div>
                        <p class="font-medium">
                            <Link :href="EventRoomController.manage.url(r.id)" class="text-foreground hover:underline">
                                {{ r.name }}
                            </Link>
                        </p>
                        <p class="text-sm text-muted-foreground">/{{ r.slug }} · {{ r.options_count }} mặt cược · {{
                            r.is_active ? 'Đang bật' : 'Tắt' }}</p>
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
                    <Form v-bind="EventRoomController.destroy.form({ event_room: r.id })" @submit="(event: SubmitEvent) => {
                        if (!confirmDelete(r.name)) {
                            event.preventDefault();
                        }
                    }" #default="{ processing }">
                        <Button type="submit" variant="destructive" size="sm" :disabled="processing">
                            <Trash2 class="size-3.5" />
                            Xóa
                        </Button>
                    </Form>
                </div>
            </li>
        </ul>

        <Pagination
            v-if="rooms.total > 0"
            :meta="rooms"
            :only="['rooms']"
            item-label="phòng"
        />
    </div>
</template>
