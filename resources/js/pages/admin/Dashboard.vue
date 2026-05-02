<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
import WithdrawalController from '@/actions/App/Http/Controllers/Admin/WithdrawalController';
import AdminListReloadButton from '@/components/admin/AdminListReloadButton.vue';
import { formatVnd } from '@/lib/vnd';
import { dashboard } from '@/routes/admin';

type Quick = {
    total_customers: number;
    total_staff: number | null;
    open_event_rounds: number;
    pending_withdrawals: number;
    total_balance_vnd: number;
    period_deposit_vnd: number;
    period_withdrawal_vnd: number;
    period_admin_debit_vnd: number;
    period_commission_vnd: number;
};

type Overview = {
    new_customers_in_period: number;
    active_customers: number;
    locked_customers: number;
};

type ChartPoint = {
    key: string;
    label: string;
    deposit_vnd: number;
    withdrawal_vnd: number;
    admin_debit_vnd: number;
    commission_vnd: number;
    new_users: number;
    event_bets: number;
};

type StaffActor = {
    id: number;
    name: string;
    username: string;
    role_label: string | null;
};

type RecentW = {
    id: number;
    user: { id: number; name: string; username: string } | null;
    amount_vnd: number;
    status: string;
    status_label: string;
    /** Thời tạo yêu cầu (VN). */
    created_at: string | null;
    /** Khi duyệt / từ chối / huỷ (VN), null nếu chờ. */
    processed_at: string | null;
    /** Giống dòng giao dịch Deposit: đã xử lý → processed_at, chờ → created_at. */
    occurred_at: string | null;
    processor: StaffActor | null;
};

type RecentU = {
    id: number;
    name: string;
    username: string;
    created_at: string | null;
    creator: StaffActor | null;
};

type RecentA = {
    id: number;
    action: string;
    action_label: string;
    description: string | null;
    created_at: string | null;
    actor: { id: number; name: string; username: string } | null;
    target: { id: number; name: string; username: string } | null;
};

const props = defineProps<{
    scope: { is_admin: boolean; is_staff_only: boolean };
    period: {
        key: string;
        date_from: string;
        date_to: string;
        label: string;
        /** IANA — khoảng ngày tùy chọn & trục biểu đồ theo múi này (không theo múi trình duyệt). */
        display_timezone: string;
    };
    quick: Quick;
    overview: Overview;
    chart_series: ChartPoint[];
    operations: { pending_withdrawals: number; open_event_rounds: number };
    recent: { withdrawals: RecentW[]; users: RecentU[]; activities: RecentA[] };
}>();

const reloadOnly = [
    'scope',
    'period',
    'quick',
    'overview',
    'chart_series',
    'operations',
    'recent',
] as const;

const currentPeriod = ref(props.period.key);
const customFrom = ref(props.period.date_from);
const customTo = ref(props.period.date_to);

