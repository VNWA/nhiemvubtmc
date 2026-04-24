<script setup lang="ts">
import EventBetController from '@/actions/App/Http/Controllers/Sukien/EventBetController';
import EventRoomController from '@/actions/App/Http/Controllers/Admin/EventRoomController';
import SukienEventRoomController from '@/actions/App/Http/Controllers/Sukien/SukienEventRoomController';
import CurrencyInput from '@/components/CurrencyInput.vue';
import { formatVnd } from '@/lib/vnd';
import {
    echo,
    joinSukienPresence,
    subscribeSukienPublicChannel,
    type PresenceMember,
    type SukienRoundPayload,
    type SukienStatsPayload,
} from '@/echo';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Check, ChevronDown, History, Settings2, Timer, Users, Wallet, Wifi, WifiOff, X } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { home } from '@/routes';

type Opt = { id: number; label: string; bg_color: string; text_color: string };
type OpenRoundT = {
    id: number;
    round_number: number;
    name: string;
    started_at: string | null;
    auto_end_at: string | null;
    duration_seconds: number | null;
    preset: { id: number; label: string; bg_color: string; text_color: string } | null;
};
type RoundHistory = {
    id: number;
    round_number: number;
    name: string;
    ended_at: string | null;
    preset: { label: string; bg_color: string; text_color: string };
};

const QUICK_AMOUNTS = [100_000, 300_000, 400_000, 500_000];

const props = defineProps<{
    eventRoom: { id: number; name: string; slug: string; avatar_url: string | null; is_active: boolean };
    options: Opt[];
    openRound: OpenRoundT | null;
    recentRounds: RoundHistory[];
    recentRoundsTotal: number;
    recentRoundsPerPage: number;
    userBet: { option_id: number; option_label: string; amount_vnd: number } | null;
    betsStats: { betsCount: number; totalAmountVnd: number } | null;
    isAdmin: boolean;
    userBalanceVnd: number;
}>();

const liveOpenRound = ref<OpenRoundT | null>(props.openRound ? { ...props.openRound } : null);
const liveUserBet = ref(props.userBet ? { ...props.userBet } : null);
const liveBalance = ref<number>(props.userBalanceVnd ?? 0);
const liveBetsCount = ref(props.betsStats?.betsCount ?? 0);
const liveTotalVnd = ref(props.betsStats?.totalAmountVnd ?? 0);
const presenceCount = ref(0);
const presenceNames = ref<string[]>([]);
const rtConnected = ref(!!echo);
const now = ref(Date.now());

let unsubPublic: (() => void) | null = null;
let unsubPresence: (() => void) | null = null;
let presenceMembers: PresenceMember[] = [];
let tickHandle: ReturnType<typeof setInterval> | null = null;
let endReloadHandle: ReturnType<typeof setTimeout> | null = null;

function syncPresenceList() {
    presenceCount.value = presenceMembers.length;
    presenceNames.value = presenceMembers.map((m) => m.name);
}

const remainingMs = computed(() => {
    const end = liveOpenRound.value?.auto_end_at;
    if (!end) {
        return null;
    }
    const diff = new Date(end).getTime() - now.value;
    return diff > 0 ? diff : 0;
});

const remainingLabel = computed(() => {
    const ms = remainingMs.value;
    if (ms === null) {
        return null;
    }
    const total = Math.ceil(ms / 1000);
    const m = Math.floor(total / 60);
    const s = total % 60;
    return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
});

const timerExpired = computed(
    () => liveOpenRound.value?.auto_end_at !== null && liveOpenRound.value?.auto_end_at !== undefined && remainingMs.value === 0,
);

const CANCEL_LOCK_MS = 5_000;
const cancelLocked = computed(
    () => remainingMs.value !== null && remainingMs.value > 0 && remainingMs.value <= CANCEL_LOCK_MS,
);

const timerColorClass = computed(() => {
    const ms = remainingMs.value;
    if (ms === null) {
        return 'bg-stone-100 text-stone-700';
    }
    if (ms <= 5_000) {
        return 'bg-red-100 text-red-700';
    }
    if (ms <= 15_000) {
        return 'bg-amber-100 text-amber-800';
    }
    return 'bg-emerald-100 text-emerald-800';
});

watch(timerExpired, (expired) => {
    if (!expired) {
        return;
    }
    if (endReloadHandle) {
        clearTimeout(endReloadHandle);
    }
    endReloadHandle = setTimeout(() => {
        router.reload({ only: ['recentRounds', 'recentRoundsTotal', 'userBet', 'betsStats', 'openRound', 'userBalanceVnd'] });
    }, 800);
});

