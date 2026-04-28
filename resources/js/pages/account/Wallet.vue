<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowDownCircle,
    ArrowLeft,
    ArrowUpCircle,
    ChevronLeft,
    ChevronRight,
    Gift,
    History,
    Lock,
    RotateCcw,
    Snowflake,
    Ticket,
    Unlock,
    Wallet,
} from 'lucide-vue-next';
import { computed, nextTick, ref, watch } from 'vue';
import AccountController from '@/actions/App/Http/Controllers/Client/AccountController';
import { formatVnd } from '@/lib/vnd';

type Tx = {
    id: number;
    direction: 'credit' | 'debit';
    source: string;
    source_label: string;
    amount_vnd: number;
    balance_after_vnd: number;
    description: string | null;
    created_at: string | null;
    meta: Record<string, unknown>;
};

type Pagination = { page: number; perPage: number; total: number; lastPage: number };

type ListTotals = {
    adminCreditVnd: number;
    refundVnd: number;
    betPlaceVnd: number;
    commissionVnd: number;
    outDebitVnd: number;
    freezeVnd: number;
};

type Filter = 'all' | 'credit' | 'refund' | 'debit' | 'commission' | 'bet_place' | 'freeze';

const props = defineProps<{
    balanceVnd: number;
    frozenVnd: number;
    availableVnd: number;
    listTotals: ListTotals;
    transactions: Tx[];
    pagination: Pagination;
    filter: Filter;
    sourceLabels: Record<string, string>;
}>();

const items = ref<Tx[]>([...props.transactions]);
const page = ref(props.pagination.page);
const lastPage = ref(props.pagination.lastPage);
const total = ref(props.pagination.total);
const filter = ref<Filter>(props.filter);
const loading = ref(false);
const loadError = ref<string | null>(null);

const FILTERS: Array<{ value: Filter; label: string; icon?: 'lock' }> = [
    { value: 'all', label: 'Tất cả' },
    { value: 'credit', label: 'Nạp tiền' },
    { value: 'refund', label: 'Hoàn trả' },
    { value: 'debit', label: 'Rút & trừ' },
    { value: 'bet_place', label: 'Lệ phí' },
    { value: 'commission', label: 'Hoa hồng' },
    { value: 'freeze', label: 'Đóng băng', icon: 'lock' },
];

/** Số đang đóng băng (users.frozen_vnd), dùng cho thẻ tóm tắt & dòng phụ dưới «Khả dụng». */
const showFreezeUi = computed(() => props.frozenVnd > 0);

const filterChips = computed(() => {
    if (showFreezeUi.value) {
        return FILTERS;
    }

    return FILTERS.filter((f) => f.value !== 'freeze');
});

function syncFromProps() {
    items.value = [...props.transactions];
    page.value = props.pagination.page;
    lastPage.value = props.pagination.lastPage;
    total.value = props.pagination.total;

    if (props.frozenVnd <= 0 && props.filter === 'freeze') {
        filter.value = 'all';
        void nextTick(() => {
            void loadPage(1);
        });
        return;
    }

    filter.value = props.filter;
}

const showPagination = computed(() => lastPage.value > 1);

watch(
    () => [props.transactions, props.filter, props.pagination] as const,
    () => syncFromProps(),
    { deep: true },
);

function isRefundSource(source: string): boolean {
    return source === 'bet_cancel' || source === 'event_refund';
}

async function loadPage(targetPage: number) {
    if (loading.value) {
        return;
    }
    if (targetPage < 1) {
        return;
    }
    if (lastPage.value > 0 && targetPage > lastPage.value) {
        return;
    }
    if (filter.value === 'freeze' && props.frozenVnd <= 0) {
        filter.value = 'all';
    }

    loading.value = true;
    loadError.value = null;

    try {
        const url = AccountController.walletData.url({ query: { page: targetPage, filter: filter.value } });
        const res = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });

        if (!res.ok) {
            throw new Error('HTTP ' + res.status);
        }

        const json = (await res.json()) as {
            data: Tx[];
            page: number;
            perPage: number;
            total: number;
            lastPage: number;
            filter: string;
        };

        items.value = json.data;
        page.value = json.page;
        total.value = json.total;
        lastPage.value = json.lastPage;
    } catch (e) {
        loadError.value = e instanceof Error ? e.message : 'Không tải được dữ liệu.';
    } finally {
        loading.value = false;
    }
}

