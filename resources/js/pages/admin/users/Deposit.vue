<script setup lang="ts">
import type { FormDataConvertible } from '@inertiajs/core';
import { Form, Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowDownCircle,
    ArrowLeft,
    ArrowUpCircle,
    Coins,
    Gift,
    Lock,
    RotateCcw,
    Snowflake,
    Ticket,
    Unlock,
    Wallet,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
import AdminListReloadButton from '@/components/admin/AdminListReloadButton.vue';
import CurrencyInput from '@/components/CurrencyInput.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import Pagination from '@/components/Pagination.vue';
import type { PaginationLink } from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { formatVnd } from '@/lib/vnd';

type Txn = {
    id: number;
    direction: 'credit' | 'debit';
    source: string;
    source_label: string;
    amount_vnd: number;
    balance_after_vnd: number;
    description: string | null;
    created_at: string | null;
};

type Paginator = {
    data: Txn[];
    current_page: number;
    from: number | null;
    to: number | null;
    total: number;
    per_page: number;
    last_page: number;
    links: PaginationLink[];
};

const props = defineProps<{
    user: {
        id: number;
        name: string;
        username: string;
        email: string;
        balance_vnd: number;
        frozen_vnd: number;
        available_vnd: number;
        role: string;
    };
    transactions: Paginator;
    filter: WalletFilter;
    list_totals: {
        adminCreditVnd: number;
        refundVnd: number;
        betPlaceVnd: number;
        commissionVnd: number;
        outDebitVnd: number;
        freezeVnd: number;
        adminCreditCount: number;
        refundCount: number;
        betPlaceCount: number;
        commissionCount: number;
        outDebitCount: number;
        freezeCount: number;
    };
    summary: {
        net_vnd: number;
    };
}>();

type WalletFilter = 'all' | 'credit' | 'refund' | 'debit' | 'commission' | 'bet_place' | 'freeze';

const quickAmounts = [100_000, 500_000, 1_000_000, 5_000_000, 10_000_000];

type Operation = 'credit' | 'debit' | 'commission' | 'freeze' | 'unfreeze';

const DEFAULT_NOTE: Record<Operation, string> = {
    credit: 'Nạp tiền thành công',
    debit: 'Rút tiền thành công',
    commission: 'Thưởng hoa hồng',
    /** Chỉ phần mô tả; tiền tố "Lý do đóng băng: " thêm lúc gửi (xem `transformAdjustBalance`). */
    freeze: 'Sai thao tác',
    unfreeze: 'Mở đóng băng',
};

const FREEZE_NOTE_PREFIX = 'Lý do đóng băng: ';

/**
 * Gửi lên server dạng đầy đủ, trong khi UI chỉ cho sửa phần sau dấu hai chấm.
 */
function transformAdjustBalance(
    data: Record<string, FormDataConvertible>,
): Record<string, FormDataConvertible> {
    if (String(data.operation) !== 'freeze') {
        return data;
    }

    const raw = typeof data.note === 'string' ? data.note.trim() : '';
    const detail = raw || DEFAULT_NOTE.freeze;
    const full = FREEZE_NOTE_PREFIX + detail;

    return { ...data, note: full };
}

const adjustAmount = ref<number>(0);
const adjustOperation = ref<Operation>('credit');
const adjustNote = ref<string>(DEFAULT_NOTE.credit);

const FILTERS: Array<{ value: WalletFilter; label: string; icon?: 'lock' }> = [
    { value: 'all', label: 'Tất cả' },
    { value: 'credit', label: 'Nạp tiền' },
    { value: 'refund', label: 'Hoàn trả' },
    { value: 'debit', label: 'Rút & trừ' },
    { value: 'bet_place', label: 'Lệ phí' },
    { value: 'commission', label: 'Hoa hồng' },
    { value: 'freeze', label: 'Đóng băng', icon: 'lock' },
];

const showFreezeUi = computed(() => props.user.frozen_vnd > 1);

const filterChips = computed(() => {
    if (showFreezeUi.value) {
        return FILTERS;
    }

    return FILTERS.filter((f) => f.value !== 'freeze');
});

function setFilter(value: WalletFilter) {
    if (value === props.filter) {
        return;
    }

    const q: Record<string, string | number> = {
        page: 1,
        per_page: props.transactions.per_page,
    };

    if (value !== 'all') {
        q.filter = value;
    }

    router.get(UserController.deposit.url({ user: props.user.id }, { query: q }), {}, {
        preserveScroll: true,
        preserveState: true,
        only: ['transactions', 'list_totals', 'summary', 'filter'],
    });
}

function pickQuick(v: number) {
    const cap = maxAmountThisOp.value;
    adjustAmount.value = cap < Number.MAX_SAFE_INTEGER ? Math.min(v, cap) : v;
}

/** Chỉ cho trừ / đóng băng / mở đóng băng — không dùng cho nạp hoặc hoa hồng (không có trần hợp lý). */
const showQuickMaxButton = computed(
    () =>
        adjustOperation.value === 'debit'
        || adjustOperation.value === 'freeze'
        || adjustOperation.value === 'unfreeze',
);

const maxQuickAllDisabled = computed(() => {
    if (!showQuickMaxButton.value) {
        return true;
    }

    const cap = maxAmountThisOp.value;

    return cap <= 0;
});

const maxQuickAllTitle = computed(() => {
    if (adjustOperation.value === 'debit') {
        return 'Điền toàn bộ tổng số dư hiện tại';
    }

    if (adjustOperation.value === 'freeze') {
        return 'Điền toàn bộ số dư khả dụng (tối đa có thể đóng băng)';
    }

    if (adjustOperation.value === 'unfreeze') {
        return 'Điền toàn bộ số tiền đang đóng băng';
    }

    return '';
});

function pickMaxAmount() {
    const cap = maxAmountThisOp.value;

    if (cap <= 0) {
        return;
    }

    adjustAmount.value = cap;
}

function resetAdjust() {
    adjustAmount.value = 0;
    adjustOperation.value = 'credit';
    adjustNote.value = DEFAULT_NOTE.credit;
}

watch(adjustOperation, (next, prev) => {
    const prevDefault = DEFAULT_NOTE[prev as Operation];

    if (!adjustNote.value || adjustNote.value === prevDefault) {
        adjustNote.value = DEFAULT_NOTE[next];
    }
});

const maxAmountThisOp = computed(() => {
    if (adjustOperation.value === 'freeze') {
        return props.user.available_vnd;
    }

    if (adjustOperation.value === 'unfreeze') {
        return props.user.frozen_vnd;
    }

    if (adjustOperation.value === 'debit') {
        return props.user.balance_vnd;
    }

    return Number.MAX_SAFE_INTEGER;
});

const canSubmitAdjust = computed(() => {
    if (adjustAmount.value <= 0) {
        return false;
    }

    if (adjustOperation.value === 'freeze' && adjustAmount.value > props.user.available_vnd) {
        return false;
    }

    if (adjustOperation.value === 'unfreeze' && adjustAmount.value > props.user.frozen_vnd) {
        return false;
    }

    if (adjustOperation.value === 'debit' && adjustAmount.value > props.user.balance_vnd) {
        return false;
    }

    return true;
});

const submitLabel = computed(() => {
    if (adjustOperation.value === 'credit') {
        return 'Xác nhận nạp';
    }

    if (adjustOperation.value === 'debit') {
        return 'Xác nhận trừ';
    }

    if (adjustOperation.value === 'commission') {
        return 'Xác nhận hoa hồng';
    }

    if (adjustOperation.value === 'freeze') {
        return 'Xác nhận đóng băng';
    }

    return 'Xác nhận mở đóng băng';
});

const submitClass = computed(() => {
    if (adjustOperation.value === 'credit') {
        return 'submit-credit';
    }

    if (adjustOperation.value === 'debit') {
        return 'submit-debit';
    }

    if (adjustOperation.value === 'commission') {
        return 'submit-commission';
    }

    if (adjustOperation.value === 'freeze') {
        return 'submit-freeze';
    }

    return 'submit-unfreeze';
});

function sourceChipClass(src: string): string {
    if (src === 'admin_credit') {
        return 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300';
    }

    if (src === 'admin_debit') {
        return 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300';
    }

    if (src === 'commission') {
        return 'border-fuchsia-200 bg-fuchsia-50 text-fuchsia-800 dark:border-fuchsia-500/30 dark:bg-fuchsia-500/10 dark:text-fuchsia-300';
    }

    if (src === 'bet_place') {
        return 'border-sky-200 bg-sky-50 text-sky-800 dark:border-sky-500/30 dark:bg-sky-500/10 dark:text-sky-300';
    }

    if (src === 'bet_cancel') {
        return 'border-indigo-200 bg-indigo-50 text-indigo-800 dark:border-indigo-500/30 dark:bg-indigo-500/10 dark:text-indigo-300';
    }

    if (src === 'withdrawal') {
        return 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300';
    }

    if (src === 'admin_freeze') {
        return 'border-cyan-200 bg-cyan-50 text-cyan-900 dark:border-cyan-500/30 dark:bg-cyan-500/10 dark:text-cyan-200';
    }

    if (src === 'admin_unfreeze') {
        return 'border-sky-200 bg-sky-50 text-sky-900 dark:border-sky-500/30 dark:bg-sky-500/10 dark:text-sky-200';
    }

    if (src === 'event_refund') {
        return 'border-violet-200 bg-violet-50 text-violet-800 dark:border-violet-500/30 dark:bg-violet-500/10 dark:text-violet-300';
    }

    return 'border-border/60 bg-muted/40 text-foreground/80';
}

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Users', href: UserController.index.url() },
            { title: 'Nạp tiền', href: '#' },
        ],
    },
});
</script>