onMounted(() => {
    tickHandle = setInterval(() => {
        now.value = Date.now();
    }, 250);

    unsubPublic = subscribeSukienPublicChannel(props.eventRoom.id, {
        onRoundStarted: (p: SukienRoundPayload) => {
            if (p.eventRoomId !== props.eventRoom.id) {
                return;
            }
            liveOpenRound.value = {
                id: p.eventRoundId,
                round_number: p.roundNumber,
                name: `Kỳ #${p.roundNumber}`,
                started_at: new Date().toISOString(),
                auto_end_at: p.autoEndAt ?? null,
                duration_seconds: null,
                preset: p.presetOption,
            };
            liveUserBet.value = null;
            liveBetsCount.value = 0;
            liveTotalVnd.value = 0;
        },
        onRoundEnded: () => {
            liveOpenRound.value = null;
            liveUserBet.value = null;
            liveBetsCount.value = 0;
            liveTotalVnd.value = 0;
            // Force a hard reload of recent rounds list and reset pagination state.
            historyPage.value = 1;
            historyHasMore.value = false;
            historyExtra.value = [];
            router.reload({ only: ['recentRounds', 'recentRoundsTotal', 'userBet', 'betsStats', 'openRound', 'userBalanceVnd'] });
        },
        onStats: (p: SukienStatsPayload) => {
            if (p.eventRoomId !== props.eventRoom.id) {
                return;
            }
            if (liveOpenRound.value && p.eventRoundId === liveOpenRound.value.id) {
                liveBetsCount.value = p.betsCount;
                liveTotalVnd.value = p.totalAmountVnd;
            }
        },
    });
    unsubPresence = joinSukienPresence(props.eventRoom.id, {
        onHere: (users) => {
            presenceMembers = users.slice();
            syncPresenceList();
        },
        onJoining: (m) => {
            if (!presenceMembers.some((u) => u.id === m.id)) {
                presenceMembers.push(m);
            }
            syncPresenceList();
        },
        onLeaving: (m) => {
            presenceMembers = presenceMembers.filter((u) => u.id !== m.id);
            syncPresenceList();
        },
    });
});

onUnmounted(() => {
    unsubPublic?.();
    unsubPresence?.();
    if (tickHandle) {
        clearInterval(tickHandle);
    }
    if (endReloadHandle) {
        clearTimeout(endReloadHandle);
    }
});

watch(
    () => [props.openRound, props.userBet, props.betsStats, props.userBalanceVnd, props.recentRoundsTotal] as const,
    () => {
        liveOpenRound.value = props.openRound ? { ...props.openRound } : null;
        liveUserBet.value = props.userBet ? { ...props.userBet } : null;
        liveBalance.value = props.userBalanceVnd ?? 0;
        if (props.betsStats) {
            liveBetsCount.value = props.betsStats.betsCount;
            liveTotalVnd.value = props.betsStats.totalAmountVnd;
        }
        // Reset pagination state when the base list refreshes.
        historyPage.value = 1;
        historyExtra.value = [];
        historyHasMore.value = props.recentRoundsTotal > props.recentRounds.length;
    },
    { deep: true },
);

const selectedOptionId = ref<number | null>(props.options[0]?.id ?? null);
const amountVnd = ref(0);

function setAmount(n: number) {
    amountVnd.value = Math.max(0, Math.min(n, liveBalance.value));
}

function resetAmount() {
    setAmount(0);
}

watch(liveBalance, (b) => {
    if (amountVnd.value > b) setAmount(b);
});

const betForm = useForm({
    option_id: 0,
    amount_vnd: 0,
});

function submitBet() {
    if (!selectedOptionId.value) {
        return;
    }
    const amt = amountVnd.value;
    if (amt < 1000) {
        return;
    }
    if (amt > liveBalance.value) {
        return;
    }
    betForm.option_id = selectedOptionId.value;
    betForm.amount_vnd = amt;
    betForm.post(EventBetController.store.url(props.eventRoom.slug), {
        preserveScroll: true,
        onSuccess: () => {
            betForm.clearErrors();
            resetAmount();
            liveBalance.value = Math.max(0, liveBalance.value - amt);
            router.reload({ only: ['userBet', 'betsStats', 'userBalanceVnd'] });
        },
    });
}

const cancelForm = useForm({});
const cancelError = ref<string | null>(null);