function setFilter(value: Filter) {
    if (filter.value === value) {
        return;
    }

    filter.value = value;
    void loadPage(1);
}

function txTitle(tx: Tx): string {
    if (tx.description && tx.description.trim() !== '') {
        return tx.description;
    }

    return tx.source_label;
}

function txIconClass(tx: Tx): string {
    if (tx.source === 'admin_freeze') {
        return 'bg-cyan-100 text-cyan-800 ring-1 ring-cyan-200';
    }
    if (tx.source === 'admin_unfreeze') {
        return 'bg-sky-100 text-sky-800 ring-1 ring-sky-200';
    }

    if (tx.source === 'commission') {
        return 'bg-fuchsia-100 text-fuchsia-700';
    }

    if (tx.source === 'bet_place') {
        return 'bg-blue-100 text-blue-700';
    }

    if (isRefundSource(tx.source)) {
        return 'bg-amber-100 text-amber-800';
    }

    if (tx.source === 'admin_credit' || (tx.direction === 'credit' && !isRefundSource(tx.source))) {
        return 'bg-emerald-100 text-emerald-700';
    }

    return 'bg-rose-100 text-rose-700';
}

function sourcePillClass(tx: Tx): string {
    if (tx.source === 'admin_freeze') {
        return 'border-cyan-200 bg-cyan-50 text-cyan-900';
    }
    if (tx.source === 'admin_unfreeze') {
        return 'border-sky-200 bg-sky-50 text-sky-900';
    }
    if (tx.source === 'commission') {
        return 'border-fuchsia-200 bg-fuchsia-50 text-fuchsia-700';
    }
    if (tx.source === 'bet_place') {
        return 'border-blue-200 bg-blue-50 text-blue-700';
    }
    if (isRefundSource(tx.source)) {
        return 'border-amber-200 bg-amber-50 text-amber-800';
    }
    if (tx.direction === 'credit') {
        return 'border-emerald-200 bg-emerald-50 text-emerald-700';
    }

    return 'border-rose-200 bg-rose-50 text-rose-700';
}

function txAmountClass(tx: Tx): string {
    if (tx.source === 'admin_freeze') {
        return 'text-cyan-800';
    }
    if (tx.source === 'admin_unfreeze') {
        return 'text-sky-800';
    }
    if (tx.source === 'commission') {
        return 'text-fuchsia-700';
    }
    if (tx.source === 'bet_place') {
        return 'text-blue-700';
    }
    if (isRefundSource(tx.source)) {
        return 'text-amber-800';
    }
    if (tx.direction === 'credit') {
        return 'text-emerald-700';
    }

    return 'text-rose-700';
}
</script>

