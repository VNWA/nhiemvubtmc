<script setup lang="ts">
import AccountController from '@/actions/App/Http/Controllers/Client/AccountController';
import { formatVnd } from '@/lib/vnd';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ArrowDownCircle, ArrowUpCircle, History, Lock, ShieldCheck, User2, Wallet } from 'lucide-vue-next';
import { computed, ref } from 'vue';

type RecentTx = {
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

const props = defineProps<{
    profile: {
        id: number;
        name: string;
        username: string;
        email: string;
        created_at: string | null;
        role: string;
    };
    balanceVnd: number;
    totals: { totalCreditVnd: number; totalDebitVnd: number; totalCount: number };
    recentTransactions: RecentTx[];
}>();

const page = usePage();
const flash = computed(() => (page.props as { flash?: { success?: string } }).flash ?? {});

const form = useForm({
    name: props.profile.name,
    username: props.profile.username,
    password: '',
    password_confirmation: '',
});

const showPassword = ref(false);

function submit() {
    form.transform((data) => {
        if (!data.password) {
            const { password: _p, password_confirmation: _c, ...rest } = data;
            return rest;
        }
        return data;
    }).patch(AccountController.update.url(), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('password', 'password_confirmation');
            showPassword.value = false;
        },
    });
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
    <Head title="Tài khoản" />

    <div class="space-y-4 px-3 pb-24 pt-3">
        <div v-if="flash.success" class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
            {{ flash.success }}
        </div>

        <section class="balance-card">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-xs font-medium uppercase tracking-wide text-amber-100/90">Số dư hiện tại</p>
                    <p class="mt-1 truncate font-mono text-3xl font-bold leading-tight">{{ formatVnd(balanceVnd) }}</p>
                </div>
                <div class="flex size-12 shrink-0 items-center justify-center rounded-full bg-white/15 backdrop-blur">
                    <Wallet class="size-6" />
                </div>
            </div>
            <div class="mt-3 grid grid-cols-2 gap-2 text-xs">
                <div class="rounded-lg bg-white/10 px-2 py-1.5">
                    <p class="flex items-center gap-1 text-amber-100/90">
                        <ArrowUpCircle class="size-3.5" /> Tổng nạp
                    </p>
                    <p class="mt-0.5 font-mono font-semibold">{{ formatVnd(totals.totalCreditVnd) }}</p>
                </div>
                <div class="rounded-lg bg-white/10 px-2 py-1.5">
                    <p class="flex items-center gap-1 text-amber-100/90">
                        <ArrowDownCircle class="size-3.5" /> Tổng chi
                    </p>
                    <p class="mt-0.5 font-mono font-semibold">{{ formatVnd(totals.totalDebitVnd) }}</p>
                </div>
            </div>
            <Link
                :href="AccountController.wallet.url()"
                class="mt-3 inline-flex w-full items-center justify-center gap-1.5 rounded-lg bg-white text-sm font-semibold text-amber-700 shadow-sm transition active:scale-[0.99] py-2"
            >
                <History class="size-4" />
                Xem lịch sử giao dịch ({{ totals.totalCount }})
            </Link>
        </section>

        <section class="rounded-2xl border border-stone-200 bg-white p-4 shadow-sm">
            <h2 class="mb-3 flex items-center gap-2 text-sm font-semibold text-stone-800">
                <User2 class="size-4 text-amber-700" />
                Thông tin tài khoản
            </h2>
            <form class="space-y-3" @submit.prevent="submit">
                <div>
                    <label class="account-label" for="account-name">Họ tên</label>
                    <input id="account-name" v-model="form.name" type="text" class="account-input" :class="{ 'is-invalid': form.errors.name }" autocomplete="name" />
                    <p v-if="form.errors.name" class="account-error">{{ form.errors.name }}</p>
                </div>

                <div>
                    <label class="account-label" for="account-username">Tên đăng nhập</label>
                    <input id="account-username" v-model="form.username" type="text" class="account-input" :class="{ 'is-invalid': form.errors.username }" autocomplete="username" />
                    <p v-if="form.errors.username" class="account-error">{{ form.errors.username }}</p>
                </div>

                <div>
                    <label class="account-label">Email</label>
                    <input :value="profile.email" type="text" class="account-input is-readonly" readonly />
                    <p class="mt-1 text-[11px] text-stone-500">Email do hệ thống cấp, không thể thay đổi.</p>
                </div>

                <div class="rounded-xl border border-stone-200 bg-stone-50/60 p-3">
                    <button type="button" class="flex w-full items-center justify-between text-left" @click="showPassword = !showPassword">
                        <span class="flex items-center gap-2 text-sm font-semibold text-stone-700">
                            <Lock class="size-4 text-amber-700" />
                            Đổi mật khẩu
                        </span>
                        <span class="text-[11px] text-stone-500">{{ showPassword ? 'Ẩn' : 'Hiện' }}</span>
                    </button>
                    <div v-if="showPassword" class="mt-3 space-y-2">
                        <div>
                            <label class="account-label" for="account-password">Mật khẩu mới</label>
                            <input id="account-password" v-model="form.password" type="password" class="account-input" :class="{ 'is-invalid': form.errors.password }" autocomplete="new-password" />
                            <p v-if="form.errors.password" class="account-error">{{ form.errors.password }}</p>
                        </div>
                        <div>
                            <label class="account-label" for="account-password-confirm">Nhập lại mật khẩu</label>
                            <input id="account-password-confirm" v-model="form.password_confirmation" type="password" class="account-input" autocomplete="new-password" />
                        </div>
                        <p class="text-[11px] text-stone-500">Để trống nếu không đổi mật khẩu.</p>
                    </div>
                </div>

                <button
                    type="submit"
                    class="inline-flex h-11 w-full items-center justify-center rounded-xl bg-amber-600 text-sm font-semibold text-white shadow-sm transition hover:bg-amber-700 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="form.processing"
                >
                    {{ form.processing ? 'Đang lưu…' : 'Lưu thay đổi' }}
                </button>
            </form>
        </section>

        <section class="rounded-2xl border border-stone-200 bg-white p-4 shadow-sm">
            <div class="mb-2 flex items-center justify-between">
                <h2 class="flex items-center gap-2 text-sm font-semibold text-stone-800">
                    <History class="size-4 text-amber-700" />
                    Giao dịch gần đây
                </h2>
                <Link :href="AccountController.wallet.url()" class="text-xs font-semibold text-amber-700 active:opacity-70">
                    Xem tất cả →
                </Link>
            </div>
            <ul v-if="recentTransactions.length" class="space-y-1.5">
                <li v-for="tx in recentTransactions" :key="tx.id" class="flex items-start gap-2 rounded-xl border border-stone-100 bg-stone-50/60 px-3 py-2">
                    <span
                        class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full"
                        :class="tx.direction === 'credit' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'"
                    >
                        <ArrowUpCircle v-if="tx.direction === 'credit'" class="size-4" />
                        <ArrowDownCircle v-else class="size-4" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-stone-800">{{ tx.description || tx.source_label }}</p>
                        <p class="text-[11px] text-stone-500">{{ formatDateTime(tx.created_at) }} · {{ tx.source_label }}</p>
                    </div>
                    <div class="text-right">
                        <p class="font-mono text-sm font-bold" :class="tx.direction === 'credit' ? 'text-emerald-700' : 'text-rose-700'">
                            {{ tx.direction === 'credit' ? '+' : '−' }}{{ formatVnd(tx.amount_vnd) }}
                        </p>
                        <p class="font-mono text-[10px] text-stone-500">SD: {{ formatVnd(tx.balance_after_vnd) }}</p>
                    </div>
                </li>
            </ul>
            <p v-else class="rounded-xl border border-dashed border-stone-200 bg-stone-50/40 px-3 py-6 text-center text-xs text-stone-500">
                Chưa có giao dịch nào.
            </p>
        </section>

        <section class="rounded-2xl border border-stone-200 bg-white p-4 text-xs text-stone-500 shadow-sm">
            <p class="flex items-center gap-1.5"><ShieldCheck class="size-3.5 text-emerald-600" /> Tài khoản đã xác thực</p>
            <p class="mt-1">Tham gia từ {{ formatDateTime(profile.created_at) }} · vai trò <b class="text-stone-700">{{ profile.role }}</b></p>
        </section>
    </div>
