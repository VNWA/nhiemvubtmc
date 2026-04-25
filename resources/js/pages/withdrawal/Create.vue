<script setup lang="ts">
import AccountController from '@/actions/App/Http/Controllers/Client/AccountController';
import WithdrawalController from '@/actions/App/Http/Controllers/Client/WithdrawalController';
import CurrencyInput from '@/components/CurrencyInput.vue';
import InputError from '@/components/InputError.vue';
import CButton from '@/components/client/CButton.vue';
import CLabel from '@/components/client/CLabel.vue';
import { formatVnd } from '@/lib/vnd';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Ban, Banknote, CheckCircle2, ChevronsRight, Hourglass, Landmark, Send, XCircle } from 'lucide-vue-next';
import { computed } from 'vue';

type HistoryItem = {
    id: number;
    amount_vnd: number;
    status: string;
    status_label: string;
    note: string | null;
    admin_note: string | null;
    bank_name: string;
    bank_account_number: string;
    bank_account_name: string;
    created_at: string | null;
    processed_at: string | null;
    can_cancel: boolean;
};

const props = defineProps<{
    balanceVnd: number;
    pendingTotalVnd: number;
    availableVnd: number;
    bank: {
        bank_name: string | null;
        bank_account_number: string | null;
        bank_account_name: string | null;
    };
    history: HistoryItem[];
}>();

const quickAmounts = [100_000, 300_000, 500_000, 1_000_000];

const form = useForm({
    amount_vnd: 0,
    note: '',
});

const bankLinked = computed(
    () => !!props.bank.bank_name && !!props.bank.bank_account_number && !!props.bank.bank_account_name,
);

function pickQuick(v: number) {
    form.amount_vnd = Math.min(v, props.availableVnd);
}

function reset() {
    form.reset();
}

