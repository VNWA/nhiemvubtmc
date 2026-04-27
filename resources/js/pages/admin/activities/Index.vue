<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ScrollText, Search, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import ActivityLogController from '@/actions/App/Http/Controllers/Admin/ActivityLogController';
import AdminListReloadButton from '@/components/admin/AdminListReloadButton.vue';
import Heading from '@/components/Heading.vue';
import Pagination from '@/components/Pagination.vue';
import type {PaginationLink} from '@/components/Pagination.vue';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type UserRef = { id: number; name: string; username: string } | null;

type Log = {
    id: number;
    action: string;
    action_label: string;
    description: string | null;
    meta: Record<string, unknown> | null;
    ip: string | null;
    created_at: string | null;
    actor: UserRef;
    target: UserRef;
};

type Paginator = {
    data: Log[];
    current_page: number;
    from: number | null;
    to: number | null;
    total: number;
    per_page: number;
    last_page: number;
    links: PaginationLink[];
};

const props = defineProps<{
    logs: Paginator;
    filters: {
        action: string;
        actor_id: number | null;
        target_user_id: number | null;
        q: string;
        date_from: string;
        date_to: string;
        per_page: number;
    };
    actionOptions: { value: string; label: string }[];
}>();

const actionFilter = ref<string>(props.filters.action !== '' ? props.filters.action : '__all');
const search = ref<string>(props.filters.q ?? '');
const dateFrom = ref<string>(props.filters.date_from ?? '');
const dateTo = ref<string>(props.filters.date_to ?? '');

let debounceTimer: ReturnType<typeof setTimeout> | null = null;

function pushFilters() {
    const params: Record<string, string | number> = {};
    const cleaned = search.value.trim();

    if (cleaned !== '') {
        params.q = cleaned;
    }

    if (actionFilter.value && actionFilter.value !== '__all') {
        params.action = actionFilter.value;
    }

    if (dateFrom.value) {
        params.date_from = dateFrom.value;
    }

    if (dateTo.value) {
        params.date_to = dateTo.value;
    }

    router.get(ActivityLogController.index.url(), params, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['logs', 'filters'],
    });
}

watch(search, () => {
    if (debounceTimer) {
        clearTimeout(debounceTimer);
    }

    debounceTimer = setTimeout(pushFilters, 300);
});

watch([actionFilter, dateFrom, dateTo], () => pushFilters());

const hasFilters = computed(() => {
    const q = search.value.trim();
    const hasAction = actionFilter.value !== '' && actionFilter.value !== '__all';
    return !!(q || hasAction || dateFrom.value || dateTo.value);
});

function resetFilters() {
    search.value = '';
    actionFilter.value = '__all';
    dateFrom.value = '';
    dateTo.value = '';
}

function actionBadgeClass(action: string): string {
    if (action.startsWith('user.locked') || action.startsWith('user.deleted')) {
        return 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300';
    }

    if (action.startsWith('user.unlocked')) {
        return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300';
    }

    if (action.startsWith('wallet.')) {
        return 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300';
    }

    if (action.startsWith('user.created')) {
        return 'bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300';
    }

    return 'bg-secondary text-secondary-foreground';
}

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Lịch sử thao tác', href: ActivityLogController.index.url() }],
    },
});
</script>

