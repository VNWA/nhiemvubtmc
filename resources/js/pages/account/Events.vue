<script setup lang="ts">
import AccountPageHeader from '@/components/AccountPageHeader.vue';
import { formatVnd } from '@/lib/vnd';
import { Head, Link, router } from '@inertiajs/vue3';
import { CalendarHeart } from 'lucide-vue-next';
import AccountController from '@/actions/App/Http/Controllers/Client/AccountController';

type Bet = {
    id: number;
    amount_vnd: number;
    refund_vnd: number;
    commission_vnd: number;
    net_vnd: number;
    status: 'pending' | 'completed';
    status_label: string;
    created_at: string | null;
    option_label: string | null;
    round_name: string | null;
    round_number: number;
    room_name: string | null;
};

type Paginator = {
    data: Bet[];
    current_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
    next_page_url: string | null;
    prev_page_url: string | null;
};

const props = defineProps<{
    bets: Paginator;
    balanceVnd: number;
}>();

function goPage(url: string | null) {
    if (!url) return;
    router.visit(url, { preserveScroll: true });
}

function formatSigned(amount: number): string {
    if (amount > 0) return `+${formatVnd(amount)}`;
    if (amount < 0) return `-${formatVnd(Math.abs(amount))}`;
    return formatVnd(0);
}
</script>

<template>

    <Head title="Sự kiện đã tham gia" />

    <div class="space-y-4 px-3 pb-24 pt-3">
        <AccountPageHeader title="Sự kiện đã tham gia"
            description="Lịch sử các phiên đã đặt cược, hoàn trả và hoa hồng được ghi nhận." />

        <section class="rounded-2xl border border-stone-200 bg-white p-3 shadow-sm">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-stone-500">Tổng phiên tham gia</p>
            <p class="mt-1 text-2xl font-bold text-stone-800">{{ props.bets.total }}</p>
        </section>

        <section v-if="props.bets.data.length === 0"
            class="rounded-2xl border border-dashed border-stone-300 bg-white p-6 text-center text-sm text-stone-500">
            <CalendarHeart class="mx-auto mb-2 size-7 text-stone-400" />
            Bạn chưa tham gia phiên sự kiện nào.
            <div class="mt-3">
                <Link href="/sukien"
                    class="inline-flex items-center justify-center rounded-lg border border-amber-300 bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-700 transition active:bg-amber-100">
                Khám phá sự kiện
                </Link>
            </div>
        </section>

        <section v-else class="space-y-3">
            <article v-for="bet in props.bets.data" :key="bet.id" class="event-card">
                <header class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="flex items-center gap-1 text-sm font-semibold text-stone-800">
                            <span aria-hidden="true">🎬</span>
                            <span class="truncate">Sự kiện: {{ bet.room_name ?? '—' }}</span>
                        </p>
                        <p class="mt-0.5 flex items-center gap-1 text-xs text-stone-600">
                            <span aria-hidden="true">📌</span>
                            <span class="truncate">Nhiệm vụ: {{ bet.round_name ?? bet.option_label ?? '—' }}</span>
                        </p>
                    </div>
                    <span class="status-pill" :class="bet.status === 'completed' ? 'status-done' : 'status-pending'">
                        {{ bet.status_label }}
                    </span>
                </header>

                <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-stone-500">
                    <span class="inline-flex items-center gap-1">
                        <span aria-hidden="true">🕒</span> Phiên #{{ bet.id }}
                    </span>
                    <span v-if="bet.option_label">· Đáp án: <span class="font-medium text-stone-700">{{
                        bet.option_label }}</span></span>
                </div>

                <ul class="mt-3 grid grid-cols-1 gap-1.5 text-sm">
                    <li class="bet-row">
                        <span class="flex items-center gap-1.5 text-stone-600">
                            <span aria-hidden="true">💳</span> Phí tham gia
                        </span>
                        <span class="font-mono font-semibold text-rose-600">-{{ formatVnd(bet.amount_vnd) }}</span>
                    </li>
                    <li class="bet-row">
                        <span class="flex items-center gap-1.5 text-stone-600">
                            <span aria-hidden="true">🔁</span> Hoàn trả
                        </span>
                        <span class="font-mono font-semibold text-emerald-600">+{{ formatVnd(bet.refund_vnd) }}</span>
                    </li>
                    <li class="bet-row">
                        <span class="flex items-center gap-1.5 text-stone-600">
                            <span aria-hidden="true">🎁</span> Hoa hồng
                        </span>
                        <span class="font-mono font-semibold text-fuchsia-600">+{{ formatVnd(bet.commission_vnd)
                        }}</span>
                    </li>
                </ul>

                <footer class="mt-2.5 flex items-center justify-between border-t border-stone-100 pt-2">
                    <span class="flex items-center gap-1 text-[11px] text-stone-500">
                        <span aria-hidden="true">📈</span> Kết quả
                    </span>
                    <span class="font-mono text-sm font-bold"
                        :class="bet.net_vnd >= 0 ? 'text-emerald-600' : 'text-rose-600'">
                        {{ formatSigned(bet.net_vnd) }}
                    </span>
                </footer>
            </article>
        </section>

        <div v-if="props.bets.last_page > 1"
            class="flex items-center justify-between rounded-xl border border-stone-200 bg-white px-3 py-2 text-xs">
            <button type="button" :disabled="!props.bets.prev_page_url" class="page-btn"
                @click="goPage(props.bets.prev_page_url)">
                ‹ Trước
            </button>
            <span class="text-stone-500">
                Trang {{ props.bets.current_page }} / {{ props.bets.last_page }}
            </span>
            <button type="button" :disabled="!props.bets.next_page_url" class="page-btn"
                @click="goPage(props.bets.next_page_url)">
                Sau ›
            </button>
        </div>

        <p class="text-center text-[11px] text-stone-400">
            Số dư hiện tại: <span class="font-mono font-semibold text-stone-600">{{ formatVnd(props.balanceVnd) }}</span>
        </p>
    </div>
</template>

<style scoped>
.event-card {
    border-radius: 1rem;
    border: 1px solid rgb(231 229 228);
    background: white;
    padding: 0.75rem;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
}

.bet-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-radius: 0.5rem;
    background: rgb(250 250 249);
    padding: 0.375rem 0.625rem;
}

.status-pill {
    flex-shrink: 0;
    border-radius: 9999px;
    padding: 0.125rem 0.5rem;
    font-size: 0.6875rem;
    font-weight: 600;
}

.status-done {
    background: rgb(220 252 231);
    color: rgb(21 128 61);
}

.status-pending {
    background: rgb(254 249 195);
    color: rgb(161 98 7);
}

.page-btn {
    display: inline-flex;
    height: 2rem;
    align-items: center;
    justify-content: center;
    border-radius: 0.5rem;
    border: 1px solid rgb(231 229 228);
    padding: 0 0.75rem;
    font-weight: 600;
    color: rgb(68 64 60);
    transition: background-color 120ms ease;
}

.page-btn:disabled {
    opacity: 0.45;
    cursor: not-allowed;
}

.page-btn:not(:disabled):active {
    background: rgb(250 250 249);
}
</style>