function submit() {
    form.post(WithdrawalController.store.url(), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}

function cancel(id: number) {
    if (!confirm('Huỷ yêu cầu rút tiền này?')) return;
    router.delete(WithdrawalController.cancel.url({ withdrawal: id }), {
        preserveScroll: true,
    });
}

function formatDate(iso: string | null): string {
    if (!iso) return '';
    try {
        return new Date(iso).toLocaleString('vi-VN', { hour12: false });
    } catch {
        return iso;
    }
}

function statusIcon(s: string) {
    if (s === 'pending') return Hourglass;
    if (s === 'approved') return CheckCircle2;
    if (s === 'rejected') return XCircle;
    return Ban;
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
</script>

<template>

    <Head title="Rút tiền" />

    <div class="space-y-4 px-3 pb-24 pt-3">
        <header class="flex items-center gap-2">
            <Link :href="'/'"
                class="inline-flex size-8 shrink-0 items-center justify-center rounded-full bg-stone-100 text-stone-600 transition active:scale-95 hover:bg-stone-200"
                aria-label="Quay lại">
                <ArrowLeft class="size-4" />
            </Link>
            <div class="min-w-0 flex-1">
                <h1 class="text-base font-bold leading-tight text-stone-800">Rút tiền</h1>
                <p class="mt-0.5 text-xs text-stone-500">
                    Yêu cầu được gửi đến quản trị viên và sẽ chuyển khoản sau khi duyệt.
                </p>
            </div>
        </header>

        <section class="balance-card">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="balance-label">Số dư khả dụng</p>
                    <p class="mt-1 truncate font-mono text-2xl font-bold leading-tight">
                        {{ formatVnd(availableVnd) }}
                    </p>
                    <p class="balance-sub mt-1">
                        Tổng số dư {{ formatVnd(balanceVnd) }} · Đang chờ duyệt
                        {{ formatVnd(pendingTotalVnd) }}
                    </p>
                </div>
                <div class="balance-icon">
                    <Banknote class="size-6" />
                </div>
            </div>
        </section>

        <section v-if="!bankLinked" class="rounded-xl border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900">
            <p class="font-semibold">Bạn chưa liên kết tài khoản ngân hàng.</p>
            <p class="mt-1 text-xs">
                Vui lòng cập nhật thông tin ngân hàng để gửi yêu cầu rút tiền.
            </p>
            <Link :href="AccountController.editBank.url()"
                class="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-amber-900 underline">
                Liên kết ngân hàng
                <ChevronsRight class="size-3.5" />
            </Link>
        </section>

        <section v-else class="rounded-xl border border-stone-200 bg-white p-3 shadow-sm">
            <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-stone-500">
                <Landmark class="size-3.5" /> Tài khoản nhận
            </div>
            <div class="mt-1.5 space-y-0.5 text-sm text-stone-800">
                <p class="font-semibold">{{ bank.bank_name }}</p>
                <p class="font-mono">{{ bank.bank_account_number }}</p>
                <p class="text-xs text-stone-500">Chủ TK: <span class="font-semibold text-stone-700">{{
                    bank.bank_account_name }}</span></p>
            </div>

        </section>

        <form v-if="bankLinked" class="space-y-3 rounded-xl border border-stone-200 bg-white p-3 shadow-sm"
            @submit.prevent="submit">
            <div class="space-y-1.5">
                <CLabel for="amount_vnd" required>
                    Số tiền muốn rút
                </CLabel>
                <CurrencyInput id="amount_vnd" v-model="form.amount_vnd" :max="availableVnd"
                    :aria-invalid="!!form.errors.amount_vnd" placeholder="0" />
                <InputError :message="form.errors.amount_vnd" />
                <div class="flex flex-wrap gap-1.5 pt-1">
                    <button v-for="v in quickAmounts" :key="v" type="button" class="quick-btn"
                        :disabled="v > availableVnd" @click="pickQuick(v)">
                        {{ formatVnd(v) }}
                    </button>
                    <button type="button" class="quick-btn quick-btn--max" :disabled="availableVnd <= 0"
                        @click="pickQuick(availableVnd)">
                        Tối đa
                    </button>
                </div>
            </div>

            <div class="space-y-1.5">
                <CLabel for="note">
                    Ghi chú (không bắt buộc)
                </CLabel>
                <textarea id="note" v-model="form.note" rows="2" maxlength="500" placeholder="Ví dụ: Rút tiền thưởng…"
                    class="withdraw-textarea" :aria-invalid="!!form.errors.note || undefined"></textarea>
                <InputError :message="form.errors.note" />
            </div>

            <div class="flex items-center gap-2 pt-1">
                <CButton type="button" variant="outline" block :disabled="form.processing || form.amount_vnd === 0"
                    @click="reset">
                    Đặt lại
                </CButton>
                <CButton type="submit" variant="gold" block
                    :disabled="form.processing || form.amount_vnd === 0 || availableVnd <= 0">
                    <Send class="size-4" />
                    Gửi yêu cầu
                </CButton>
            </div>
        </form>

        <section class="rounded-xl border border-stone-200 bg-white shadow-sm">
            <header class="flex items-center justify-between border-b border-stone-100 px-3 py-2">
                <h2 class="text-sm font-bold text-stone-800">Lịch sử rút tiền</h2>
                <span class="text-xs text-stone-500">{{ history.length }} mục</span>
            </header>

            <div v-if="history.length === 0" class="px-3 py-6 text-center text-xs text-stone-500">
                Chưa có yêu cầu rút tiền nào.
            </div>

            <ul v-else class="divide-y divide-stone-100">
                <li v-for="row in history" :key="row.id" class="px-3 py-2.5">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-1.5">
                                <span class="font-mono text-base font-bold text-stone-900">
                                    {{ formatVnd(row.amount_vnd) }}
                                </span>
                                <span
                                    class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[11px] font-semibold"
                                    :class="statusChipClass(row.status)">
                                    <component :is="statusIcon(row.status)" class="size-3" />
                                    {{ row.status_label }}
                                </span>
                            </div>
                            <p class="mt-0.5 text-xs text-stone-500">
                                {{ formatDate(row.created_at) }}
                            </p>
                            <p v-if="row.note" class="mt-1 text-xs text-stone-600">
                                Ghi chú: {{ row.note }}
                            </p>
                            <p v-if="row.admin_note" class="mt-1 rounded bg-stone-50 px-2 py-1 text-xs text-stone-700">
                                <span class="font-semibold">Lý do:</span> {{ row.admin_note }}
                            </p>
                        </div>
                        <button v-if="row.can_cancel" type="button" class="cancel-btn" @click="cancel(row.id)">
                            Huỷ
                        </button>
                    </div>
                </li>
            </ul>
        </section>
    </div>
</template>

<style scoped>
.balance-card {
    background: linear-gradient(135deg, #0a3d7b 0%, #0d4f9e 55%, #1565c0 100%);
    color: #fdf8e8;
    border-radius: 1rem;
    padding: 1rem;
    border: 1.5px solid rgba(232, 165, 0, 0.55);
    box-shadow:
        inset 0 -2px 0 rgba(232, 165, 0, 0.55),
        0 10px 26px -10px rgba(13, 79, 158, 0.55);
}

.balance-label {
    font-size: 0.6875rem;
    font-weight: 700;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    color: #ffd766;
}

.balance-sub {
    font-size: 0.6875rem;
    color: rgba(253, 248, 232, 0.8);
}

.balance-icon {
    display: flex;
    flex-shrink: 0;
    height: 3rem;
    width: 3rem;
    align-items: center;
    justify-content: center;
    border-radius: 9999px;
    background: rgba(232, 165, 0, 0.22);
    color: #ffd766;
    border: 1px solid rgba(232, 165, 0, 0.35);
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

.quick-btn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

.quick-btn--max {
    background: #fff3cf;
    color: #7a5800;
    border-color: rgba(232, 165, 0, 0.45);
}

.quick-btn--max:hover:not(:disabled) {
    background: var(--primary-2, #e8a500);
    color: var(--primary-1, #0d4f9e);
    border-color: var(--primary-2, #e8a500);
}

.withdraw-textarea {
    width: 100%;
    border-radius: 0.5rem;
    border: 1.5px solid var(--border, #dbe4ed);
    background: #ffffff;
    padding: 0.5rem 0.75rem;
    font-size: 0.875rem;
    color: var(--text-body, #102a43);
    transition: border-color 120ms ease, box-shadow 120ms ease;
    resize: vertical;
}

.withdraw-textarea:focus {
    outline: none;
    border-color: var(--primary-1, #0d4f9e);
    box-shadow: 0 0 0 3px rgba(13, 79, 158, 0.14);
}

.cancel-btn {
    padding: 0.25rem 0.625rem;
    border-radius: 9999px;
    font-size: 0.6875rem;
    font-weight: 700;
    background: #fef2f2;
    color: #b91c1c;
    border: 1px solid rgba(185, 28, 28, 0.25);
    transition: background-color 120ms ease;
}

.cancel-btn:hover {
    background: #fee2e2;
}
</style>
