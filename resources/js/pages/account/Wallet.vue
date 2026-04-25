<script setup lang="ts">
import AccountController from '@/actions/App/Http/Controllers/Client/AccountController';
import { formatVnd } from '@/lib/vnd';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowDownCircle, ArrowLeft, ArrowUpCircle, ChevronDown, Gift, History, Ticket, Wallet } from 'lucide-vue-next';
import { computed, ref } from 'vue';

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

type Pagination = { page: number; perPage: number; total: number; hasMore: boolean };

type Filter = 'all' | 'credit' | 'debit' | 'commission' | 'bet_place';

const props = defineProps<{
    balanceVnd: number;
    transactions: Tx[];
    pagination: Pagination;
    filter: Filter;
    sourceLabels: Record<string, string>;
}>();

const items = ref<Tx[]>([...props.transactions]);
const page = ref(props.pagination.page);
const hasMore = ref(props.pagination.hasMore);
const total = ref(props.pagination.total);
const filter = ref<Filter>(props.filter);
const loading = ref(false);
const loadError = ref<string | null>(null);

const FILTERS: Array<{ value: Filter; label: string }> = [
    { value: 'all', label: 'Tất cả' },
    { value: 'credit', label: 'Nạp tiền' },
    { value: 'bet_place', label: 'Lệ phí' },
    { value: 'commission', label: 'Hoa hồng' },
    { value: 'debit', label: 'Rút tiền' },
];

const totalCredit = computed(() =>
    items.value
        .filter((t) => t.direction === 'credit' && t.source !== 'commission')
        .reduce((s, t) => s + t.amount_vnd, 0),
);
const totalDebit = computed(() =>
    items.value
        .filter((t) => t.direction === 'debit' && t.source !== 'bet_place')
        .reduce((s, t) => s + t.amount_vnd, 0),
);
const totalCommission = computed(() =>
    items.value.filter((t) => t.source === 'commission').reduce((s, t) => s + t.amount_vnd, 0),
);
const totalBetPlace = computed(() =>
    items.value.filter((t) => t.source === 'bet_place').reduce((s, t) => s + t.amount_vnd, 0),
);

function txTitle(tx: Tx): string {
    if (tx.description && tx.description.trim() !== '') {
        return tx.description;
    }
    return tx.source_label;
}

function txIconClass(tx: Tx): string {
    if (tx.source === 'commission') return 'bg-fuchsia-100 text-fuchsia-700';
    if (tx.source === 'bet_place') return 'bg-blue-100 text-blue-700';
    if (tx.direction === 'credit') return 'bg-emerald-100 text-emerald-700';
    return 'bg-rose-100 text-rose-700';
}

function txAmountClass(tx: Tx): string {
    if (tx.source === 'commission') return 'text-fuchsia-700';
    if (tx.source === 'bet_place') return 'text-blue-700';
    if (tx.direction === 'credit') return 'text-emerald-700';
    return 'text-rose-700';
}

async function fetchPage(targetPage: number, replace: boolean) {
    if (loading.value) return;
    loading.value = true;
    loadError.value = null;
    try {
        const url = AccountController.walletData.url({ query: { page: targetPage, filter: filter.value } });
        const res = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = (await res.json()) as { data: Tx[]; total: number; hasMore: boolean; page: number };
        items.value = replace ? json.data : items.value.concat(json.data);
        total.value = json.total;
        hasMore.value = json.hasMore;
        page.value = json.page;
    } catch (e) {
        loadError.value = e instanceof Error ? e.message : 'Không tải được dữ liệu.';
    } finally {
        loading.value = false;
    }
}

function loadMore() {
    fetchPage(page.value + 1, false);
}

function setFilter(value: Filter) {
    if (filter.value === value) return;
    filter.value = value;
    fetchPage(1, true);
}