<template>

    <Head :title="`Nạp tiền · ${user.name}`" />

    <div class="flex flex-col gap-4 p-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <Heading variant="small" title="Nạp / trừ số dư người dùng"
                description="Điều chỉnh số dư và theo dõi toàn bộ lịch sử giao dịch của tài khoản." />
            <div class="flex flex-wrap items-center justify-end gap-2">
                <AdminListReloadButton :only="['user', 'transactions', 'list_totals', 'summary', 'filter']" />
                <Button variant="outline" as-child>
                    <Link :href="UserController.index.url()">
                        <ArrowLeft class="size-4" /> Quay lại danh sách
                    </Link>
                </Button>
            </div>
        </div>

        <section class="user-card">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0 flex-1">
                    <p class="user-eyebrow">Đang thao tác với</p>
                    <h2 class="mt-0.5 text-lg font-bold text-foreground">{{ user.name }}</h2>
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        <span class="font-mono">@{{ user.username }}</span>
                        <span class="mx-1.5">·</span>
                        <span
                            class="inline-flex rounded-md bg-secondary px-2 py-0.5 text-[11px] capitalize text-secondary-foreground">
                            {{ user.role }}
                        </span>
                    </p>
                </div>

                <div class="user-balance">
                    <Wallet class="size-5" />
                    <div class="text-right leading-tight">
                        <p class="balance-eyebrow">Tổng ví (VNĐ)</p>
                        <p class="mt-1 font-mono text-2xl font-extrabold">{{ formatVnd(user.balance_vnd) }}</p>
                        <p
                            v-if="user.frozen_vnd > 1"
                            class="mt-1.5 text-[10px] font-medium leading-tight text-[#ffecb3]"
                        >
                            Đang đóng băng
                            <span class="font-mono font-bold">{{ formatVnd(user.frozen_vnd) }}</span>
                            <span class="mx-0.5">·</span>
                            Khả dụng
                            <span class="font-mono font-bold text-white">{{ formatVnd(user.available_vnd) }}</span>
                        </p>
                        <p
                            v-else
                            class="mt-1.5 text-[10px] font-medium leading-tight text-[#ffecb3]/90"
                        >
                            Toàn bộ số dư trên đều dùng được
                        </p>
                    </div>
                </div>
            </div>

            <p class="mt-3 text-[11px] text-muted-foreground">
                Chọn thẻ hoặc nút bộ lọc để xem lịch sử theo từng loại (giống ví người chơi).
            </p>

            <div
                class="mt-2 grid grid-cols-2 gap-1 min-[500px]:grid-cols-3 min-[800px]:grid-cols-4"
                :class="showFreezeUi ? 'min-[1000px]:grid-cols-7' : 'min-[1000px]:grid-cols-6'"
            >
                <button
                    v-for="f in filterChips"
                    :key="f.value"
                    type="button"
                    class="filter-chip-adm"
                    :class="{ 'filter-chip-adm--active': filter === f.value, 'filter-chip-adm--freeze': f.value === 'freeze' }"
                    @click="setFilter(f.value)"
                >
                    <span class="filter-chip-adm-inner">
                        <Lock v-if="f.icon === 'lock'" class="size-3 shrink-0" />
                        <span>{{ f.label }}</span>
                    </span>
                </button>
            </div>

            <div
                class="mt-3 grid grid-cols-2 gap-2 text-[11px] sm:grid-cols-3"
                :class="showFreezeUi ? 'min-[900px]:grid-cols-7' : 'min-[900px]:grid-cols-6'"
            >
                <button
                    type="button"
                    class="summary-tile border-emerald-200 bg-emerald-50/70 text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300"
                    :class="{ 'summary-tile--on': filter === 'credit' }"
                    @click="setFilter('credit')"
                >
                    <p class="flex items-center gap-1 font-semibold">
                        <ArrowUpCircle class="size-3" /> Nạp tiền
                    </p>
                    <p class="font-mono text-sm font-bold">{{ formatVnd(list_totals.adminCreditVnd) }}</p>
                    <p class="text-[10px] opacity-90">{{ list_totals.adminCreditCount }} giao dịch</p>
                </button>
                <button
                    type="button"
                    class="summary-tile border-amber-200 bg-amber-50/70 text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200"
                    :class="{ 'summary-tile--on': filter === 'refund' }"
                    @click="setFilter('refund')"
                >
                    <p class="flex items-center gap-1 font-semibold">
                        <RotateCcw class="size-3" /> Hoàn trả
                    </p>
                    <p class="font-mono text-sm font-bold">{{ formatVnd(list_totals.refundVnd) }}</p>
                    <p class="text-[10px] opacity-90">{{ list_totals.refundCount }} giao dịch</p>
                </button>
                <button
                    type="button"
                    class="summary-tile border-blue-200 bg-blue-50/70 text-blue-800 dark:border-blue-500/30 dark:bg-blue-500/10 dark:text-blue-300"
                    :class="{ 'summary-tile--on': filter === 'bet_place' }"
                    @click="setFilter('bet_place')"
                >
                    <p class="flex items-center gap-1 font-semibold">
                        <Ticket class="size-3" /> Lệ phí
                    </p>
                    <p class="font-mono text-sm font-bold">{{ formatVnd(list_totals.betPlaceVnd) }}</p>
                    <p class="text-[10px] opacity-90">{{ list_totals.betPlaceCount }} giao dịch</p>
                </button>
                <button
                    type="button"
                    class="summary-tile border-fuchsia-200 bg-fuchsia-50/70 text-fuchsia-800 dark:border-fuchsia-500/30 dark:bg-fuchsia-500/10 dark:text-fuchsia-300"
                    :class="{ 'summary-tile--on': filter === 'commission' }"
                    @click="setFilter('commission')"
                >
                    <p class="flex items-center gap-1 font-semibold">
                        <Gift class="size-3" /> Hoa hồng
                    </p>
                    <p class="font-mono text-sm font-bold">{{ formatVnd(list_totals.commissionVnd) }}</p>
                    <p class="text-[10px] opacity-90">{{ list_totals.commissionCount }} giao dịch</p>
                </button>
                <button
                    type="button"
                    class="summary-tile border-rose-200 bg-rose-50/70 text-rose-800 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300"
                    :class="{ 'summary-tile--on': filter === 'debit' }"
                    @click="setFilter('debit')"
                >
                    <p class="flex items-center gap-1 font-semibold">
                        <ArrowDownCircle class="size-3" /> Rút &amp; trừ
                    </p>
                    <p class="font-mono text-sm font-bold">{{ formatVnd(list_totals.outDebitVnd) }}</p>
                    <p class="text-[10px] opacity-90">{{ list_totals.outDebitCount }} giao dịch</p>
                </button>
                <button
                    v-if="showFreezeUi"
                    type="button"
                    class="summary-tile border-cyan-200 bg-cyan-50/80 text-cyan-900 dark:border-cyan-500/30 dark:bg-cyan-500/10 dark:text-cyan-200"
                    :class="{ 'summary-tile--on': filter === 'freeze' }"
                    @click="setFilter('freeze')"
                >
                    <p class="flex items-center gap-1 font-semibold">
                        <Snowflake class="size-3" /> Đóng băng
                    </p>
                    <p class="font-mono text-sm font-bold">{{ formatVnd(list_totals.freezeVnd) }}</p>
                    <p class="text-[10px] opacity-90">{{ list_totals.freezeCount }} giao dịch</p>
                </button>
                <div
                    class="summary-tile cursor-default border-border/60 bg-muted/30 text-foreground dark:bg-muted/20"
                >
                    <p class="flex items-center gap-1 font-semibold">
                        <Coins class="size-3" /> Chênh lệch
                    </p>
                    <p
                        class="font-mono text-sm font-bold"
                        :class="summary.net_vnd >= 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-700 dark:text-rose-400'"
                    >
                        {{ summary.net_vnd >= 0 ? '+' : '' }}{{ formatVnd(summary.net_vnd) }}
                    </p>
                    <p class="text-[10px] text-muted-foreground">Tín dụng − ghi nợ (tổng)</p>
                </div>
            </div>
        </section>

        <div class="grid gap-4 lg:grid-cols-5">
            <section class="lg:col-span-2">
                <div class="rounded-xl border border-border/60 bg-card p-4 shadow-sm">
                    <h3 class="text-base font-bold text-foreground">Nạp / trừ / hoa hồng / đóng băng</h3>
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        <strong>Đóng băng</strong> tách phần tiền khỏi số dùng được; <strong>Mở đóng băng</strong> trả
                        lại phần đã khóa. Tổng tiền trong ví không đổi, chỉ tách mức dùng được.
                    </p>

                    <Form v-bind="UserController.adjustBalance.form({ user: user.id })" class="mt-4 space-y-3"
                        :transform="transformAdjustBalance" v-slot="{ errors, processing }" @success="resetAdjust">
                        <div class="grid gap-1.5">
                            <Label>Loại điều chỉnh</Label>
                            <div class="flex flex-wrap gap-2">
                                <label class="op-chip"
                                    :class="adjustOperation === 'credit' ? 'op-chip--credit-active' : ''">
                                    <input type="radio" name="operation" value="credit" class="hidden"
                                        :checked="adjustOperation === 'credit'" @change="adjustOperation = 'credit'" />
                                    <ArrowUpCircle class="size-4" /> Nạp tiền
                                </label>
                                <label class="op-chip"
                                    :class="adjustOperation === 'debit' ? 'op-chip--debit-active' : ''">
                                    <input type="radio" name="operation" value="debit" class="hidden"
                                        :checked="adjustOperation === 'debit'" @change="adjustOperation = 'debit'" />
                                    <ArrowDownCircle class="size-4" /> Trừ tiền
                                </label>
                                <label class="op-chip"
                                    :class="adjustOperation === 'commission' ? 'op-chip--commission-active' : ''">
                                    <input type="radio" name="operation" value="commission" class="hidden"
                                        :checked="adjustOperation === 'commission'"
                                        @change="adjustOperation = 'commission'" />
                                    <Gift class="size-4" /> Thưởng hoa hồng
                                </label>
                            </div>
                            <div class="mt-1.5 flex flex-wrap gap-2">
                                <label class="op-chip"
                                    :class="adjustOperation === 'freeze' ? 'op-chip--freeze-active' : ''">
                                    <input type="radio" name="operation" value="freeze" class="hidden"
                                        :checked="adjustOperation === 'freeze'"
                                        @change="adjustOperation = 'freeze'" />
                                    <Lock class="size-4" /> Đóng băng
                                </label>
                                <label class="op-chip"
                                    :class="adjustOperation === 'unfreeze' ? 'op-chip--unfreeze-active' : ''">
                                    <input type="radio" name="operation" value="unfreeze" class="hidden"
                                        :checked="adjustOperation === 'unfreeze'"
                                        @change="adjustOperation = 'unfreeze'" />
                                    <Unlock class="size-4" /> Mở đóng băng
                                </label>
                            </div>
                            <p v-if="adjustOperation === 'freeze'" class="pt-0.5 text-[11px] text-muted-foreground">
                                Tối đa
                                <span class="font-mono font-semibold text-foreground">{{
                                    formatVnd(user.available_vnd) }}</span>
                                (số dư khả dụng).
                            </p>
                            <p v-else-if="adjustOperation === 'unfreeze'" class="pt-0.5 text-[11px] text-muted-foreground">
                                Tối đa
                                <span class="font-mono font-semibold text-foreground">{{
                                    formatVnd(user.frozen_vnd) }}</span>
                                (đang đóng băng).
                            </p>
                            <p v-else-if="adjustOperation === 'debit'" class="pt-0.5 text-[11px] text-muted-foreground">
                                Tối đa
                                <span class="font-mono font-semibold text-foreground">{{
                                    formatVnd(user.balance_vnd) }}</span>
                                (tổng số dư; không thể trừ vượt quá).
                            </p>
                            <InputError :message="errors.operation" />
                        </div>

                        <div class="grid gap-1.5">
                            <Label for="amount_vnd">Số tiền (VNĐ)</Label>
                            <CurrencyInput id="amount_vnd" v-model="adjustAmount" name="amount_vnd"
                                placeholder="VD: 100.000" :aria-invalid="!!errors.amount_vnd" />
                            <InputError :message="errors.amount_vnd" />
                            <div class="flex flex-wrap items-center gap-1.5 pt-1">
                                <button v-for="v in quickAmounts" :key="v" type="button" class="quick-btn"
                                    @click="pickQuick(v)">
                                    {{ formatVnd(v) }}
                                </button>
                                <button
                                    v-if="showQuickMaxButton"
                                    type="button"
                                    class="quick-btn quick-btn--max"
                                    :disabled="maxQuickAllDisabled"
                                    :title="maxQuickAllTitle"
                                    @click="pickMaxAmount"
                                >
                                    Toàn bộ
                                </button>
                            </div>
                            <p v-if="showQuickMaxButton" class="text-[10px] text-muted-foreground">
                                «Toàn bộ» = điền nhanh mức tối đa: trừ tiền → tổng số dư; đóng băng → số khả dụng; mở đóng băng → số đang đóng băng.
                            </p>
                        </div>

                        <div class="grid gap-1.5">
                            <Label for="note">{{
                                adjustOperation === 'freeze' ? 'Nội dung mô tả' : 'Ghi chú'
                            }}</Label>
                            <Input
                                v-if="adjustOperation === 'freeze'"
                                id="note"
                                v-model="adjustNote"
                                name="note"
                                :maxlength="255 - FREEZE_NOTE_PREFIX.length"
                                placeholder="Sai thao tác"
                                autocomplete="off"
                            />
                            <Input
                                v-else
                                id="note"
                                v-model="adjustNote"
                                name="note"
                                maxlength="255"
                                :placeholder="DEFAULT_NOTE[adjustOperation]"
                            />
                            <p v-if="adjustOperation === 'freeze'" class="text-[11px] text-muted-foreground">
                                Chỉ nhập phần mô tả; khi lưu hệ thống gắn thêm
                                <span class="whitespace-nowrap font-semibold">«{{ FREEZE_NOTE_PREFIX }}»</span>
                                ở phía trước (mặc định: <span class="font-semibold">{{ DEFAULT_NOTE.freeze }}</span>).
                            </p>
                            <p v-else class="text-[11px] text-muted-foreground">
                                Ghi chú sẽ hiển thị ở tab lịch sử giao dịch của user. Mặc định:
                                <span class="font-semibold">{{ DEFAULT_NOTE[adjustOperation] }}</span>.
                            </p>
                            <InputError :message="errors.note" />
                        </div>

                        <div class="flex items-center gap-2 pt-1">
                            <Button type="button" variant="outline" class="flex-1" :disabled="processing"
                                @click="resetAdjust">
                                <RotateCcw class="size-4" /> Đặt lại
                            </Button>
                            <Button type="submit" class="flex-[1.4]"
                                :class="submitClass"
                                :disabled="processing || !canSubmitAdjust">
                                <Spinner v-if="processing" />
                                {{ submitLabel }}
                            </Button>
                        </div>
                    </Form>
                </div>
            </section>

            <section class="lg:col-span-3">
                <div class="overflow-hidden rounded-xl border border-border/60 bg-card shadow-sm">
                    <header
                        class="flex items-center justify-between border-b border-border/60 px-4 py-2.5"
                    >
                        <div>
                            <h3 class="text-sm font-bold text-foreground">Lịch sử giao dịch</h3>
                            <p class="text-xs text-muted-foreground">
                                <span v-if="filter === 'all'">Theo dõi theo từng giao dịch (có phân trang).</span>
                                <span v-else>Đang lọc theo loại đã chọn — trang 1 khi đổi bộ lọc.</span>
                            </p>
                        </div>
                        <span class="font-mono text-xs text-muted-foreground">
                            Tổng: {{ transactions.total }} giao dịch
                        </span>
                    </header>

                    <div
                        v-if="transactions.data.length === 0"
                        class="px-4 py-10 text-center text-sm text-muted-foreground"
                    >
                        <template v-if="filter === 'all'">Chưa có giao dịch nào.</template>
                        <template v-else>Chưa có giao dịch nào ở bộ lọc này.</template>
                    </div>

                    <div v-else class="max-h-128 overflow-auto">
                        <table class="w-full text-sm">
                            <thead
                                class="sticky top-0 z-10 bg-muted/60 text-xs uppercase tracking-wide text-muted-foreground backdrop-blur"
                            >
                                <tr>
                                    <th class="px-3 py-2 text-left">Loại</th>
                                    <th class="px-3 py-2 text-right">Số tiền</th>
                                    <th class="px-3 py-2 text-left">Nguồn</th>
                                    <th class="px-3 py-2 text-right">Số dư tổng sau</th>
                                    <th class="px-3 py-2 text-left">Thời gian</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border/60">
                                <tr v-for="t in transactions.data" :key="t.id" class="align-top">
                                    <td class="px-3 py-2">
                                        <span
                                            class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[11px] font-semibold"
                                            :class="t.direction === 'credit'
                                                ? 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300'
                                                : 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300'"
                                        >
                                            <component
                                                :is="t.direction === 'credit' ? ArrowUpCircle : ArrowDownCircle"
                                                class="size-3"
                                            />
                                            {{ t.direction === 'credit' ? 'Nạp' : 'Chi' }}
                                        </span>
                                    </td>
                                    <td
                                        class="px-3 py-2 text-right font-mono font-bold"
                                        :class="t.direction === 'credit'
                                            ? 'text-emerald-700 dark:text-emerald-400'
                                            : 'text-rose-700 dark:text-rose-400'"
                                    >
                                        {{ t.direction === 'credit' ? '+' : '−' }}{{ formatVnd(t.amount_vnd) }}
                                    </td>
                                    <td class="px-3 py-2">
                                        <span
                                            class="inline-flex rounded-full border px-2 py-0.5 text-[11px] font-semibold"
                                            :class="sourceChipClass(t.source)"
                                        >
                                            {{ t.source_label }}
                                        </span>
                                        <p
                                            v-if="t.description"
                                            class="mt-1 text-[11px] text-muted-foreground"
                                        >
                                            {{ t.description }}
                                        </p>
                                    </td>
                                    <td class="px-3 py-2 text-right font-mono text-xs text-foreground/80">
                                        {{ formatVnd(t.balance_after_vnd) }}
                                    </td>
                                    <td class="px-3 py-2 text-xs text-muted-foreground">
                                        {{ t.created_at ?? '—' }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <Pagination
                        v-if="transactions.total > 0"
                        :meta="transactions"
                        :only="['user', 'transactions', 'list_totals', 'summary', 'filter']"
                        item-label="giao dịch"
                    />
                </div>
            </section>
        </div>
    </div>
</template>

<style scoped>
.user-card {
    border-radius: 1rem;
    border: 1px solid var(--border);
    background: var(--card);
    padding: 1rem 1.125rem;
    box-shadow: 0 6px 18px -12px rgba(13, 79, 158, 0.2);
}

:global(.dark) .user-card {
    box-shadow: 0 6px 20px -12px rgba(0, 0, 0, 0.55);
}

.user-eyebrow {
    font-size: 0.625rem;
    font-weight: 700;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: #0d4f9e;
}

:global(.dark) .user-eyebrow {
    color: #60a5fa;
}

.user-balance {
    display: inline-flex;
    align-items: center;
    gap: 0.625rem;
    padding: 0.625rem 0.875rem;
    border-radius: 0.75rem;
    background: linear-gradient(135deg, #0a3d7b 0%, #0d4f9e 55%, #1565c0 100%);
    color: #fdf8e8;
    border: 1.5px solid rgba(232, 165, 0, 0.55);
    box-shadow: inset 0 -2px 0 rgba(232, 165, 0, 0.55);
}

.balance-eyebrow {
    font-size: 0.625rem;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: #ffd766;
}

.filter-chip-adm {
    min-height: 2.5rem;
    border: 1.5px solid var(--border);
    border-radius: 0.625rem;
    background: var(--background);
    color: var(--foreground);
    font-size: 0.7rem;
    font-weight: 600;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
    transition:
        transform 100ms ease,
        border-color 150ms ease,
        background-color 150ms ease;
    cursor: pointer;
    text-align: center;
    line-height: 1.2;
}

.filter-chip-adm:hover {
    border-color: color-mix(in srgb, var(--primary) 50%, var(--border));
    background: color-mix(in srgb, var(--card) 85%, var(--primary));
}

.filter-chip-adm--active {
    border-color: #0d4f9e;
    background: #eff4fc;
    color: #0a3d7b;
    box-shadow: 0 0 0 1px rgba(13, 79, 158, 0.2);
}

:global(.dark) .filter-chip-adm--active {
    border-color: #3b82f6;
    background: rgba(30, 58, 138, 0.35);
    color: #dbeafe;
}

.filter-chip-adm--freeze.filter-chip-adm--active {
    border-color: #06b6d4;
    background: #ecfeff;
}

:global(.dark) .filter-chip-adm--freeze.filter-chip-adm--active {
    background: rgba(6, 182, 212, 0.2);
    color: #a5f3fc;
}

.filter-chip-adm-inner {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.25rem;
    padding: 0.4rem 0.35rem;
    width: 100%;
    min-height: 2.4rem;
}

.summary-tile {
    text-align: left;
    width: 100%;
    min-height: 4.5rem;
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 0.2rem;
    border-radius: 0.5rem;
    border-width: 1.5px;
    padding: 0.5rem 0.6rem;
    transition:
        box-shadow 120ms ease,
        border-color 120ms ease;
    cursor: pointer;
}

.summary-tile:hover {
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.08);
}

.summary-tile--on {
    box-shadow: 0 0 0 2px color-mix(in srgb, var(--ring) 45%, transparent);
    border-color: color-mix(in srgb, var(--primary) 40%, var(--border));
}

:global(.dark) .summary-tile--on {
    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.45);
}