<template>

    <Head title="Lịch sử thao tác" />

    <div class="flex flex-col gap-5 p-4">
        <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
            <Heading variant="small" title="Lịch sử thao tác"
                description="Toàn bộ thao tác do admin / nhân viên / hệ thống thực hiện." />
            <AdminListReloadButton :only="['logs', 'filters', 'actionOptions']" />
        </div>

        <div class="rounded-xl border border-border/60 bg-card p-4 shadow-sm dark:border-sidebar-border">
            <div
                class="grid grid-cols-1 gap-4 sm:grid-cols-2 sm:items-end lg:grid-cols-4 lg:items-end"
            >
                <div class="flex min-w-0 flex-col gap-1.5">
                    <label class="whitespace-nowrap text-[11px] font-medium leading-none text-muted-foreground"
                        for="activity-search">
                        Tìm kiếm
                    </label>
                    <div class="relative w-full min-w-0">
                        <Search
                            class="pointer-events-none absolute left-3 top-1/2 z-10 size-4 -translate-y-1/2 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <Input
                            id="activity-search"
                            v-model="search"
                            type="text"
                            inputmode="search"
                            enterkeyhint="search"
                            placeholder="Tên, username, mô tả…"
                            autocomplete="off"
                            :class="[
                                'h-10 w-full min-w-0 pl-9 pr-10',
                                'placeholder:text-muted-foreground/80',
                            ]"
                        />
                        <button
                            v-show="search !== ''"
                            type="button"
                            class="absolute right-1.5 top-1/2 z-10 flex size-7 -translate-y-1/2 items-center justify-center rounded-md text-muted-foreground transition hover:bg-muted hover:text-foreground"
                            aria-label="Xóa tìm kiếm"
                            @click="search = ''"
                        >
                            <X class="size-4" />
                        </button>
                    </div>
                </div>
                <div class="flex min-w-0 flex-col gap-1.5">
                    <span class="text-[11px] font-medium leading-none text-muted-foreground">Loại thao tác</span>
                    <Select v-model="actionFilter">
                        <SelectTrigger class="h-10! w-full min-w-0" aria-label="Bộ lọc loại thao tác">
                            <SelectValue placeholder="Chọn loại" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="__all">Tất cả thao tác</SelectItem>
                            <SelectItem v-for="opt in actionOptions" :key="opt.value" :value="opt.value">
                                {{ opt.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="flex min-w-0 flex-col gap-1.5">
                    <label class="text-[11px] font-medium leading-none text-muted-foreground" for="date-from">Từ
                        ngày</label>
                    <Input
                        id="date-from"
                        v-model="dateFrom"
                        type="date"
                        :max="dateTo || undefined"
                        class="h-10 w-full min-w-0"
                    />
                </div>
                <div class="flex min-w-0 flex-col gap-1.5">
                    <label class="text-[11px] font-medium leading-none text-muted-foreground" for="date-to">Đến
                        ngày</label>
                    <Input
                        id="date-to"
                        v-model="dateTo"
                        type="date"
                        :min="dateFrom || undefined"
                        class="h-10 w-full min-w-0"
                    />
                </div>
            </div>
            <div
                class="mt-4 flex flex-col gap-2 border-t border-border/50 pt-3 sm:flex-row sm:items-center sm:justify-between dark:border-sidebar-border/80"
            >
                <span class="text-xs text-muted-foreground">
                    Tổng
                    <span class="font-semibold text-foreground">{{ logs.total }}</span> bản ghi
                </span>
                <button
                    v-if="hasFilters"
                    type="button"
                    class="inline-flex shrink-0 items-center justify-center gap-1.5 self-start rounded-md border border-border/60 bg-muted/40 px-3 py-1.5 text-xs font-medium text-foreground/90 transition hover:bg-muted sm:self-auto dark:border-sidebar-border"
                    @click="resetFilters"
                >
                    <X class="size-3.5" />
                    Xóa bộ lọc
                </button>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-border/60 bg-card shadow-sm dark:border-sidebar-border">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[900px] text-left text-sm">
                    <thead
                        class="border-b border-border/60 bg-muted/50 text-xs uppercase tracking-wide text-muted-foreground dark:border-sidebar-border">
                        <tr>
                            <th class="p-3">Thời gian</th>
                            <th class="p-3">Người thực hiện</th>
                            <th class="p-3">Hành động</th>
                            <th class="p-3">Đối tượng</th>
                            <th class="p-3">Chi tiết</th>
                            <th class="p-3">IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="logs.data.length === 0">
                            <td colspan="6" class="px-3 py-10 text-center text-sm text-muted-foreground">
                                <ScrollText class="mx-auto mb-2 size-6 opacity-40" />
                                Chưa có thao tác nào.
                            </td>
                        </tr>
                        <tr v-for="log in logs.data" :key="log.id"
                            class="border-b border-border/40 transition hover:bg-muted/40 last:border-0 dark:border-sidebar-border/60">
                            <td class="p-3 text-xs text-muted-foreground">{{ log.created_at ?? '—' }}</td>
                            <td class="p-3 text-xs">
                                <div v-if="log.actor" class="flex flex-col leading-tight">
                                    <span class="text-foreground">{{ log.actor.name }}</span>
                                    <span class="font-mono text-[11px] text-muted-foreground">@{{ log.actor.username }}</span>
                                </div>
                                <span v-else class="italic text-muted-foreground">Hệ thống</span>
                            </td>
                            <td class="p-3">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold"
                                    :class="actionBadgeClass(log.action)">
                                    {{ log.action_label }}
                                </span>
                            </td>
                            <td class="p-3 text-xs">
                                <div v-if="log.target" class="flex flex-col leading-tight">
                                    <span class="text-foreground">{{ log.target.name }}</span>
                                    <span class="font-mono text-[11px] text-muted-foreground">@{{ log.target.username }}</span>
                                </div>
                                <span v-else class="italic text-muted-foreground">—</span>
                            </td>
                            <td class="p-3 text-xs text-foreground/80">{{ log.description ?? '—' }}</td>
                            <td class="p-3 font-mono text-[11px] text-muted-foreground">{{ log.ip || '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Pagination :meta="logs" :only="['logs', 'filters', 'actionOptions']" item-label="bản ghi" />
        </div>
    </div>
</template>