function cancelBet() {
    if (!liveUserBet.value) {
        return;
    }
    if (!confirm('Huỷ đặt cược kỳ này? Số tiền sẽ được hoàn lại số dư.')) {
        return;
    }
    cancelError.value = null;
    const refundAmount = liveUserBet.value.amount_vnd;
    cancelForm.delete(EventBetController.destroy.url(props.eventRoom.slug), {
        preserveScroll: true,
        onSuccess: () => {
            liveBalance.value = liveBalance.value + refundAmount;
            liveUserBet.value = null;
            router.reload({ only: ['userBet', 'betsStats', 'userBalanceVnd'] });
        },
        onError: (errors) => {
            cancelError.value = (errors.bet as string | undefined) ?? 'Không thể huỷ đặt cược.';
        },
    });
}

const insufficientBalance = computed(() => amountVnd.value > liveBalance.value);

const canPlaceBet = computed(
    () =>
        !!liveOpenRound.value &&
        !liveUserBet.value &&
        !timerExpired.value &&
        selectedOptionId.value !== null &&
        amountVnd.value >= 1000 &&
        amountVnd.value <= liveBalance.value,
);

// Pagination for recent rounds.
const historyPage = ref(1);
const historyExtra = ref<RoundHistory[]>([]);
const historyHasMore = ref<boolean>(props.recentRoundsTotal > props.recentRounds.length);
const historyLoading = ref(false);
const historyError = ref<string | null>(null);

const displayedRounds = computed<RoundHistory[]>(() => [
    ...props.recentRounds,
    ...historyExtra.value,
]);