.op-chip {
    flex: 1 1 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.375rem;
    padding: 0.5rem 0.75rem;
    border-radius: 0.5rem;
    border: 1.5px solid var(--border);
    background: var(--background);
    font-size: 0.8125rem;
    font-weight: 600;
    color: var(--foreground);
    cursor: pointer;
    transition: background-color 120ms ease, border-color 120ms ease, color 120ms ease;
}

.op-chip:hover {
    border-color: var(--primary);
}

.op-chip--credit-active {
    background: #ecfdf5;
    border-color: #10b981;
    color: #065f46;
}

.op-chip--debit-active {
    background: #fff1f2;
    border-color: #e11d48;
    color: #9f1239;
}

.op-chip--commission-active {
    background: #fdf4ff;
    border-color: #d946ef;
    color: #86198f;
}

:global(.dark) .op-chip--credit-active {
    background: rgba(16, 185, 129, 0.15);
    border-color: #10b981;
    color: #6ee7b7;
}

:global(.dark) .op-chip--debit-active {
    background: rgba(244, 63, 94, 0.15);
    border-color: #f43f5e;
    color: #fda4af;
}

:global(.dark) .op-chip--commission-active {
    background: rgba(217, 70, 239, 0.15);
    border-color: #d946ef;
    color: #f0abfc;
}

