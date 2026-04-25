<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ScrollText } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import ActivityLogController from '@/actions/App/Http/Controllers/Admin/ActivityLogController';
import Heading from '@/components/Heading.vue';
import Pagination, { type PaginationLink } from '@/components/Pagination.vue';
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
        per_page: number;
    };
    actionOptions: { value: string; label: string }[];
}>();

const actionFilter = ref<string>(props.filters.action ?? '');

watch(actionFilter, (next) => {
    const params: Record<string, string | number> = {};
    if (next && next !== '__all') params.action = next;
    router.get(ActivityLogController.index.url(), params, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['logs', 'filters'],
    });
});

function formatDate(iso: string | null): string {
    if (!iso) return '—';
    try {
        return new Date(iso).toLocaleString('vi-VN', { hour12: false });
    } catch {
        return iso;
    }
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
        <Heading variant="small" title="Lịch sử thao tác"
            description="Toàn bộ thao tác do admin / nhân viên / hệ thống thực hiện." />

        <div class="rounded-xl border border-border/60 bg-card p-3 shadow-sm dark:border-sidebar-border">
            <div class="grid gap-3 sm:grid-cols-2">
                <Select v-model="actionFilter">
                    <SelectTrigger class="h-10 w-full">
                        <SelectValue placeholder="Loại thao tác" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="__all">Tất cả thao tác</SelectItem>
                        <SelectItem v-for="opt in actionOptions" :key="opt.value" :value="opt.value">
                            {{ opt.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <div class="flex items-center justify-end text-xs text-muted-foreground">
                    Tổng <span class="ml-1 font-semibold text-foreground">{{ logs.total }}</span> bản ghi
                </div>
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
                            <td class="p-3 text-xs text-muted-foreground">{{ formatDate(log.created_at) }}</td>
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

            <Pagination :meta="logs" :only="['logs', 'filters']" item-label="bản ghi" />
        </div>
    </div>
</template>
