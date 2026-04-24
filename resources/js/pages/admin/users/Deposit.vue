<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ArrowDownCircle, ArrowLeft, ArrowUpCircle, Coins, Gift, RotateCcw, Wallet } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
import CurrencyInput from '@/components/CurrencyInput.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
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

const props = defineProps<{
    user: {
        id: number;
        name: string;
        username: string;
        email: string;
        balance_vnd: number;
        role: string;
    };
    transactions: Txn[];
    summary: {
        credit_total: number;
        credit_count: number;
        debit_total: number;
        debit_count: number;
        commission_total: number;
        commission_count: number;
    };
}>();

const quickAmounts = [100_000, 500_000, 1_000_000, 5_000_000, 10_000_000];

type Operation = 'credit' | 'debit' | 'commission';

const DEFAULT_NOTE: Record<Operation, string> = {
    credit: 'Nạp tiền thành công',
    debit: 'Rút tiền thành công',
    commission: 'Thưởng hoa hồng',
};

const adjustAmount = ref<number>(0);
const adjustOperation = ref<Operation>('credit');
const adjustNote = ref<string>(DEFAULT_NOTE.credit);

const netTotal = computed(() => props.summary.credit_total - props.summary.debit_total);

function pickQuick(v: number) {
    adjustAmount.value = v;
}

function resetAdjust() {
    adjustAmount.value = 0;
    adjustOperation.value = 'credit';
    adjustNote.value = DEFAULT_NOTE.credit;
}

watch(adjustOperation, (next, prev) => {
    const prevDefault = DEFAULT_NOTE[prev];
    if (!adjustNote.value || adjustNote.value === prevDefault) {
        adjustNote.value = DEFAULT_NOTE[next];
    }
});

const submitLabel = computed(() => {
    if (adjustOperation.value === 'credit') return 'Xác nhận nạp';
    if (adjustOperation.value === 'debit') return 'Xác nhận trừ';
    return 'Xác nhận hoa hồng';
});

const submitClass = computed(() => {
    if (adjustOperation.value === 'credit') return 'submit-credit';
    if (adjustOperation.value === 'debit') return 'submit-debit';
    return 'submit-commission';
});

function formatDate(iso: string | null): string {
    if (!iso) return '—';
    try {
        return new Date(iso).toLocaleString('vi-VN', { hour12: false });
    } catch {
        return iso;
    }
}