.op-chip--freeze-active {
    background: #ecfeff;
    border-color: #06b6d4;
    color: #155e75;
}

.op-chip--unfreeze-active {
    background: #eff6ff;
    border-color: #3b82f6;
    color: #1e3a8a;
}

:global(.dark) .op-chip--freeze-active {
    background: rgba(6, 182, 212, 0.15);
    border-color: #06b6d4;
    color: #a5f3fc;
}

:global(.dark) .op-chip--unfreeze-active {
    background: rgba(59, 130, 246, 0.15);
    border-color: #3b82f6;
    color: #bfdbfe;
}

.quick-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.3125rem 0.625rem;
    border-radius: 0.5rem;
    font-size: 0.75rem;
    font-weight: 700;
    background: #f3f7fc;
    color: #0d4f9e;
    border: 1px solid rgba(13, 79, 158, 0.15);
    transition: background-color 120ms ease, color 120ms ease, border-color 120ms ease;
}

.quick-btn:hover:not(:disabled) {
    background: #0d4f9e;
    color: #ffffff;
    border-color: #0d4f9e;
}

:global(.dark) .quick-btn {
    background: rgba(59, 130, 246, 0.1);
    color: #93c5fd;
    border-color: rgba(59, 130, 246, 0.3);
}

:global(.dark) .quick-btn:hover:not(:disabled) {
    background: #2563eb;
    color: #ffffff;
    border-color: #2563eb;
}

