<script setup lang="ts">
import AccountController from '@/actions/App/Http/Controllers/Client/AccountController';
import AccountPageHeader from '@/components/AccountPageHeader.vue';
import { formatVnd } from '@/lib/vnd';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowDownCircle, ArrowUpCircle, CalendarRange, History, PieChart, Wallet } from 'lucide-vue-next';
import { computed } from 'vue';

type Summary = { totalCreditVnd: number; totalDebitVnd: number; totalCount: number };
type SourceRow = {
    source: string;
    source_label: string;
    direction: 'credit' | 'debit';
    count: number;
    total_vnd: number;
};

const props = defineProps<{
    balanceVnd: number;
    totals: Summary;
    last30Days: Summary;
    bySource: SourceRow[];
}>();

const netLifetime = computed(() => props.totals.totalCreditVnd - props.totals.totalDebitVnd);
const net30 = computed(() => props.last30Days.totalCreditVnd - props.last30Days.totalDebitVnd);




function percent(part: number, whole: number): string {
    if (whole <= 0) return '0%';
    return `${Math.round((part / whole) * 100)}%`;
}
</script>

<template>

    <Head title="Báo cáo tài chính" />

    <div class="space-y-3 px-3 pb-24 pt-3">
        <AccountPageHeader title="Báo cáo tài chính" description="Tổng hợp dòng tiền của bạn" />

        <section class="rounded-2xl border border-stone-200 bg-white p-4 shadow-sm">
            <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-stone-500">
                <Wallet class="size-4" /> Số dư hiện tại
            </div>
            <p class="mt-1 font-mono text-2xl font-bold text-stone-800">{{ formatVnd(balanceVnd) }}</p>
        </section>

        <section class="rounded-2xl border border-stone-200 bg-white p-4 shadow-sm">
            <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-stone-500">
                <PieChart class="size-4" /> Toàn thời gian
            </div>
            <div class="mt-2 grid grid-cols-2 gap-2">
                <div class="rounded-xl bg-emerald-50 p-2.5">
                    <p class="flex items-center gap-1 text-[11px] font-semibold text-emerald-700">
                        <ArrowUpCircle class="size-3.5" /> Tổng nạp
                    </p>
                    <p class="mt-0.5 font-mono text-base font-bold text-emerald-800">+{{
                        formatVnd(totals.totalCreditVnd) }}</p>
                </div>
                <div class="rounded-xl bg-rose-50 p-2.5">
                    <p class="flex items-center gap-1 text-[11px] font-semibold text-rose-700">
                        <ArrowDownCircle class="size-3.5" /> Tổng rút
                    </p>
                    <p class="mt-0.5 font-mono text-base font-bold text-rose-800">-{{ formatVnd(totals.totalDebitVnd) }}
                    </p>
                </div>
            </div>
            <div class="mt-2 flex items-center justify-between rounded-xl bg-stone-50 px-3 py-2">
                <span class="text-xs font-semibold text-stone-600">Chênh lệch ròng</span>
                <span class="font-mono text-sm font-bold"
                    :class="netLifetime >= 0 ? 'text-emerald-700' : 'text-rose-700'">
                    {{ netLifetime >= 0 ? '+' : '' }}{{ formatVnd(netLifetime) }}
                </span>
            </div>
            <p class="mt-2 text-[11px] text-stone-500">{{ totals.totalCount }} giao dịch đã ghi nhận</p>
        </section>

        <section class="rounded-2xl border border-stone-200 bg-white p-4 shadow-sm">
            <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-stone-500">
                <CalendarRange class="size-4" /> 30 ngày gần nhất
            </div>
            <div class="mt-2 grid grid-cols-3 gap-2 text-center">
                <div class="rounded-xl bg-emerald-50 p-2">
                    <p class="text-[10px] font-semibold uppercase text-emerald-700">Nạp</p>
                    <p class="mt-0.5 font-mono text-xs font-bold text-emerald-800">+{{
                        formatVnd(last30Days.totalCreditVnd) }}</p>
                </div>
                <div class="rounded-xl bg-rose-50 p-2">
                    <p class="text-[10px] font-semibold uppercase text-rose-700">Rút</p>
                    <p class="mt-0.5 font-mono text-xs font-bold text-rose-800">-{{ formatVnd(last30Days.totalDebitVnd)
                    }}</p>
                </div>
                <div class="rounded-xl bg-stone-100 p-2">
                    <p class="text-[10px] font-semibold uppercase text-stone-700">Ròng</p>
                    <p class="mt-0.5 font-mono text-xs font-bold"
                        :class="net30 >= 0 ? 'text-emerald-800' : 'text-rose-800'">
                        {{ net30 >= 0 ? '+' : '' }}{{ formatVnd(net30) }}
                    </p>
                </div>
            </div>
            <p class="mt-2 text-[11px] text-stone-500">{{ last30Days.totalCount }} giao dịch trong 30 ngày qua</p>
        </section>





        <Link :href="AccountController.wallet.url()"
            class="flex items-center justify-center gap-2 rounded-xl border border-stone-200 bg-white px-4 py-2.5 text-sm font-semibold text-stone-700 shadow-sm transition active:scale-99 hover:bg-stone-50">
            <History class="size-4" />
            Xem lịch sử giao dịch chi tiết
        </Link>
    </div>
</template>
