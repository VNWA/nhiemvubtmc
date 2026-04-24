<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ArrowDownCircle, ArrowLeft, ArrowUpCircle, Coins, RotateCcw, Wallet } from 'lucide-vue-next';
import { computed, ref } from 'vue';
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
    };
}>();

const quickAmounts = [100_000, 500_000, 1_000_000, 5_000_000, 10_000_000];

const adjustAmount = ref<number>(0);
const adjustOperation = ref<'credit' | 'debit'>('credit');

const netTotal = computed(() => props.summary.credit_total - props.summary.debit_total);

function pickQuick(v: number) {
    adjustAmount.value = v;
}

function resetAdjust() {
    adjustAmount.value = 0;
    adjustOperation.value = 'credit';
}

function formatDate(iso: string | null): string {
    if (!iso) return '—';
    try {
        return new Date(iso).toLocaleString('vi-VN', { hour12: false });
    } catch {
        return iso;
    }
}

function sourceChipClass(src: string): string {
    if (src === 'admin_credit') return 'bg-emerald-50 text-emerald-800 border-emerald-200';
    if (src === 'admin_debit') return 'bg-rose-50 text-rose-700 border-rose-200';
    if (src === 'bet_place') return 'bg-sky-50 text-sky-800 border-sky-200';
    if (src === 'bet_cancel') return 'bg-indigo-50 text-indigo-800 border-indigo-200';
    if (src === 'withdrawal') return 'bg-amber-50 text-amber-800 border-amber-200';
    return 'bg-stone-50 text-stone-700 border-stone-200';
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
            <Heading
                variant="small"
                title="Nạp / trừ số dư người dùng"
                description="Điều chỉnh số dư và theo dõi toàn bộ lịch sử giao dịch của tài khoản."
            />
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
                    <h2 class="mt-0.5 text-lg font-bold text-stone-900">{{ user.name }}</h2>
                    <p class="mt-0.5 text-xs text-stone-500">
                        <span class="font-mono">@{{ user.username }}</span>
                        <span class="mx-1.5">·</span>
                        <span class="inline-flex rounded-md bg-stone-100 px-2 py-0.5 text-[11px] capitalize text-stone-700">
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

            <div class="mt-4 grid grid-cols-3 gap-2">
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
                        <p class="stat-eyebrow">Tổng chi</p>
                        <p class="stat-amount">{{ formatVnd(summary.debit_total) }}</p>
                        <p class="stat-sub">{{ summary.debit_count }} giao dịch</p>
                    </div>
                </div>
                <div class="stat-chip stat-net">
                    <Coins class="size-4" />
                    <div class="min-w-0">
                        <p class="stat-eyebrow">Chênh lệch</p>
                        <p class="stat-amount" :class="netTotal >= 0 ? 'text-emerald-700' : 'text-rose-700'">
                            {{ netTotal >= 0 ? '+' : '' }}{{ formatVnd(netTotal) }}
                        </p>
                        <p class="stat-sub">Nạp − Chi</p>
                    </div>
                </div>
            </div>
        </section>

        <div class="grid gap-4 lg:grid-cols-5">
            <section class="lg:col-span-2">
                <div class="rounded-xl border border-stone-200 bg-white p-4 shadow-sm">
                    <h3 class="text-base font-bold text-stone-900">Nạp / trừ số dư</h3>
                    <p class="mt-0.5 text-xs text-stone-500">
                        Chọn nhanh số tiền hoặc nhập tuỳ ý. Ghi chú sẽ được lưu vào lịch sử.
                    </p>

                    <Form
                        v-bind="UserController.adjustBalance.form({ user: user.id })"
                        class="mt-4 space-y-3"
                        v-slot="{ errors, processing }"
                        @success="resetAdjust"
                    >
                        <div class="grid gap-1.5">
                            <Label>Loại điều chỉnh</Label>
                            <div class="flex gap-2">
                                <label
                                    class="op-chip"
                                    :class="adjustOperation === 'credit' ? 'op-chip--credit-active' : ''"
                                >
                                    <input
                                        type="radio"
                                        name="operation"
                                        value="credit"
                                        class="hidden"
                                        :checked="adjustOperation === 'credit'"
                                        @change="adjustOperation = 'credit'"
                                    />
                                    <ArrowUpCircle class="size-4" /> Nạp tiền
                                </label>
                                <label
                                    class="op-chip"
                                    :class="adjustOperation === 'debit' ? 'op-chip--debit-active' : ''"
                                >
                                    <input
                                        type="radio"
                                        name="operation"
                                        value="debit"
                                        class="hidden"
                                        :checked="adjustOperation === 'debit'"
                                        @change="adjustOperation = 'debit'"
                                    />
                                    <ArrowDownCircle class="size-4" /> Trừ tiền
                                </label>
                            </div>
                            <InputError :message="errors.operation" />
                        </div>

                        <div class="grid gap-1.5">
                            <Label for="amount_vnd">Số tiền (VNĐ)</Label>
                            <CurrencyInput
                                id="amount_vnd"
                                v-model="adjustAmount"
                                name="amount_vnd"
                                placeholder="VD: 100.000"
                                :aria-invalid="!!errors.amount_vnd"
                            />
                            <InputError :message="errors.amount_vnd" />
                            <div class="flex flex-wrap gap-1.5 pt-1">
                                <button
                                    v-for="v in quickAmounts"
                                    :key="v"
                                    type="button"
                                    class="quick-btn"
                                    @click="pickQuick(v)"
                                >
                                    {{ formatVnd(v) }}
                                </button>
                            </div>
                        </div>

                        <div class="grid gap-1.5">
                            <Label for="note">Ghi chú (tuỳ chọn)</Label>
                            <Input id="note" name="note" maxlength="255" placeholder="VD: Nạp thưởng sự kiện" />
                            <InputError :message="errors.note" />
                        </div>

                        <div class="flex items-center gap-2 pt-1">
                            <Button
                                type="button"
                                variant="outline"
                                class="flex-1"
                                :disabled="processing"
                                @click="resetAdjust"
                            >
                                <RotateCcw class="size-4" /> Đặt lại
                            </Button>
                            <Button
                                type="submit"
                                class="flex-[1.4]"
                                :class="adjustOperation === 'credit' ? 'submit-credit' : 'submit-debit'"
                                :disabled="processing || adjustAmount <= 0"
                            >
                                <Spinner v-if="processing" />
                                {{ adjustOperation === 'credit' ? 'Xác nhận nạp' : 'Xác nhận trừ' }}
                            </Button>
                        </div>
                    </Form>
                </div>
            </section>

            <section class="lg:col-span-3">
                <div class="overflow-hidden rounded-xl border border-stone-200 bg-white shadow-sm">
                    <header class="flex items-center justify-between border-b border-stone-100 px-4 py-2.5">
                        <div>
                            <h3 class="text-sm font-bold text-stone-900">Lịch sử giao dịch</h3>
                            <p class="text-xs text-stone-500">100 giao dịch gần nhất</p>
                        </div>
                        <span class="text-xs font-mono text-stone-500">#{{ transactions.length }}</span>
                    </header>

                    <div v-if="transactions.length === 0" class="px-4 py-10 text-center text-sm text-stone-500">
                        Chưa có giao dịch nào.
                    </div>

                    <div v-else class="max-h-128 overflow-auto">
                        <table class="w-full text-sm">
                            <thead class="sticky top-0 z-10 bg-stone-50 text-xs uppercase tracking-wide text-stone-500">
                                <tr>
                                    <th class="px-3 py-2 text-left">Loại</th>
                                    <th class="px-3 py-2 text-right">Số tiền</th>
                                    <th class="px-3 py-2 text-left">Nguồn</th>
                                    <th class="px-3 py-2 text-right">Số dư sau</th>
                                    <th class="px-3 py-2 text-left">Thời gian</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-stone-100">
                                <tr v-for="t in transactions" :key="t.id" class="align-top">
                                    <td class="px-3 py-2">
                                        <span
                                            class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[11px] font-semibold"
                                            :class="t.direction === 'credit'
                                                ? 'bg-emerald-50 text-emerald-800 border-emerald-200'
                                                : 'bg-rose-50 text-rose-700 border-rose-200'"
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
                                        :class="t.direction === 'credit' ? 'text-emerald-700' : 'text-rose-700'"
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
                                        <p v-if="t.description" class="mt-1 text-[11px] text-stone-500">
                                            {{ t.description }}
                                        </p>
                                    </td>
                                    <td class="px-3 py-2 text-right font-mono text-xs text-stone-700">
                                        {{ formatVnd(t.balance_after_vnd) }}
                                    </td>
                                    <td class="px-3 py-2 text-xs text-stone-500">{{ formatDate(t.created_at) }}</td>
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
    border: 1px solid var(--border, #dbe4ed);
    background: #ffffff;
    padding: 1rem 1.125rem;
    box-shadow: 0 6px 18px -12px rgba(13, 79, 158, 0.2);
}

.user-eyebrow {
    font-size: 0.625rem;
    font-weight: 700;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: var(--primary-1, #0d4f9e);
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

.stat-net {
    background: #f3f7fc;
    border-color: #dbe4ed;
    color: var(--primary-1, #0d4f9e);
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
    border: 1.5px solid var(--border, #dbe4ed);
    background: #ffffff;
    font-size: 0.8125rem;
    font-weight: 600;
    color: var(--text-body, #102a43);
    cursor: pointer;
    transition: background-color 120ms ease, border-color 120ms ease, color 120ms ease;
}

.op-chip:hover {
    border-color: var(--primary-1, #0d4f9e);
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

.quick-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.3125rem 0.625rem;
    border-radius: 0.5rem;
    font-size: 0.75rem;
    font-weight: 700;
    background: #f3f7fc;
    color: var(--primary-1, #0d4f9e);
    border: 1px solid rgba(13, 79, 158, 0.15);
    transition: background-color 120ms ease, color 120ms ease, border-color 120ms ease;
}

.quick-btn:hover:not(:disabled) {
    background: var(--primary-1, #0d4f9e);
    color: #ffffff;
    border-color: var(--primary-1, #0d4f9e);
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
</style>