.quick-btn--max {
    border-style: dashed;
    border-color: rgba(180, 83, 9, 0.45);
    background: #fffbeb;
    color: #92400e;
}

.quick-btn--max:hover:not(:disabled) {
    background: #f59e0b;
    color: #ffffff;
    border-color: #d97706;
    border-style: solid;
}

.quick-btn--max:disabled {
    opacity: 0.45;
    cursor: not-allowed;
}

:global(.dark) .quick-btn--max {
    background: rgba(245, 158, 11, 0.12);
    color: #fcd34d;
    border-color: rgba(251, 191, 36, 0.45);
}

:global(.dark) .quick-btn--max:hover:not(:disabled) {
    background: #d97706;
    color: #fffbeb;
    border-color: #d97706;
}

.submit-credit {
    background: #10b981 !important;
    color: #ffffff !important;
}

.submit-credit:hover:not(:disabled) {
    background: #059669 !important;
}

.submit-debit {
    background: #e11d48 !important;
    color: #ffffff !important;
}

.submit-debit:hover:not(:disabled) {
    background: #be123c !important;
}

.submit-commission {
    background: #d946ef !important;
    color: #ffffff !important;
}

.submit-commission:hover:not(:disabled) {
    background: #a21caf !important;
}

.submit-freeze {
    background: #0891b2 !important;
    color: #ffffff !important;
}

.submit-freeze:hover:not(:disabled) {
    background: #0e7490 !important;
}

.submit-unfreeze {
    background: #2563eb !important;
    color: #ffffff !important;
}

.submit-unfreeze:hover:not(:disabled) {
    background: #1d4ed8 !important;
}
</style>
