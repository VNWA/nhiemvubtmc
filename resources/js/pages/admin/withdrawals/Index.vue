<script setup lang="ts">
import WithdrawalController from '@/actions/App/Http/Controllers/Admin/WithdrawalController';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatVnd } from '@/lib/vnd';
import { Head, router } from '@inertiajs/vue3';
import { Ban, Check, ClipboardList, X } from 'lucide-vue-next';
import { ref, watch } from 'vue';

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

type Summary = {
    pending_total: number;
    pending_count: number;
    approved_total: number;
    approved_count: number;
};

type StatusOption = { value: string; label: string };

const props = defineProps<{
    items: Item[];
    filter: { status: string };
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
    if (!confirm(`Duyệt rút ${formatVnd(row.amount_vnd)} cho ${row.user?.name ?? 'user'}?`)) return;

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

function formatDate(iso: string | null): string {
    if (!iso) return '—';
    try {
        return new Date(iso).toLocaleString('vi-VN', { hour12: false });
    } catch {
        return iso;
    }
}

function statusChipClass(s: string): string {
    switch (s) {
        case 'pending':
            return 'bg-amber-50 text-amber-800 border-amber-200';
        case 'approved':
            return 'bg-emerald-50 text-emerald-800 border-emerald-200';
        case 'rejected':
            return 'bg-rose-50 text-rose-700 border-rose-200';
        case 'cancelled':
            return 'bg-stone-50 text-stone-600 border-stone-200';
        default:
            return 'bg-stone-50 text-stone-600 border-stone-200';
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

    <div class="flex flex-col gap-4 p-4">
        <Heading
            title="Yêu cầu rút tiền"
            description="Duyệt hoặc từ chối các yêu cầu rút tiền của người dùng. Khi duyệt, hệ thống sẽ tự trừ số dư tương ứng."
        />

        <section class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-3">
                <p class="text-xs font-semibold uppercase tracking-wide text-amber-800">
                    Đang chờ duyệt
                </p>
                <p class="mt-1 font-mono text-xl font-bold text-amber-900">
                    {{ formatVnd(summary.pending_total) }}
                </p>
                <p class="mt-0.5 text-xs text-amber-800">{{ summary.pending_count }} yêu cầu</p>
            </div>
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3">
                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-800">
                    Đã duyệt
                </p>
                <p class="mt-1 font-mono text-xl font-bold text-emerald-900">
                    {{ formatVnd(summary.approved_total) }}
                </p>
                <p class="mt-0.5 text-xs text-emerald-800">{{ summary.approved_count }} yêu cầu</p>
            </div>
            <div class="rounded-xl border border-stone-200 bg-white p-3 sm:col-span-2 lg:col-span-2">
                <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-stone-500">
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

        <section class="overflow-hidden rounded-xl border border-stone-200 bg-white shadow-sm">
            <div v-if="items.length === 0" class="px-4 py-10 text-center text-sm text-stone-500">
                Không có yêu cầu nào.
            </div>

            <table v-else class="w-full text-sm">
                <thead class="bg-stone-50 text-xs uppercase tracking-wide text-stone-500">
                    <tr>
                        <th class="px-3 py-2 text-left">Người dùng</th>
                        <th class="px-3 py-2 text-right">Số tiền</th>
                        <th class="px-3 py-2 text-left">Ngân hàng</th>
                        <th class="px-3 py-2 text-left">Ghi chú</th>
                        <th class="px-3 py-2 text-left">Trạng thái</th>
                        <th class="px-3 py-2 text-left">Thời gian</th>
                        <th class="px-3 py-2 text-right">Hành động</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    <template v-for="row in items" :key="row.id">
                        <tr class="align-top">
                            <td class="px-3 py-3">
                                <div class="font-semibold text-stone-800">
                                    {{ row.user?.name ?? 'Đã xoá' }}
                                </div>
                                <div class="text-xs text-stone-500">
                                    @{{ row.user?.username ?? '—' }}
                                </div>
                            </td>
                            <td class="px-3 py-3 text-right font-mono font-bold text-stone-900">
                                {{ formatVnd(row.amount_vnd) }}
                            </td>
                            <td class="px-3 py-3">
                                <div class="font-semibold text-stone-800">{{ row.bank_name }}</div>
                                <div class="font-mono text-xs text-stone-600">{{ row.bank_account_number }}</div>
                                <div class="text-xs text-stone-500">{{ row.bank_account_name }}</div>
                            </td>
                            <td class="px-3 py-3 text-xs text-stone-600">
                                <p v-if="row.note">{{ row.note }}</p>
                                <p v-else class="text-stone-400">—</p>
                                <p
                                    v-if="row.admin_note"
                                    class="mt-1 rounded bg-stone-50 px-2 py-1 text-stone-700"
                                >
                                    <span class="font-semibold">Phản hồi:</span> {{ row.admin_note }}
                                </p>
                            </td>
                            <td class="px-3 py-3">
                                <span
                                    class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[11px] font-semibold"
                                    :class="statusChipClass(row.status)"
                                >
                                    {{ row.status_label }}
                                </span>
                                <p v-if="row.processor" class="mt-1 text-[11px] text-stone-500">
                                    bởi {{ row.processor.name }}
                                </p>
                            </td>
                            <td class="px-3 py-3 text-xs text-stone-600">
                                <div>Tạo: {{ formatDate(row.created_at) }}</div>
                                <div v-if="row.processed_at">Xử lý: {{ formatDate(row.processed_at) }}</div>
                            </td>
                            <td class="px-3 py-3 text-right">
                                <div v-if="row.status === 'pending'" class="flex justify-end gap-1.5">
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        class="text-rose-600 hover:bg-rose-50"
                                        :title="activeRowId === row.id ? 'Nhập lý do rồi xác nhận' : 'Từ chối'"
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
                                <button
                                    v-else
                                    type="button"
                                    class="inline-flex items-center gap-1 text-xs text-stone-400"
                                    disabled
                                >
                                    <Ban class="size-3.5" /> Đã xử lý
                                </button>
                            </td>
                        </tr>
                        <tr v-if="row.status === 'pending' && activeRowId === row.id" :key="`${row.id}-note`" class="bg-stone-50">
                            <td colspan="7" class="px-3 py-2">
                                <label class="block text-xs font-semibold text-stone-600">
                                    Ghi chú khi duyệt / lý do từ chối
                                </label>
                                <textarea
                                    v-model="noteDraft"
                                    rows="2"
                                    maxlength="500"
                                    class="mt-1 w-full rounded-lg border border-stone-300 bg-white px-3 py-2 text-sm focus:border-(--primary-1) focus:outline-none focus:ring-2 focus:ring-(--primary-1)/20"
                                    placeholder="Tuỳ chọn khi duyệt, bắt buộc khi từ chối…"
                                ></textarea>
                                <div class="mt-2 flex justify-end gap-2">
                                    <Button size="sm" variant="outline" @click="toggleNote(row.id)">Đóng</Button>
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
        </section>
    </div>
</template>