function formatDateTime(iso: string | null): string {
    if (!iso) return '—';
    try {
        return new Date(iso).toLocaleString('vi-VN', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    } catch {
        return iso;
    }
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
                <p class="text-[11px] uppercase tracking-wide text-stone-500">Số dư</p>
                <p class="font-mono text-sm font-bold text-stone-800">{{ formatVnd(balanceVnd) }}</p>
            </div>
        </div>

        <section class="rounded-2xl border border-stone-200 bg-white p-3 shadow-sm">
            <div class="flex items-center gap-2">
                <Wallet class="size-5 text-amber-700" />
                <h1 class="text-base font-bold text-stone-800">Lịch sử giao dịch</h1>
                <span class="ml-auto text-[11px] text-stone-500">{{ items.length }}/{{ total }}</span>
            </div>

            <div class="mt-2 grid grid-cols-5 gap-1">
                <button v-for="f in FILTERS" :key="f.value" type="button" class="filter-chip"
                    :class="{ 'is-active': filter === f.value }" @click="setFilter(f.value)">
                    {{ f.label }}
                </button>
            </div>

            <div class="mt-2 grid grid-cols-2 gap-2 text-[11px] sm:grid-cols-4">
                <div class="rounded-lg border border-emerald-200 bg-emerald-50/70 px-2 py-1.5 text-emerald-800">
                    <p class="flex items-center gap-1">
                        <ArrowUpCircle class="size-3" /> Nạp
                    </p>
                    <p class="font-mono text-sm font-bold">{{ formatVnd(totalCredit) }}</p>
                </div>
                <div class="rounded-lg border border-blue-200 bg-blue-50/70 px-2 py-1.5 text-blue-800">
                    <p class="flex items-center gap-1">
                        <Ticket class="size-3" /> Phí sự kiện
                    </p>
                    <p class="font-mono text-sm font-bold">{{ formatVnd(totalBetPlace) }}</p>
                </div>
                <div class="rounded-lg border border-fuchsia-200 bg-fuchsia-50/70 px-2 py-1.5 text-fuchsia-800">
                    <p class="flex items-center gap-1">
                        <Gift class="size-3" /> Hoa hồng
                    </p>
                    <p class="font-mono text-sm font-bold">{{ formatVnd(totalCommission) }}</p>
                </div>
                <div class="rounded-lg border border-rose-200 bg-rose-50/70 px-2 py-1.5 text-rose-800">
                    <p class="flex items-center gap-1">
                        <ArrowDownCircle class="size-3" /> Rút
                    </p>
                    <p class="font-mono text-sm font-bold">{{ formatVnd(totalDebit) }}</p>
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-stone-200 bg-white p-3 shadow-sm">
            <ul v-if="items.length" class="divide-y divide-stone-100">
                <li v-for="tx in items" :key="tx.id" class="flex items-start gap-2 py-2.5">
                    <span class="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-full"
                        :class="txIconClass(tx)">
                        <Gift v-if="tx.source === 'commission'" class="size-4" />
                        <Ticket v-else-if="tx.source === 'bet_place'" class="size-4" />
                        <ArrowUpCircle v-else-if="tx.direction === 'credit'" class="size-4" />
                        <ArrowDownCircle v-else class="size-4" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold text-stone-800">
                            {{ txTitle(tx) }}
                        </p>
                        <div class="mt-0.5 flex flex-wrap items-center gap-1.5 text-[11px] text-stone-500">
                            <span class="rounded border px-1.5 py-px text-[10px] font-semibold" :class="tx.source === 'commission'
                                ? 'border-fuchsia-200 bg-fuchsia-50 text-fuchsia-700'
                                : tx.source === 'bet_place'
                                    ? 'border-blue-200 bg-blue-50 text-blue-700'
                                    : tx.direction === 'credit'
                                        ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                                        : 'border-rose-200 bg-rose-50 text-rose-700'">
                                {{ tx.source_label }}
                            </span>
                            <span>{{ formatDateTime(tx.created_at) }}</span>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="font-mono text-sm font-bold" :class="txAmountClass(tx)">
                            {{ tx.direction === 'credit' ? '+' : '−' }}{{ formatVnd(tx.amount_vnd) }}
                        </p>
                        <p class="font-mono text-[10px] text-stone-500">SD: {{ formatVnd(tx.balance_after_vnd) }}</p>
                    </div>
                </li>
            </ul>

            <p v-else
                class="rounded-xl border border-dashed border-stone-200 bg-stone-50/40 px-3 py-8 text-center text-xs text-stone-500">
                <History class="mx-auto mb-1 size-5 text-stone-400" />
                Chưa có giao dịch nào ở bộ lọc này.
            </p>

            <div v-if="hasMore" class="mt-3">
                <button type="button"
                    class="inline-flex w-full items-center justify-center gap-1.5 rounded-xl border border-stone-200 bg-white px-3 py-2 text-sm font-semibold text-stone-700 shadow-sm transition hover:bg-stone-50 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="loading" @click="loadMore">
                    <ChevronDown v-if="!loading" class="size-4" />
                    {{ loading ? 'Đang tải…' : `Xem thêm (còn ${total - items.length})` }}
                </button>
            </div>
            <p v-if="loadError" class="mt-1 text-[11px] text-rose-600">{{ loadError }}</p>
        </section>
    </div>
</template>

<style scoped>
.filter-chip {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    height: 2.25rem;
    padding: 0 2px;
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

.filter-chip:hover {
    border-color: rgb(252 211 77);
    background: rgb(255 251 235);
    color: rgb(146 64 14);
}

.filter-chip:active {
    transform: scale(0.97);
}

.filter-chip.is-active {
    border-color: rgb(217 119 6);
    background: rgb(254 243 199);
    color: rgb(146 64 14);
    box-shadow: 0 0 0 3px rgb(254 243 199);
}
</style>
