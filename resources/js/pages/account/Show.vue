<script setup lang="ts">
import AccountController from '@/actions/App/Http/Controllers/Client/AccountController';
import { formatVnd } from '@/lib/vnd';
import { logout } from '@/routes';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowDownCircle,
    ArrowUpCircle,
    BarChart3,
    ChevronRight,
    History,
    Landmark,
    LogOut,
    ShieldCheck,
    User2,
    Wallet,
    type LucideIcon,
    CalendarHeart,
} from 'lucide-vue-next';
import { computed } from 'vue';

type MenuItem = {
    href: string;
    icon: LucideIcon;
    iconBg: string;
    iconColor: string;
    title: string;
    description: string;
    badge?: string;
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
    bank: {
        bank_name: string | null;
        bank_account_number: string | null;
        bank_account_name: string | null;
    };
    totals: { totalCreditVnd: number; totalDebitVnd: number; totalCount: number };
    eventCount?: number;
}>();

const bankConnected = computed(
    () => !!(props.bank.bank_name && props.bank.bank_account_number && props.bank.bank_account_name),
);

const maskedAccount = computed(() => {
    const num = props.bank.bank_account_number;
    if (!num) return null;
    if (num.length <= 4) return num;
    return '•••• ' + num.slice(-4);
});

const menuItems = computed<MenuItem[]>(() => [
    {
        href: AccountController.events.url(),
        icon: CalendarHeart,
        iconBg: 'bg-blue-100',
        iconColor: 'text-blue-700',
        title: 'Sự kiện đã tham gia',
        description: `${props.eventCount ?? 0} phiên đã ghi nhận`,
    },
    {
        href: AccountController.wallet.url(),
        icon: History,
        iconBg: 'bg-amber-100',
        iconColor: 'text-amber-700',
        title: 'Lịch sử giao dịch',
        description: `${props.totals.totalCount} giao dịch đã ghi nhận`,
    },
    {
        href: AccountController.editProfile.url(),
        icon: User2,
        iconBg: 'bg-violet-100',
        iconColor: 'text-violet-700',
        title: 'Thông tin tài khoản',
        description: 'Cập nhật tên & tên đăng nhập',
    },
    {
        href: AccountController.editPassword.url(),
        icon: ShieldCheck,
        iconBg: 'bg-emerald-100',
        iconColor: 'text-emerald-700',
        title: 'Mật khẩu đăng nhập',
        description: 'Đổi mật khẩu đăng nhập',
    },
    {
        href: AccountController.editBank.url(),
        icon: Landmark,
        iconBg: 'bg-rose-100',
        iconColor: 'text-rose-700',
        title: 'Liên kết ngân hàng',
        description: bankConnected.value
            ? `${props.bank.bank_name} · ${maskedAccount.value}`
            : 'Thêm thông tin nạp/rút',
        badge: bankConnected.value ? undefined : 'Chưa liên kết',
    },
]);

function doLogout() {
    if (!confirm('Đăng xuất khỏi tài khoản?')) return;
    router.post(logout().url, {}, { preserveScroll: false });
}
</script>

<template>

    <Head title="Tài khoản" />

    <div class="space-y-4 px-3 pb-24 pt-3">
        <section class="balance-card">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="balance-label">Số dư hiện tại</p>
                    <p class="mt-1 truncate  text-2xl font-bold leading-tight">{{ formatVnd(balanceVnd) }}
                    </p>
                    <p class="balance-sub mt-1">{{ profile.name }} · @{{ profile.username }}</p>
                </div>
                <div class="balance-icon">
                    <Wallet class="size-6" />
                </div>
            </div>
            <div class="mt-3 grid grid-cols-2 gap-2 text-xs">
                <div class="balance-chip">
                    <p class="balance-label flex items-center gap-1">
                        <ArrowUpCircle class="size-3.5" /> Tổng nạp
                    </p>
                    <p class="mt-0.5 font-mono font-semibold">{{ formatVnd(totals.totalCreditVnd) }}</p>
                </div>
                <div class="balance-chip">
                    <p class="balance-label flex items-center gap-1">
                        <ArrowDownCircle class="size-3.5" /> Tổng rút
                    </p>
                    <p class="mt-0.5 font-mono font-semibold">{{ formatVnd(totals.totalDebitVnd) }}</p>
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-stone-200 bg-white shadow-sm">
            <ul class="divide-y divide-stone-100">
                <li v-for="item in menuItems" :key="item.href">
                    <Link :href="item.href" class="flex items-center gap-3 px-3 py-3 transition active:bg-amber-50/70">
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-xl"
                            :class="[item.iconBg, item.iconColor]">
                            <component :is="item.icon" class="size-5" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-sm font-semibold text-stone-800">
                                {{ item.title }}
                                <span v-if="item.badge"
                                    class="rounded-full bg-rose-100 px-1.5 py-0.5 text-[10px] font-semibold text-rose-700">
                                    {{ item.badge }}
                                </span>
                            </p>
                            <p class="mt-0.5 truncate text-xs text-stone-500">{{ item.description }}</p>
                        </div>
                        <ChevronRight class="size-4 shrink-0 text-stone-400" />
                    </Link>
                </li>
            </ul>
        </section>

        <button type="button" class="logout-btn" @click="doLogout">
            <LogOut class="size-4" />
            Đăng xuất
        </button>

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
    text-transform: uppercase;
    letter-spacing: 0.04em;
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
    backdrop-filter: blur(6px);
    border: 1px solid rgba(232, 165, 0, 0.35);
}

.balance-chip {
    border-radius: 0.5rem;
    padding: 0.375rem 0.5rem;
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(232, 165, 0, 0.2);
}

.logout-btn {
    display: inline-flex;
    width: 100%;
    height: 2.75rem;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    border: 1.5px solid rgb(254 202 202);
    border-radius: 0.75rem;
    background: white;
    color: rgb(190 18 60);
    font-size: 0.875rem;
    font-weight: 600;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
    transition: background-color 150ms ease, transform 100ms ease;
    cursor: pointer;
}

.logout-btn:hover {
    background: rgb(255 241 242);
}

.logout-btn:active {
    transform: scale(0.99);
}
</style>