</template>

<style scoped>
.balance-card {
    background: linear-gradient(135deg, #b45309 0%, #d97706 60%, #f59e0b 100%);
    color: white;
    border-radius: 1rem;
    padding: 1rem;
    box-shadow: 0 8px 24px -8px rgba(180, 83, 9, 0.45);
}

.account-label {
    display: block;
    margin-bottom: 0.25rem;
    font-size: 0.75rem;
    font-weight: 600;
    color: rgb(68 64 60);
}

.account-input {
    display: block;
    width: 100%;
    height: 2.5rem;
    padding: 0 0.875rem;
    border: 1.5px solid rgb(231 229 228);
    border-radius: 0.625rem;
    background: white;
    color: rgb(28 25 23);
    font-size: 0.9375rem;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
    transition: border-color 150ms ease, box-shadow 150ms ease;
    outline: none;
}

.account-input::placeholder {
    color: rgb(214 211 209);
}

.account-input:hover:not(:disabled):not(.is-readonly) {
    border-color: rgb(214 211 209);
}

.account-input:focus {
    border-color: rgb(217 119 6);
    box-shadow: 0 0 0 3px rgb(254 243 199);
}

.account-input.is-invalid {
    border-color: rgb(220 38 38);
    box-shadow: 0 0 0 3px rgb(254 226 226);
}

.account-input.is-readonly {
    background: rgb(250 250 249);
    color: rgb(120 113 108);
}

.account-error {
    margin-top: 0.25rem;
    font-size: 0.6875rem;
    color: rgb(220 38 38);
    font-weight: 500;
}
</style>