async function loadMoreRounds() {
    if (historyLoading.value || !historyHasMore.value) {
        return;
    }
    historyLoading.value = true;
    historyError.value = null;
    try {
        const nextPage = historyPage.value + 1;
        const url = SukienEventRoomController.roundsHistory.url(props.eventRoom.slug);
        const res = await fetch(`${url}?page=${nextPage}`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        if (!res.ok) {
            throw new Error(`HTTP ${res.status}`);
        }
        const json = (await res.json()) as {
            data: RoundHistory[];
            page: number;
            hasMore: boolean;
        };
        historyExtra.value = [...historyExtra.value, ...json.data];
        historyPage.value = json.page;
        historyHasMore.value = json.hasMore;
    } catch (e) {
        historyError.value = e instanceof Error ? e.message : 'Không thể tải thêm kỳ.';
    } finally {
        historyLoading.value = false;
    }
}
</script>

<template>

    <Head :title="eventRoom.name" />

    <div class="flex flex-col gap-2 px-3 pb-6 pt-2">
        <header
            class="flex items-center justify-between gap-2 rounded-xl border border-stone-200 bg-white px-3 py-2 shadow-sm">
            <div class="flex min-w-0 items-center gap-2">
                <div v-if="eventRoom.avatar_url"
                    class="flex size-9 shrink-0 items-center justify-center overflow-hidden rounded-full border border-stone-200 bg-stone-100">
                    <img :src="eventRoom.avatar_url" :alt="eventRoom.name" class="size-full object-cover" />
                </div>
                <div class="min-w-0">
                    <h2 class="truncate text-sm font-bold leading-tight text-stone-900">
                        {{ eventRoom.name }}
                    </h2>
                    <p class="flex items-center gap-1 text-xs text-stone-500">
                        <Users class="size-3" />
                        <span>{{ presenceCount }} đang xem</span>
                    </p>
                </div>
            </div>
            <div class="flex shrink-0 items-center gap-1.5">
                <Link :href="SukienEventRoomController.index.url()" class="text-amber-800 underline text-xs">
                    ← Tất cả sự kiện
                </Link>
            </div>
        </header>

        <div v-if="!isAdmin"
            class="flex items-center justify-between gap-2 rounded-xl border border-amber-200 bg-amber-50 px-3 py-1.5 shadow-sm">
            <span class="flex items-center gap-1 text-xs font-medium text-amber-900">
                <Wallet class="size-3.5" />
                Số dư
            </span>
            <span class="font-mono text-base font-bold text-amber-900">{{ formatVnd(liveBalance) }}</span>
        </div>

        <section v-if="liveOpenRound"
            class="flex items-center justify-between gap-2 rounded-xl border-2 border-amber-300 bg-linear-to-r from-amber-50 to-amber-100 px-3 py-2 shadow-sm">
            <div class="flex min-w-0 items-center gap-2">
                <div
                    class="flex size-9 shrink-0 items-center justify-center rounded-full bg-amber-200 text-sm font-bold text-amber-900">
                    #{{ liveOpenRound.round_number }}
                </div>
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-amber-900/80">Kỳ đang mở</p>
                    <div v-if="liveOpenRound.preset" class="mt-0.5 flex items-center gap-1">
                        <span class="text-[11px] text-stone-600">Kết quả:</span>
                        <span
                            class="inline-flex items-center gap-0.5 rounded px-1.5 py-0.5 text-xs font-semibold shadow"
                            :style="{
                                backgroundColor: liveOpenRound.preset.bg_color,
                                color: liveOpenRound.preset.text_color,
                            }">
                            <Check class="size-3" />
                            {{ liveOpenRound.preset.label }}
                        </span>
                    </div>
                </div>
            </div>
            <div v-if="remainingLabel !== null"
                class="inline-flex shrink-0 items-center gap-1 rounded-full px-2.5 py-1 text-sm font-semibold"
                :class="timerColorClass">
                <Timer class="size-3.5" />
                <span class="font-mono">{{ remainingLabel }}</span>
            </div>
        </section>
        <section v-else
            class="rounded-xl border border-dashed border-stone-200 bg-stone-50 px-3 py-2 text-center text-xs text-stone-600">
            Chưa có kỳ nào đang mở. Vui lòng đợi quản trị bắt đầu kỳ mới.
        </section>

        <section v-if="liveOpenRound && !isAdmin" class="rounded-xl border border-stone-200 bg-white p-3 shadow-sm">
            <div v-if="liveUserBet" class="space-y-2">
                <div class="rounded-lg bg-emerald-50 p-2 text-sm">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-xs text-emerald-800">Bạn đã đặt</span>
                        <span class="font-mono text-base font-bold text-emerald-900">{{
                            formatVnd(liveUserBet.amount_vnd)
                            }}</span>
                    </div>
                    <div class="mt-1 flex items-center gap-1 text-xs text-emerald-800">
                        cho
                        <span class="inline-flex items-center rounded px-1.5 py-0.5 text-xs font-semibold" :style="{
                            backgroundColor: options.find((o) => o.id === liveUserBet!.option_id)?.bg_color,
                            color: options.find((o) => o.id === liveUserBet!.option_id)?.text_color,
                        }">
                            {{ liveUserBet.option_label }}
                        </span>
                    </div>
                </div>
                <Button type="button" variant="outline" size="sm"
                    class="w-full border-red-200 text-red-700 hover:bg-red-50"
                    :disabled="cancelForm.processing || timerExpired || cancelLocked" @click="cancelBet">
                    <X class="size-4" />
                    {{
                        cancelForm.processing
                            ? 'Đang huỷ…'
                            : timerExpired
                                ? 'Hết giờ — không thể huỷ'
                                : cancelLocked
                                    ? 'Còn dưới 5s — không thể huỷ'
                                    : 'Huỷ đặt cược (hoàn tiền)'
                    }}
                </Button>
                <p v-if="cancelError" class="text-xs text-red-600">{{ cancelError }}</p>
            </div>
            <div v-else class="space-y-2">
                <p class="text-xs text-stone-500">Chọn 1 mục, nhập số tiền (tối thiểu 1.000đ).</p>
                <div class="grid grid-cols-2 gap-1.5">
                    <button v-for="o in options" :key="o.id" type="button"
                        class="flex items-center gap-1.5 rounded-lg border-2 px-2 py-1.5 text-left text-sm transition active:scale-95"
                        :class="selectedOptionId === o.id
                            ? 'border-amber-500 bg-amber-50'
                            : 'border-stone-200 bg-white'
                            " @click="selectedOptionId = o.id">
                        <span class="flex size-3.5 shrink-0 items-center justify-center rounded-full border-2"
                            :class="selectedOptionId === o.id ? 'border-amber-600 bg-amber-500' : 'border-stone-300'" />
                        <span
                            class="inline-flex flex-1 items-center justify-center rounded px-1.5 py-0.5 text-xs font-semibold"
                            :style="{ backgroundColor: o.bg_color, color: o.text_color }">
                            {{ o.label }}
                        </span>
                    </button>
                </div>

                <div>
                    <label
                        for="vnd-amount"
                        class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-stone-600"
                    >
                        Số tiền cược
                    </label>
                    <CurrencyInput
                        id="vnd-amount"
                        v-model="amountVnd"
                        :max="liveBalance"
                        :aria-invalid="insufficientBalance"
                        input-class="bet-amount-input"
                    />

                    <div class="mt-2 grid grid-cols-4 gap-1.5">
                        <button
                            v-for="amt in QUICK_AMOUNTS"
                            :key="amt"
                            type="button"
                            class="quick-chip"
                            :class="{ 'is-active': amountVnd === amt }"
                            :disabled="amt > liveBalance"
                            @click="setAmount(amt)"
                        >
                            {{ amt >= 1_000_000 ? `${amt / 1_000_000}M` : `${amt / 1_000}K` }}
                        </button>
                    </div>

                    <div class="mt-3 flex gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="h-10 flex-1 rounded-lg border-stone-300 text-stone-700 hover:bg-stone-50"
                            :disabled="amountVnd === 0"
                            @click="resetAmount"
                        >
                            Đặt lại
                        </Button>
                        <Button
                            class="h-10 flex-2 rounded-lg bg-amber-600 text-base font-semibold text-white shadow-sm hover:bg-amber-700"
                            size="sm"
                            :disabled="!canPlaceBet || betForm.processing"
                            @click="submitBet"
                        >
                            {{ betForm.processing ? 'Đang gửi…' : 'Xác nhận đặt' }}
                        </Button>
                    </div>

                    <p v-if="insufficientBalance" class="mt-1.5 text-[11px] font-medium text-red-600">
                        Số tiền vượt số dư ({{ formatVnd(liveBalance) }}).
                    </p>
                    <p v-else-if="betForm.errors.amount_vnd" class="mt-1.5 text-[11px] text-red-600">
                        {{ betForm.errors.amount_vnd }}
                    </p>
                    <p v-else-if="timerExpired" class="mt-1.5 text-[11px] text-red-600">
                        Hết giờ — vui lòng đợi kỳ tiếp theo.
                    </p>
                </div>
            </div>
        </section>

        <section class="rounded-xl border border-stone-200 bg-stone-50/80 p-3">
            <div class="mb-1.5 flex items-center justify-between">
                <h3 class="flex items-center gap-1 text-sm font-semibold text-stone-800">
                    <History class="size-4" />
                    Các kỳ đã kết thúc
                </h3>
                <span class="text-[11px] text-stone-500">{{ displayedRounds.length }}/{{ recentRoundsTotal }}</span>
            </div>
            <ul v-if="displayedRounds.length" class="space-y-1 text-sm">
                <li v-for="h in displayedRounds" :key="h.id"
                    class="flex items-center justify-between gap-2 rounded-lg bg-white px-2 py-1.5">
                    <span class="text-xs text-stone-700">Kỳ #{{ h.round_number }}</span>
                    <span class="rounded px-1.5 py-0.5 text-[11px] font-medium" :style="{
                        backgroundColor: h.preset.bg_color,
                        color: h.preset.text_color,
                    }">
                        {{ h.preset.label }}
                    </span>
                </li>
            </ul>
            <p v-else class="text-xs text-stone-500">Chưa có kỳ nào kết thúc.</p>

            <div v-if="historyHasMore" class="mt-2">
                <Button type="button" variant="outline" size="sm" class="w-full" :disabled="historyLoading"
                    @click="loadMoreRounds">
                    <ChevronDown v-if="!historyLoading" class="size-4" />
                    {{ historyLoading ? 'Đang tải…' : `Xem thêm (còn ${recentRoundsTotal - displayedRounds.length})` }}
                </Button>
            </div>
            <p v-if="historyError" class="mt-1 text-[11px] text-red-600">{{ historyError }}</p>
        </section>

        <div class="text-center text-xs">

        </div>
    </div>
