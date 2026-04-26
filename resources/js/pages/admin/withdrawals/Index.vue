<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Ban, Check, ClipboardList, X } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import WithdrawalController from '@/actions/App/Http/Controllers/Admin/WithdrawalController';
import AdminListReloadButton from '@/components/admin/AdminListReloadButton.vue';
import Heading from '@/components/Heading.vue';
import Pagination from '@/components/Pagination.vue';
import type {PaginationLink} from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatVnd } from '@/lib/vnd';

type Item = {
    id: number;
    user: { id: number; name: string; username: string } | null;
    amount_vnd: number;
    bank_name: string;
    bank_account_number: string;
    bank_account_name: string;
    note: string | null;
    admin_note: string | null;
    status: string;
    status_label: string;
    processor: { id: number; name: string } | null;
    processed_at: string | null;
    created_at: string | null;
};

type Paginator = {
    data: Item[];
    current_page: number;
    from: number | null;
    to: number | null;
    total: number;
    per_page: number;
    last_page: number;
    links: PaginationLink[];
};

type Summary = {
    pending_total: number;
    pending_count: number;
    approved_total: number;
    approved_count: number;
};

type StatusOption = { value: string; label: string };

const props = defineProps<{
    items: Paginator;
    filter: { status: string; per_page: number };
    statusOptions: StatusOption[];
    summary: Summary;
}>();

const statusFilter = ref(props.filter.status ?? 'all');
const activeRowId = ref<number | null>(null);
const noteDraft = ref<string>('');

watch(statusFilter, (next) => {
    router.get(
        WithdrawalController.index.url(),
        next === 'all' ? {} : { status: next },
        { preserveState: true, preserveScroll: true, replace: true },
    );
});

function toggleNote(id: number) {
    if (activeRowId.value === id) {
        activeRowId.value = null;
        noteDraft.value = '';
    } else {
        activeRowId.value = id;
        noteDraft.value = '';
    }
}

function approve(row: Item) {
    if (!confirm(`Duyệt rút ${formatVnd(row.amount_vnd)} cho ${row.user?.name ?? 'user'}?`)) {
return;
}

    router.post(
        WithdrawalController.approve.url({ withdrawal: row.id }),
        { admin_note: activeRowId.value === row.id ? noteDraft.value : '' },
        {
            preserveScroll: true,
            onSuccess: () => {
                activeRowId.value = null;
                noteDraft.value = '';
            },
        },
    );
}

function reject(row: Item) {
    if (activeRowId.value !== row.id) {
        activeRowId.value = row.id;
        noteDraft.value = '';

        return;
    }

    const note = noteDraft.value.trim();

    if (!note) {
        alert('Vui lòng nhập lý do từ chối.');

        return;
    }

    router.post(
        WithdrawalController.reject.url({ withdrawal: row.id }),
        { admin_note: note },
        {
            preserveScroll: true,
            onSuccess: () => {
                activeRowId.value = null;
                noteDraft.value = '';
            },
        },
    );
}