function sourceChipClass(src: string): string {
    if (src === 'admin_credit')
        return 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300';
    if (src === 'admin_debit')
        return 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300';
    if (src === 'commission')
        return 'border-fuchsia-200 bg-fuchsia-50 text-fuchsia-800 dark:border-fuchsia-500/30 dark:bg-fuchsia-500/10 dark:text-fuchsia-300';
    if (src === 'bet_place')
        return 'border-sky-200 bg-sky-50 text-sky-800 dark:border-sky-500/30 dark:bg-sky-500/10 dark:text-sky-300';
    if (src === 'bet_cancel')
        return 'border-indigo-200 bg-indigo-50 text-indigo-800 dark:border-indigo-500/30 dark:bg-indigo-500/10 dark:text-indigo-300';
    if (src === 'withdrawal')
        return 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300';
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
            <Button variant="outline" as-child>
                <Link :href="UserController.index.url()">
                    <ArrowLeft class="size-4" /> Quay lại danh sách
                </Link>
            </Button>
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
                    <div class="text-right leading-none">
                        <p class="balance-eyebrow">Số dư hiện tại</p>
                        <p class="mt-1 font-mono text-2xl font-extrabold">{{ formatVnd(user.balance_vnd) }}</p>
                    </div>
                </div>
            </div>

            <div class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-4">
                <div class="stat-chip stat-credit">
                    <ArrowUpCircle class="size-4" />
                    <div class="min-w-0">
                        <p class="stat-eyebrow">Tổng nạp</p>
                        <p class="stat-amount">{{ formatVnd(summary.credit_total) }}</p>
                        <p class="stat-sub">{{ summary.credit_count }} giao dịch</p>
                    </div>
                </div>
                <div class="stat-chip stat-debit">
                    <ArrowDownCircle class="size-4" />
                    <div class="min-w-0">
                        <p class="stat-eyebrow">Tổng rút</p>
                        <p class="stat-amount">{{ formatVnd(summary.debit_total) }}</p>
                        <p class="stat-sub">{{ summary.debit_count }} giao dịch</p>
                    </div>
                </div>
                <div class="stat-chip stat-commission">
                    <Gift class="size-4" />
                    <div class="min-w-0">
                        <p class="stat-eyebrow">Hoa hồng</p>
                        <p class="stat-amount">{{ formatVnd(summary.commission_total) }}</p>
                        <p class="stat-sub">{{ summary.commission_count }} lượt thưởng</p>
                    </div>
                </div>
                <div class="stat-chip stat-net">
                    <Coins class="size-4" />
                    <div class="min-w-0">
                        <p class="stat-eyebrow">Chênh lệch</p>
                        <p
                            class="stat-amount"
                            :class="netTotal >= 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-700 dark:text-rose-400'"
                        >
                            {{ netTotal >= 0 ? '+' : '' }}{{ formatVnd(netTotal) }}
                        </p>
                        <p class="stat-sub">Nạp − Chi</p>
                    </div>
                </div>
            </div>
        </section>

        <div class="grid gap-4 lg:grid-cols-5">
            <section class="lg:col-span-2">
                <div class="rounded-xl border border-border/60 bg-card p-4 shadow-sm">
                    <h3 class="text-base font-bold text-foreground">Nạp / trừ số dư</h3>
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        Chọn nhanh số tiền hoặc nhập tuỳ ý. Ghi chú sẽ được lưu vào lịch sử.
                    </p>

                    <Form v-bind="UserController.adjustBalance.form({ user: user.id })" class="mt-4 space-y-3"
                        v-slot="{ errors, processing }" @success="resetAdjust">
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
                            <InputError :message="errors.operation" />
                        </div>

                        <div class="grid gap-1.5">
                            <Label for="amount_vnd">Số tiền (VNĐ)</Label>
                            <CurrencyInput id="amount_vnd" v-model="adjustAmount" name="amount_vnd"
                                placeholder="VD: 100.000" :aria-invalid="!!errors.amount_vnd" />
                            <InputError :message="errors.amount_vnd" />
                            <div class="flex flex-wrap gap-1.5 pt-1">
                                <button v-for="v in quickAmounts" :key="v" type="button" class="quick-btn"
                                    @click="pickQuick(v)">
                                    {{ formatVnd(v) }}
                                </button>
                            </div>
                        </div>

                        <div class="grid gap-1.5">
                            <Label for="note">Ghi chú</Label>
                            <Input
                                id="note"
                                v-model="adjustNote"
                                name="note"
                                maxlength="255"
                                :placeholder="DEFAULT_NOTE[adjustOperation]"
                            />
                            <p class="text-[11px] text-muted-foreground">
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
                                :disabled="processing || adjustAmount <= 0">
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
                            <p class="text-xs text-muted-foreground">100 giao dịch gần nhất</p>
                        </div>
                        <span class="font-mono text-xs text-muted-foreground">
                            #{{ transactions.length }}
                        </span>
                    </header>

                    <div
                        v-if="transactions.length === 0"
                        class="px-4 py-10 text-center text-sm text-muted-foreground"
                    >
                        Chưa có giao dịch nào.
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
                                    <th class="px-3 py-2 text-right">Số dư sau</th>
                                    <th class="px-3 py-2 text-left">Thời gian</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border/60">
                                <tr v-for="t in transactions" :key="t.id" class="align-top">
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
                                        {{ formatDate(t.created_at) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
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

.stat-chip {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.625rem 0.75rem;
    border-radius: 0.625rem;
    border: 1px solid;
}

.stat-credit {
    background: #ecfdf5;
    border-color: #a7f3d0;
    color: #065f46;
}

.stat-debit {
    background: #fff1f2;
    border-color: #fecaca;
    color: #9f1239;
}

.stat-commission {
    background: #fdf4ff;
    border-color: #f0abfc;
    color: #86198f;
}

.stat-net {
    background: #f3f7fc;
    border-color: #dbe4ed;
    color: #0d4f9e;
}

:global(.dark) .stat-credit {
    background: rgba(16, 185, 129, 0.12);
    border-color: rgba(16, 185, 129, 0.35);
    color: #6ee7b7;
}

:global(.dark) .stat-debit {
    background: rgba(244, 63, 94, 0.12);
    border-color: rgba(244, 63, 94, 0.35);
    color: #fda4af;
}

:global(.dark) .stat-commission {
    background: rgba(217, 70, 239, 0.12);
    border-color: rgba(217, 70, 239, 0.35);
    color: #f0abfc;
}

:global(.dark) .stat-net {
    background: rgba(59, 130, 246, 0.1);
    border-color: rgba(59, 130, 246, 0.3);
    color: #93c5fd;
}

.stat-eyebrow {
    font-size: 0.625rem;
    font-weight: 700;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    opacity: 0.85;
}

.stat-amount {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace;
    font-size: 0.9375rem;
    font-weight: 800;
    line-height: 1.15;
}

.stat-sub {
    font-size: 0.625rem;
    opacity: 0.75;
    margin-top: 0.125rem;
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
</style>