<template>

    <Head title="Lịch sử giao dịch" />

    <div class="space-y-3 px-3 pb-24 pt-3">
        <div class="flex items-center justify-between">
            <Link :href="AccountController.show.url()"
                class="inline-flex items-center gap-1 text-sm font-medium text-amber-700 active:opacity-70">
                <ArrowLeft class="size-4" />
                Tài khoản
            </Link>
            <div class="text-right">
                <p class="text-[11px] uppercase tracking-wide text-stone-500">Khả dụng</p>
                <p class="font-mono text-sm font-bold text-stone-800">{{ formatVnd(availableVnd) }}</p>
                <p v-if="showFreezeUi" class="text-[10px] text-stone-500">
                    Đang đóng băng {{ formatVnd(frozenVnd) }}
                </p>
            </div>
        </div>

        <section class="rounded-2xl border border-stone-200 bg-white p-3 shadow-sm">
            <div class="flex items-center gap-2">
                <Wallet class="size-5 text-amber-700" />
                <h1 class="text-base font-bold text-stone-800">Lịch sử giao dịch</h1>
            </div>


            <div class="mt-2 grid grid-cols-2 gap-1 min-[500px]:grid-cols-3 min-[800px]:grid-cols-4"
                :class="showFreezeUi ? 'min-[1000px]:grid-cols-7' : 'min-[1000px]:grid-cols-6'">
                <button v-for="f in filterChips" :key="f.value" type="button" class="filter-chip"
                    :class="{ 'is-active': filter === f.value, 'is-freeze': f.value === 'freeze' }" :disabled="loading"
                    @click="setFilter(f.value)">
                    <span class="filter-chip-inner">
                        <Lock v-if="f.icon === 'lock'" class="size-3 shrink-0" />
                        <span>{{ f.label }}</span>
                    </span>
                </button>
            </div>

            <div class="mt-2 grid grid-cols-2 gap-2 text-[11px] sm:grid-cols-3"
                :class="showFreezeUi ? 'min-[800px]:grid-cols-6' : 'min-[800px]:grid-cols-5'">
                <div class="rounded-lg border border-emerald-200 bg-emerald-50/70 px-2 py-1.5 text-emerald-800">
                    <p class="flex items-center gap-1">
                        <ArrowUpCircle class="size-3" /> Nạp
                    </p>
                    <p class="font-mono text-sm font-bold">{{ formatVnd(listTotals.adminCreditVnd) }}</p>
                </div>
                <div class="rounded-lg border border-amber-200 bg-amber-50/70 px-2 py-1.5 text-amber-900">
                    <p class="flex items-center gap-1">
                        <RotateCcw class="size-3" /> Hoàn trả
                    </p>
                    <p class="font-mono text-sm font-bold">{{ formatVnd(listTotals.refundVnd) }}</p>
                </div>
                <div class="rounded-lg border border-rose-200 bg-rose-50/70 px-2 py-1.5 text-rose-800">
                    <p class="flex items-center gap-1">
                        <ArrowDownCircle class="size-3" /> Rút &amp; trừ
                    </p>
                    <p class="font-mono text-sm font-bold">{{ formatVnd(listTotals.outDebitVnd) }}</p>
                </div>
                <div class="rounded-lg border border-blue-200 bg-blue-50/70 px-2 py-1.5 text-blue-800">
                    <p class="flex items-center gap-1">
                        <Ticket class="size-3" /> Phí sự kiện
                    </p>
                    <p class="font-mono text-sm font-bold">{{ formatVnd(listTotals.betPlaceVnd) }}</p>
                </div>
                <div class="rounded-lg border border-fuchsia-200 bg-fuchsia-50/70 px-2 py-1.5 text-fuchsia-800">
                    <p class="flex items-center gap-1">
                        <Gift class="size-3" /> Hoa hồng
                    </p>
                    <p class="font-mono text-sm font-bold">{{ formatVnd(listTotals.commissionVnd) }}</p>
                </div>

                <div v-if="showFreezeUi"
                    class="rounded-lg border border-cyan-200 bg-cyan-50/80 px-2 py-1.5 text-cyan-900">
                    <p class="flex items-center gap-1">
                        <Lock class="size-3" /> Đang đóng băng
                    </p>
                    <p class="font-mono text-sm font-bold">{{ formatVnd(listTotals.freezeVnd) }}</p>
                    <p class="mt-0.5 text-[10px] leading-tight text-cyan-950/80">Số khóa hiện tại</p>
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-stone-200 bg-white p-3 shadow-sm">
            <p class="text-[11px] text-stone-500">
                Đang xem: <span class="font-mono font-semibold text-stone-700">{{
                    items.length }}</span> / <span class="font-mono">{{ total }}</span> bản ghi
                (trang {{ page }}/{{ lastPage }})
            </p>

            <ul v-if="items.length" class="divide-y divide-stone-100">
                <li v-for="tx in items" :key="tx.id" class="flex items-start gap-2 py-2.5">
                    <span class="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-full"
                        :class="txIconClass(tx)">
                        <Lock v-if="tx.source === 'admin_freeze'" class="size-4" />
                        <Unlock v-else-if="tx.source === 'admin_unfreeze'" class="size-4" />
                        <Gift v-else-if="tx.source === 'commission'" class="size-4" />
                        <Ticket v-else-if="tx.source === 'bet_place'" class="size-4" />
                        <RotateCcw v-else-if="isRefundSource(tx.source)" class="size-4" />
                        <ArrowUpCircle v-else-if="tx.direction === 'credit'" class="size-4" />
                        <ArrowDownCircle v-else class="size-4" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold text-stone-800">
                            {{ txTitle(tx) }}
                        </p>
                        <div class="mt-0.5 flex flex-wrap items-center gap-1.5 text-[11px] text-stone-500">
                            <span
                                class="inline-flex max-w-full items-center gap-0.5 rounded border px-1.5 py-px text-[10px] font-semibold"
                                :class="sourcePillClass(tx)">
                                <Snowflake v-if="tx.source === 'admin_freeze'" class="size-2.5 shrink-0" />
                                <span class="min-w-0">{{ tx.source_label }}</span>
                            </span>
                            <span>{{ tx.created_at ?? '—' }}</span>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="font-mono text-sm font-bold" :class="txAmountClass(tx)">
                            {{ tx.direction === 'credit' ? '+' : '−' }}{{ formatVnd(tx.amount_vnd) }}
                        </p>
                        <p class="font-mono text-[10px] text-stone-500">Tổng SD: {{ formatVnd(tx.balance_after_vnd) }}
                        </p>
                    </div>
                </li>
            </ul>

            <p v-else
                class="rounded-xl border border-dashed border-stone-200 bg-stone-50/40 px-3 py-8 text-center text-xs text-stone-500">
                <History class="mx-auto mb-1 size-5 text-stone-400" />
                Chưa có giao dịch nào ở bộ lọc này.
            </p>

            <div v-if="showPagination" class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <p v-if="loadError" class="text-xs text-rose-600 sm:order-2">{{ loadError }}</p>
                <div
                    class="inline-flex w-full max-w-sm items-stretch overflow-hidden rounded-xl border border-stone-200 bg-stone-50/80 shadow-sm sm:order-1 sm:w-auto">
                    <button type="button" class="page-pill-btn flex-1" :class="{ 'is-disabled': page <= 1 || loading }"
                        :disabled="page <= 1 || loading" aria-label="Trang trước" @click="loadPage(page - 1)">
                        <ChevronLeft class="mx-auto size-4" />
                    </button>
                    <span
                        class="flex flex-1 items-center justify-center px-1 py-1.5 text-center text-xs font-medium text-stone-700">
                        <template v-if="loading">Đang tải…</template>
                        <template v-else>Trang {{ page }} / {{ lastPage }}</template>
                    </span>
                    <button type="button" class="page-pill-btn flex-1"
                        :class="{ 'is-disabled': page >= lastPage || loading }" :disabled="page >= lastPage || loading"
                        aria-label="Trang sau" @click="loadPage(page + 1)">
                        <ChevronRight class="mx-auto size-4" />
                    </button>
                </div>
            </div>
            <p v-else-if="loadError" class="mt-2 text-xs text-rose-600">{{ loadError }}</p>
        </section>
    </div>