function statusChipClass(s: string): string {
    switch (s) {
        case 'pending':
            return 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300';
        case 'approved':
            return 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300';
        case 'rejected':
            return 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300';
        case 'cancelled':
            return 'border-border/60 bg-muted/40 text-muted-foreground';
        default:
            return 'border-border/60 bg-muted/40 text-muted-foreground';
    }
}

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Yêu cầu rút tiền',
                href: WithdrawalController.index.url(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Yêu cầu rút tiền" />

    <div class="flex flex-col gap-5 p-4">
        <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
            <Heading
                title="Yêu cầu rút tiền"
                description="Duyệt hoặc từ chối yêu cầu rút tiền của người dùng. Khi duyệt, hệ thống tự trừ số dư tương ứng."
            />
            <AdminListReloadButton
                :only="['items', 'filter', 'summary', 'statusOptions']"
            />
        </div>

        <section class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div
                class="rounded-xl border border-amber-200 bg-amber-50 p-3 dark:border-amber-500/30 dark:bg-amber-500/10"
            >
                <p
                    class="text-xs font-semibold uppercase tracking-wide text-amber-800 dark:text-amber-300"
                >
                    Đang chờ duyệt
                </p>
                <p class="mt-1 font-mono text-xl font-bold text-amber-900 dark:text-amber-200">
                    {{ formatVnd(summary.pending_total) }}
                </p>
                <p class="mt-0.5 text-xs text-amber-800 dark:text-amber-300/80">
                    {{ summary.pending_count }} yêu cầu
                </p>
            </div>
            <div
                class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 dark:border-emerald-500/30 dark:bg-emerald-500/10"
            >
                <p
                    class="text-xs font-semibold uppercase tracking-wide text-emerald-800 dark:text-emerald-300"
                >
                    Đã duyệt
                </p>
                <p class="mt-1 font-mono text-xl font-bold text-emerald-900 dark:text-emerald-200">
                    {{ formatVnd(summary.approved_total) }}
                </p>
                <p class="mt-0.5 text-xs text-emerald-800 dark:text-emerald-300/80">
                    {{ summary.approved_count }} yêu cầu
                </p>
            </div>
            <div
                class="rounded-xl border border-border/60 bg-card p-3 shadow-sm sm:col-span-2 lg:col-span-2"
            >
                <div
                    class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground"
                >
                    <ClipboardList class="size-3.5" /> Lọc theo trạng thái
                </div>
                <div class="mt-2">
                    <Select v-model="statusFilter">
                        <SelectTrigger class="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Tất cả</SelectItem>
                            <SelectItem
                                v-for="opt in statusOptions"
                                :key="opt.value"
                                :value="opt.value"
                            >
                                {{ opt.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>
        </section>

        <section
            class="overflow-hidden rounded-xl border border-border/60 bg-card shadow-sm dark:border-sidebar-border"
        >
            <div class="overflow-x-auto">
                <table class="w-full min-w-4xl text-left text-sm">
                    <thead
                        class="border-b border-border/60 bg-muted/50 text-xs uppercase tracking-wide text-muted-foreground dark:border-sidebar-border"
                    >
                        <tr>
                            <th class="px-3 py-2 text-left font-semibold">Người dùng</th>
                            <th class="px-3 py-2 text-right font-semibold">Số tiền</th>
                            <th class="px-3 py-2 text-left font-semibold">Ngân hàng</th>
                            <th class="px-3 py-2 text-left font-semibold">Ghi chú</th>
                            <th class="px-3 py-2 text-left font-semibold">Trạng thái</th>
                            <th class="px-3 py-2 text-left font-semibold">Thời gian</th>
                            <th class="px-3 py-2 text-right font-semibold">Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="items.data.length === 0">
                            <td
                                colspan="7"
                                class="px-3 py-10 text-center text-sm text-muted-foreground"
                            >
                                Không có yêu cầu nào.
                            </td>
                        </tr>
                        <template v-for="row in items.data" :key="row.id">
                            <tr
                                class="border-b border-border/40 align-top transition hover:bg-muted/30 dark:border-sidebar-border/60"
                            >
                                <td class="px-3 py-3">
                                    <div class="font-semibold text-foreground">
                                        {{ row.user?.name ?? 'Đã xoá' }}
                                    </div>
                                    <div class="font-mono text-xs text-muted-foreground">
                                        @{{ row.user?.username ?? '—' }}
                                    </div>
                                </td>
                                <td class="px-3 py-3 text-right font-mono font-bold text-foreground">
                                    {{ formatVnd(row.amount_vnd) }}
                                </td>
                                <td class="px-3 py-3">
                                    <div class="font-semibold text-foreground">
                                        {{ row.bank_name }}
                                    </div>
                                    <div class="font-mono text-xs text-foreground/70">
                                        {{ row.bank_account_number }}
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        {{ row.bank_account_name }}
                                    </div>
                                </td>
                                <td class="px-3 py-3 text-xs text-foreground/80">
                                    <p v-if="row.note">{{ row.note }}</p>
                                    <p v-else class="text-muted-foreground/60">—</p>
                                    <p
                                        v-if="row.admin_note"
                                        class="mt-1 rounded bg-muted/60 px-2 py-1 text-xs text-foreground/80"
                                    >
                                        <span class="font-semibold">Phản hồi:</span>
                                        {{ row.admin_note }}
                                    </p>
                                </td>
                                <td class="px-3 py-3">
                                    <span
                                        class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[11px] font-semibold"
                                        :class="statusChipClass(row.status)"
                                    >
                                        {{ row.status_label }}
                                    </span>
                                    <p
                                        v-if="row.processor"
                                        class="mt-1 text-[11px] text-muted-foreground"
                                    >
                                        bởi {{ row.processor.name }}
                                    </p>
                                </td>
                                <td class="px-3 py-3 text-xs text-muted-foreground">
                                    <div>Tạo: {{ row.created_at ?? '—' }}</div>
                                    <div v-if="row.processed_at">
                                        Xử lý: {{ row.processed_at }}
                                    </div>
                                </td>
                                <td class="px-3 py-3 text-right">
                                    <div
                                        v-if="row.status === 'pending'"
                                        class="flex justify-end gap-1.5"
                                    >
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            class="text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-500/10"
                                            :title="
                                                activeRowId === row.id
                                                    ? 'Nhập lý do rồi xác nhận'
                                                    : 'Từ chối'
                                            "
                                            @click="reject(row)"
                                        >
                                            <X class="size-3.5" />
                                            <span class="ml-1">Từ chối</span>
                                        </Button>
                                        <Button
                                            size="sm"
                                            class="bg-emerald-600 text-white hover:bg-emerald-700"
                                            @click="approve(row)"
                                        >
                                            <Check class="size-3.5" />
                                            <span class="ml-1">Duyệt</span>
                                        </Button>
                                    </div>
                                    <span
                                        v-else
                                        class="inline-flex items-center gap-1 text-xs text-muted-foreground"
                                    >
                                        <Ban class="size-3.5" /> Đã xử lý
                                    </span>
                                </td>
                            </tr>
                            <tr
                                v-if="row.status === 'pending' && activeRowId === row.id"
                                :key="`${row.id}-note`"
                                class="border-b border-border/40 bg-muted/40 dark:border-sidebar-border/60"
                            >
                                <td colspan="7" class="px-3 py-3">
                                    <label
                                        class="block text-xs font-semibold text-muted-foreground"
                                    >
                                        Ghi chú khi duyệt / lý do từ chối
                                    </label>
                                    <textarea
                                        v-model="noteDraft"
                                        rows="2"
                                        maxlength="500"
                                        class="mt-1 w-full rounded-lg border border-border/60 bg-background px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 dark:border-sidebar-border"
                                        placeholder="Tuỳ chọn khi duyệt, bắt buộc khi từ chối…"
                                    ></textarea>
                                    <div class="mt-2 flex justify-end gap-2">
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            @click="toggleNote(row.id)"
                                        >
                                            Đóng
                                        </Button>
                                        <Button
                                            size="sm"
                                            class="bg-rose-600 text-white hover:bg-rose-700"
                                            @click="reject(row)"
                                        >
                                            Xác nhận từ chối
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <Pagination
                :meta="items"
                :only="['items', 'filter', 'summary', 'statusOptions']"
                item-label="yêu cầu"
            />
        </section>
    </div>
</template>