function applyPeriod(key: string) {
    if (key === 'custom') {
        router.get(
            dashboard.url({
                query: {
                    period: 'custom',
                    date_from: customFrom.value,
                    date_to: customTo.value,
                },
            }),
            {},
            { preserveState: true, preserveScroll: true, replace: true },
        );

        return;
    }

    currentPeriod.value = key;
    router.get(
        dashboard.url({ query: { period: key } }),
        {},
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

watch(
    () => props.period.key,
    (k) => {
        currentPeriod.value = k;
    },
);

watch(
    () => [props.period.date_from, props.period.date_to] as const,
    ([from, to]) => {
        customFrom.value = from;
        customTo.value = to;
    },
);

const maxWalletAmount = computed(() => {
    let m = 1;

    for (const p of props.chart_series) {
        m = Math.max(
            m,
            p.deposit_vnd,
            p.withdrawal_vnd,
            p.admin_debit_vnd,
            p.commission_vnd,
        );
    }

    return m;
});

const maxNewUsers = computed(() => {
    let m = 1;

    for (const p of props.chart_series) {
        m = Math.max(m, p.new_users);
    }

    return m;
});

const maxBets = computed(() => {
    let m = 1;

    for (const p of props.chart_series) {
        m = Math.max(m, p.event_bets);
    }

    return m;
});

const chartTrack = 120;

function staffActorLabel(actor: StaffActor | null): string {
    if (!actor) {
        return '—';
    }
    const role = actor.role_label ? ` (${actor.role_label})` : '';

    return `${actor.username}${role}`;
}

function barHeightPx(value: number, max: number, track: number = chartTrack): string {
    if (max <= 0 || value === 0) {
        return '0px';
    }

    return `${Math.max(1, Math.round((value / max) * track))}px`;
}

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Tổng quan',
                href: dashboard(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Tổng quan" />

    <div
        class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4"
    >
        <div
            class="flex flex-col gap-3 border-b border-sidebar-border/60 pb-4 dark:border-sidebar-border"
        >
            <div
                class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between"
            >
                <div>
                    <h1
                        class="text-lg font-semibold text-stone-900 dark:text-stone-100"
                    >
                        Bảng điều khiển
                    </h1>
                    <p class="text-sm text-stone-500">
                        Dữ liệu theo kỳ: {{ period.label }}
                    </p>
                </div>
                <div
                    class="flex flex-wrap items-center justify-end gap-2"
                >
                    <p
                        v-if="scope.is_staff_only"
                        class="text-xs text-amber-700 dark:text-amber-400/90"
                    >
                        Bạn đang xem số liệu của khách hàng do bạn tạo (nhân
                        viên).
                    </p>
                    <AdminListReloadButton
                        :only="[...reloadOnly]"
                    />
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <span class="text-xs font-medium text-stone-500">Kỳ:</span>
                <button
                    type="button"
                    :class="[
                        'rounded-md border px-2.5 py-1 text-xs font-medium transition',
                        currentPeriod === 'today'
                            ? 'border-primary bg-primary/10 text-primary'
                            : 'border-sidebar-border/70 text-stone-600 hover:bg-stone-100 dark:border-sidebar-border dark:text-stone-300 dark:hover:bg-stone-800/80',
                    ]"
                    @click="applyPeriod('today')"
                >
                    Hôm nay
                </button>
                <button
                    type="button"
                    :class="[
                        'rounded-md border px-2.5 py-1 text-xs font-medium transition',
                        currentPeriod === '7d'
                            ? 'border-primary bg-primary/10 text-primary'
                            : 'border-sidebar-border/70 text-stone-600 hover:bg-stone-100 dark:border-sidebar-border dark:text-stone-300 dark:hover:bg-stone-800/80',
                    ]"
                    @click="applyPeriod('7d')"
                >
                    7 ngày
                </button>
                <button
                    type="button"
                    :class="[
                        'rounded-md border px-2.5 py-1 text-xs font-medium transition',
                        currentPeriod === '30d'
                            ? 'border-primary bg-primary/10 text-primary'
                            : 'border-sidebar-border/70 text-stone-600 hover:bg-stone-100 dark:border-sidebar-border dark:text-stone-300 dark:hover:bg-stone-800/80',
                    ]"
                    @click="applyPeriod('30d')"
                >
                    30 ngày
                </button>
                <button
                    type="button"
                    :class="[
                        'rounded-md border px-2.5 py-1 text-xs font-medium transition',
                        currentPeriod === 'month'
                            ? 'border-primary bg-primary/10 text-primary'
                            : 'border-sidebar-border/70 text-stone-600 hover:bg-stone-100 dark:border-sidebar-border dark:text-stone-300 dark:hover:bg-stone-800/80',
                    ]"
                    @click="applyPeriod('month')"
                >
                    Tháng này
                </button>
            </div>

            <div
                class="flex flex-wrap items-end gap-2 rounded-md border border-dashed border-sidebar-border/60 p-2 dark:border-sidebar-border"
            >
                <div class="flex flex-col gap-0.5">
                    <label class="text-[10px] font-medium text-stone-500"
                        >Từ ngày</label
                    >
                    <input
                        v-model="customFrom"
                        type="date"
                        class="rounded border border-sidebar-border/60 bg-white px-2 py-1 text-xs dark:border-sidebar-border dark:bg-stone-900"
                    />
                </div>
                <div class="flex flex-col gap-0.5">
                    <label class="text-[10px] font-medium text-stone-500"
                        >Đến ngày</label
                    >
                    <input
                        v-model="customTo"
                        type="date"
                        class="rounded border border-sidebar-border/60 bg-white px-2 py-1 text-xs dark:border-sidebar-border dark:bg-stone-900"
                    />
                </div>
                <p
                    class="w-full text-[10px] leading-snug text-stone-500 dark:text-stone-400"
                >
                    Ngày chọn & cột biểu đồ theo lịch múi
                    <span class="font-mono">{{ period.display_timezone }}</span>
                    (cấu hình server). Giờ nửa đêm theo múi này luôn thuộc đúng “ngày” đó; nếu lệch,
                    kiểm tra <span class="font-mono">APP_DISPLAY_TIMEZONE</span> và deploy code mới nhất.
                </p>
                <button
                    type="button"
                    class="rounded-md bg-stone-800 px-3 py-1.5 text-xs font-medium text-white hover:bg-stone-700 dark:bg-stone-200 dark:text-stone-900 dark:hover:bg-white"
                    @click="applyPeriod('custom')"
                >
                    Áp dụng
                </button>
            </div>
        </div>

        <!-- 1. Thẻ nhanh -->
        <div>
            <h2
                class="mb-2 text-sm font-semibold text-stone-800 dark:text-stone-100"
            >
                Số nhanh
            </h2>
            <div
                class="grid gap-2 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-4"
            >
                <div
                    class="rounded-lg border border-sidebar-border/70 bg-stone-50/80 p-3 dark:border-sidebar-border dark:bg-stone-900/50"
                >
                    <p class="text-xs text-stone-500">Tổng khách hàng</p>
                    <p class="mt-0.5 font-mono text-lg font-semibold">
                        {{ quick.total_customers.toLocaleString('vi-VN') }}
                    </p>
                </div>
                <div
                    v-if="scope.is_admin"
                    class="rounded-lg border border-sidebar-border/70 bg-stone-50/80 p-3 dark:border-sidebar-border dark:bg-stone-900/50"
                >
                    <p class="text-xs text-stone-500">Tổng nhân viên</p>
                    <p class="mt-0.5 font-mono text-lg font-semibold">
                        {{ (quick.total_staff ?? 0).toLocaleString('vi-VN') }}
                    </p>
                </div>
                <div
                    class="rounded-lg border border-sidebar-border/70 bg-stone-50/80 p-3 dark:border-sidebar-border dark:bg-stone-900/50"
                >
                    <p class="text-xs text-stone-500">Sự kiện (vòng) đang mở</p>
                    <p class="mt-0.5 font-mono text-lg font-semibold">
                        {{ quick.open_event_rounds.toLocaleString('vi-VN') }}
                    </p>
                </div>
                <div
                    class="rounded-lg border border-sidebar-border/70 bg-stone-50/80 p-3 dark:border-sidebar-border dark:bg-stone-900/50"
                >
                    <p class="text-xs text-stone-500">Rút tiền chờ duyệt</p>
                    <p class="mt-0.5 font-mono text-lg font-semibold">
                        {{ quick.pending_withdrawals.toLocaleString('vi-VN') }}
                    </p>
                </div>
                <div
                    class="rounded-lg border border-sidebar-border/70 bg-stone-50/80 p-3 dark:border-sidebar-border dark:bg-stone-900/50"
                >
                    <p class="text-xs text-stone-500">Tổng số dư (khách)</p>
                    <p class="mt-0.5 font-mono text-sm font-semibold">
                        {{ formatVnd(quick.total_balance_vnd) }}
                    </p>
                </div>
                <div
                    class="rounded-lg border border-sidebar-border/70 bg-stone-50/80 p-3 dark:border-sidebar-border dark:bg-stone-900/50"
                >
                    <p class="text-xs text-stone-500">Nạp (trong kỳ)</p>
                    <p class="mt-0.5 font-mono text-sm font-semibold text-emerald-700 dark:text-emerald-400/90">
                        {{ formatVnd(quick.period_deposit_vnd) }}
                    </p>
                </div>
                <div
                    class="rounded-lg border border-sidebar-border/70 bg-stone-50/80 p-3 dark:border-sidebar-border dark:bg-stone-900/50"
                >
                    <p class="text-xs text-stone-500">Rút thành công (kỳ)</p>
                    <p class="mt-0.5 font-mono text-sm font-semibold text-rose-700 dark:text-rose-400/80">
                        {{ formatVnd(quick.period_withdrawal_vnd) }}
                    </p>
                </div>
                <div
                    class="rounded-lg border border-sidebar-border/70 bg-stone-50/80 p-3 dark:border-sidebar-border dark:bg-stone-900/50"
                >
                    <p class="text-xs text-stone-500" title="Trừ tiền bởi quản trị / nhân viên (không gồm lệnh rút của khách)">
                        Hệ thống trừ (kỳ)
                    </p>
                    <p class="mt-0.5 font-mono text-sm font-semibold text-orange-800 dark:text-orange-400/90">
                        {{ formatVnd(quick.period_admin_debit_vnd) }}
                    </p>
                </div>
                <div
                    class="rounded-lg border border-sidebar-border/70 bg-stone-50/80 p-3 dark:border-sidebar-border dark:bg-stone-900/50"
                >
                    <p class="text-xs text-stone-500">Hoa hồng (trong kỳ)</p>
                    <p class="mt-0.5 font-mono text-sm font-semibold text-amber-800 dark:text-amber-300/90">
                        {{ formatVnd(quick.period_commission_vnd) }}
                    </p>
                </div>
            </div>
        </div>

        <!-- 2. Tổng quan -->
        <div>
            <h2
                class="mb-2 text-sm font-semibold text-stone-800 dark:text-stone-100"
            >
                Tổng quan khách
            </h2>
            <div
                class="grid gap-2 sm:grid-cols-3"
            >
                <div
                    class="rounded-lg border border-sidebar-border/70 p-3 dark:border-sidebar-border"
                >
                    <p class="text-xs text-stone-500">Khách mới (trong kỳ)</p>
                    <p class="text-[10px] text-stone-400 dark:text-stone-500">
                        Từ 0h đến hết ngày theo
                        <span class="font-mono">{{ period.display_timezone }}</span>
                    </p>
                    <p class="mt-0.5 font-mono text-xl font-bold">
                        {{ overview.new_customers_in_period.toLocaleString('vi-VN') }}
                    </p>
                </div>
                <div
                    class="rounded-lg border border-sidebar-border/70 p-3 dark:border-sidebar-border"
                >
                    <p class="text-xs text-stone-500">Đang hoạt động</p>
                    <p class="mt-0.5 font-mono text-xl font-bold text-emerald-700 dark:text-emerald-400/90">
                        {{ overview.active_customers.toLocaleString('vi-VN') }}
                    </p>
                </div>
                <div
                    class="rounded-lg border border-sidebar-border/70 p-3 dark:border-sidebar-border"
                >
                    <p class="text-xs text-stone-500">Bị khóa</p>
                    <p class="mt-0.5 font-mono text-xl font-bold text-rose-700 dark:text-rose-300/80">
                        {{ overview.locked_customers.toLocaleString('vi-VN') }}
                    </p>
                </div>
            </div>
        </div>

        <!-- 3. Biểu đồ -->
        <div class="space-y-6">
            <div>
                <h2
                    class="mb-2 text-sm font-semibold text-stone-800 dark:text-stone-100"
                >
                    Nạp / rút / hệ thống trừ / hoa hồng theo ngày
                </h2>
                <div
                    class="flex flex-wrap items-center gap-3 text-[10px] text-stone-500"
                >
                    <span class="flex items-center gap-1.5"
                        ><span
                            class="inline-block size-2 rounded-sm bg-emerald-500/90"
                        />Nạp</span
                    >
                    <span class="flex items-center gap-1.5"
                        ><span
                            class="inline-block size-2 rounded-sm bg-rose-500/90"
                        />Rút (duyệt)</span
                    >
                    <span class="flex items-center gap-1.5"
                        title="Trừ bởi quản trị / nhân viên"
                        ><span
                            class="inline-block size-2 rounded-sm bg-orange-500/90"
                        />Hệ thống trừ</span
                    >
                    <span class="flex items-center gap-1.5"
                        ><span
                            class="inline-block size-2 rounded-sm bg-amber-500/90"
                        />Hoa hồng</span
                    >
                </div>
                <div
                    v-if="chart_series.length"
                    class="mt-2 overflow-x-auto rounded-lg border border-sidebar-border/60 p-2 dark:border-sidebar-border"
                >
                    <div
                        class="flex min-w-[min(100%,720px)] items-end gap-0.5"
                        :style="{
                            minHeight: '140px',
                        }"
                    >
                        <div
                            v-for="p in chart_series"
                            :key="`w-${p.key}`"
                            class="flex h-[140px] min-w-5 flex-1 flex-col items-center justify-end gap-0.5"
                        >
                            <div
                                class="flex w-full flex-1 items-end justify-center gap-px"
                            >
                                <div
                                    class="flex-1 max-w-1.5 min-h-px rounded-t bg-emerald-500/90"
                                    :style="{
                                        height: barHeightPx(
                                            p.deposit_vnd,
                                            maxWalletAmount,
                                        ),
                                    }"
                                />
                                <div
                                    class="flex-1 max-w-1.5 min-h-px rounded-t bg-rose-500/80"
                                    :style="{
                                        height: barHeightPx(
                                            p.withdrawal_vnd,
                                            maxWalletAmount,
                                        ),
                                    }"
                                />
                                <div
                                    class="flex-1 max-w-1.5 min-h-px rounded-t bg-orange-500/90"
                                    :style="{
                                        height: barHeightPx(
                                            p.admin_debit_vnd,
                                            maxWalletAmount,
                                        ),
                                    }"
                                />
                                <div
                                    class="flex-1 max-w-1.5 min-h-px rounded-t bg-amber-500/90"
                                    :style="{
                                        height: barHeightPx(
                                            p.commission_vnd,
                                            maxWalletAmount,
                                        ),
                                    }"
                                />
                            </div>
                            <span
                                class="mt-0.5 block max-w-10 truncate text-center text-[8px] leading-tight text-stone-500"
                            >{{ p.label }}</span
                            >
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <h2
                    class="mb-2 text-sm font-semibold text-stone-800 dark:text-stone-100"
                >
                    Khách mới theo ngày
                </h2>
                <div
                    v-if="chart_series.length"
                    class="overflow-x-auto rounded-lg border border-sidebar-border/60 p-2 dark:border-sidebar-border"
                >
                    <div
                        class="flex min-w-[min(100%,720px)] items-end gap-0.5"
                    >
                        <div
                            v-for="p in chart_series"
                            :key="`n-${p.key}`"
                            class="flex h-[100px] min-w-5 flex-1 flex-col items-center justify-end"
                        >
                            <div
                                class="w-2/3 min-h-px rounded-t bg-sky-500/90"
                                :style="{
                                    height: barHeightPx(
                                        p.new_users,
                                        maxNewUsers,
                                        100,
                                    ),
                                }"
                            />
                            <span
                                class="mt-0.5 block max-w-10 truncate text-center text-[8px] text-stone-500"
                            >{{ p.label }}</span
                            >
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <h2
                    class="mb-2 text-sm font-semibold text-stone-800 dark:text-stone-100"
                >
                    Lượt tham gia sự kiện (cược) theo ngày
                </h2>
                <div
                    v-if="chart_series.length"
                    class="overflow-x-auto rounded-lg border border-sidebar-border/60 p-2 dark:border-sidebar-border"
                >
                    <div
                        class="flex min-w-[min(100%,720px)] items-end gap-0.5"
                    >
                        <div
                            v-for="p in chart_series"
                            :key="`b-${p.key}`"
                            class="flex h-[100px] min-w-5 flex-1 flex-col items-center justify-end"
                        >
                            <div
                                class="w-2/3 min-h-px rounded-t bg-violet-500/90"
                                :style="{
                                    height: barHeightPx(
                                        p.event_bets,
                                        maxBets,
                                        100,
                                    ),
                                }"
                            />
                            <span
                                class="mt-0.5 block max-w-10 truncate text-center text-[8px] text-stone-500"
                            >{{ p.label }}</span
                            >
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. Vận hành -->
        <div>
            <h2
                class="mb-2 text-sm font-semibold text-stone-800 dark:text-stone-100"
            >
                Vận hành
            </h2>
            <div
                class="grid gap-2 sm:grid-cols-2"
            >
                <Link
                    :href="WithdrawalController.index.url({ query: { status: 'pending' } })"
                    class="block rounded-lg border border-sidebar-border/70 p-3 transition hover:bg-stone-100/80 dark:border-sidebar-border dark:hover:bg-stone-800/60"
                >
                    <p class="text-xs text-stone-500">Rút tiền đang chờ</p>
                    <p class="mt-0.5 font-mono text-2xl font-bold">
                        {{ operations.pending_withdrawals.toLocaleString('vi-VN') }}
                    </p>
                </Link>
                <a
                    :href="scope.is_admin ? '/admin/sukien-rooms' : '/sukien'"
                    class="block rounded-lg border border-sidebar-border/70 p-3 transition hover:bg-stone-100/80 dark:border-sidebar-border dark:hover:bg-stone-800/60"
                >
                    <p class="text-xs text-stone-500">Vòng sự kiện đang mở</p>
                    <p class="mt-0.5 font-mono text-2xl font-bold">
                        {{ operations.open_event_rounds.toLocaleString('vi-VN') }}
                    </p>
                </a>
            </div>
        </div>

        <!-- 5. Bảng gần đây -->
        <div
            class="grid gap-4 lg:grid-cols-3"
        >
            <div
                class="rounded-lg border border-sidebar-border/60 dark:border-sidebar-border"
            >
                <div
                    class="border-b border-sidebar-border/60 px-3 py-2 text-sm font-medium dark:border-sidebar-border"
                >
                    Yêu cầu rút gần đây
                </div>
                <div
                    v-if="recent.withdrawals.length"
                    class="max-h-72 overflow-y-auto"
                >
                    <table
                        class="w-full text-left text-xs"
                    >
                        <thead
                            class="bg-stone-100/60 text-stone-500 dark:bg-stone-800/50"
                        >
                            <tr>
                                <th class="px-2 py-1 font-medium">Khách</th>
                                <th class="px-2 py-1 font-medium">Số tiền</th>
                                <th class="px-2 py-1 font-medium">Thời gian</th>
                                <th class="px-2 py-1 font-medium">Trạng thái</th>
                                <th class="px-2 py-1 font-medium">Người xử lý</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="w in recent.withdrawals"
                                :key="w.id"
                                class="border-t border-stone-200/60 dark:border-stone-700/50"
                            >
                                <td
                                    class="px-2 py-1.5"
                                >{{ w.user?.username ?? '—' }}</td>
                                <td class="px-2 py-1.5 font-mono">{{
                                    formatVnd(w.amount_vnd)
                                }}</td>
                                <td
                                    class="whitespace-nowrap px-2 py-1.5 text-stone-500"
                                    :title="w.processed_at ? `Tạo: ${w.created_at ?? '—'}` : undefined"
                                >{{ w.occurred_at ?? '—' }}</td>
                                <td
                                    class="px-2 py-1.5"
                                >{{ w.status_label }}</td>
                                <td
                                    class="max-w-40 px-2 py-1.5 text-stone-600 dark:text-stone-400"
                                    :title="w.processor ? w.processor.name : undefined"
                                >{{
                                    w.status === 'pending'
                                        ? 'Chờ duyệt'
                                        : staffActorLabel(w.processor)
                                }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p
                    v-else
                    class="p-3 text-xs text-stone-500"
                >Chưa có dữ liệu</p
                >
            </div>

            <div
                class="rounded-lg border border-sidebar-border/60 dark:border-sidebar-border"
            >
                <div
                    class="border-b border-sidebar-border/60 px-3 py-2 text-sm font-medium dark:border-sidebar-border"
                >
                    Khách đăng ký mới
                </div>
                <div
                    v-if="recent.users.length"
                    class="max-h-72 overflow-y-auto"
                >
                    <table
                        class="w-full text-left text-xs"
                    >
                        <thead
                            class="bg-stone-100/60 text-stone-500 dark:bg-stone-800/50"
                        >
                            <tr>
                                <th class="px-2 py-1 font-medium">Tên</th>
                                <th class="px-2 py-1 font-medium">Đăng ký</th>
                                <th class="px-2 py-1 font-medium">Tạo bởi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="u in recent.users"
                                :key="u.id"
                                class="border-t border-stone-200/60 dark:border-stone-700/50"
                            >
                                <td class="px-2 py-1.5">
                                    <Link
                                        :href="UserController.edit.url({ user: u.id })"
                                        class="text-primary hover:underline"
                                    >{{ u.username }}</Link
                                    >
                                </td>
                                <td
                                    class="px-2 py-1.5 text-stone-500"
                                >{{ u.created_at ?? '—' }}</td>
                                <td
                                    class="max-w-40 px-2 py-1.5 text-stone-600 dark:text-stone-400"
                                    :title="u.creator ? u.creator.name : undefined"
                                >{{
                                    u.creator
                                        ? staffActorLabel(u.creator)
                                        : 'Khách tự đăng ký'
                                }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p
                    v-else
                    class="p-3 text-xs text-stone-500"
                >Chưa có dữ liệu</p
                >
            </div>

            <div
                class="rounded-lg border border-sidebar-border/60 dark:border-sidebar-border"
            >
                <div
                    class="border-b border-sidebar-border/60 px-3 py-2 text-sm font-medium dark:border-sidebar-border"
                >
                    Thao tác gần đây
                </div>
                <div
                    v-if="recent.activities.length"
                    class="max-h-72 overflow-y-auto divide-y divide-stone-200/50 dark:divide-stone-700/50"
                >
                    <div
                        v-for="a in recent.activities"
                        :key="a.id"
                        class="px-2 py-2 text-xs"
                    >
                        <p class="font-medium text-stone-800 dark:text-stone-100">
                            {{ a.action_label }}
                        </p>
                        <p
                            v-if="a.description"
                            class="mt-0.5 text-stone-600 dark:text-stone-400"
                        >
                            {{ a.description }}
                        </p>
                        <p
                            class="mt-0.5 text-[10px] text-stone-500"
                        >
                            {{ a.created_at }} ·
                            <span
                                v-if="a.actor"
                            >{{ a.actor.username }}</span
                            >
                        </p>
                    </div>
                </div>
                <p
                    v-else
                    class="p-3 text-xs text-stone-500"
                >Chưa có dữ liệu</p
                >
            </div>
        </div>
    </div>
</template>