</template>

<style scoped>
.filter-chip {
    min-height: 2.5rem;
    border: 1.5px solid rgb(231 229 228);
    border-radius: 0.625rem;
    background: white;
    color: rgb(68 64 60);
    font-size: 0.7rem;
    font-weight: 600;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
    transition: transform 100ms ease, border-color 150ms ease, background-color 150ms ease, color 150ms ease;
    cursor: pointer;
}

.filter-chip:disabled {
    cursor: not-allowed;
    opacity: 0.6;
}

.filter-chip-inner {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.25rem;
    padding: 0.375rem 0.25rem;
    width: 100%;
    text-align: center;
    line-height: 1.2;
}

.filter-chip:hover:not(:disabled) {
    border-color: rgb(252 211 77);
    background: rgb(255 251 235);
    color: rgb(146 64 14);
}

.filter-chip:active:not(:disabled) {
    transform: scale(0.98);
}

.filter-chip.is-active {
    border-color: rgb(217 119 6);
    background: rgb(254 243 199);
    color: rgb(146 64 14);
    box-shadow: 0 0 0 2px rgb(254 243 199);
}

.filter-chip.is-freeze.is-active {
    border-color: rgb(6 182 212);
    background: rgb(204 251 241);
    color: rgb(22 78 99);
    box-shadow: 0 0 0 2px rgba(6, 182, 212, 0.25);
}

.page-pill-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 2.5rem;
    background: white;
    color: rgb(68 64 60);
    transition: background 120ms ease, color 120ms ease;
}

.page-pill-btn:hover:not(:disabled) {
    background: rgb(250 250 249);
    color: rgb(120 53 15);
}

.page-pill-btn:disabled,
.page-pill-btn.is-disabled {
    opacity: 0.4;
    cursor: not-allowed;
}
</style>