</template>

<style scoped>
/* Make the reused CurrencyInput larger inside the betting card. */
:deep(.bet-amount-input) {
    height: 3rem;
    font-size: 1.375rem;
    font-weight: 700;
    border-width: 2px;
    border-radius: 0.75rem;
}

:deep(.bet-amount-input:focus) {
    box-shadow: 0 0 0 4px rgb(254 243 199);
}

.quick-chip {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    height: 2rem;
    padding: 0 0.5rem;
    border: 1.5px solid rgb(231 229 228);
    border-radius: 0.5rem;
    background-color: white;
    color: rgb(68 64 60);
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace;
    font-size: 0.8125rem;
    font-weight: 700;
    letter-spacing: 0.025em;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
    transition: transform 100ms ease, border-color 150ms ease, background-color 150ms ease, color 150ms ease;
    cursor: pointer;
    user-select: none;
}

.quick-chip:hover:not(:disabled) {
    border-color: rgb(252 211 77);
    background-color: rgb(255 251 235);
    color: rgb(146 64 14);
}

.quick-chip:active:not(:disabled) {
    transform: scale(0.96);
}

.quick-chip:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

.quick-chip.is-active {
    border-color: rgb(217 119 6);
    background-color: rgb(254 243 199);
    color: rgb(146 64 14);
    box-shadow: 0 0 0 3px rgb(254 243 199);
}
</style>
