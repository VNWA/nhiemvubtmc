<script setup lang="ts">
import EventBetController from '@/actions/App/Http/Controllers/Sukien/EventBetController';
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
import { Check, ChevronDown, History, Timer, Users, Wallet, X } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import CButton from '@/components/client/CButton.vue';

type Opt = { id: number; label: string; bg_color: string; text_color: string };
type OpenRoundT = {
    id: number;
    round_number: number;
    name: string;
    started_at: string | null;
    auto_end_at: string | null;
    duration_seconds: number | null;
};
type RoundHistory = {
    id: number;
    round_number: number;
    name: string;
    ended_at: string | null;
};
type UserBet = {
    id: number;
    option_ids: number[];
    option_labels: string[];
    amount_vnd: number;
    status: 'pending' | 'completed';
    is_settled: boolean;
};

const QUICK_AMOUNTS = [100_000, 300_000, 400_000, 500_000];

const props = defineProps<{
    eventRoom: {
        id: number;
        name: string;
        slug: string;
        avatar_url: string | null;
        is_active: boolean;
        /** Kỳ đếm phiên — lịch sử giao dịch chỉ thuộc kỳ này. */
        round_session: number;
    };
    options: Opt[];
    openRound: OpenRoundT | null;
    recentRounds: RoundHistory[];
    recentRoundsTotal: number;
    recentRoundsPerPage: number;
    userBet: UserBet | null;
    betsStats: { betsCount: number; totalAmountVnd: number } | null;
    isAdmin: boolean;
    userBalanceVnd: number;
}>();

const liveOpenRound = ref<OpenRoundT | null>(props.openRound ? { ...props.openRound } : null);
const liveUserBet = ref<UserBet | null>(props.userBet ? { ...props.userBet } : null);
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
let settlementPollHandle: ReturnType<typeof setInterval> | null = null;

function syncPresenceList() {
    presenceCount.value = presenceMembers.length;
    presenceNames.value = presenceMembers.map((m) => m.name);
}

// Synthetic viewer count: a random fake offset between 500 and 1000 added on
// top of the real presence count, refreshed every second to feel "alive".
// Kept purely client-side — no DB column / no backend round-trip.
const FAKE_VIEWER_MIN = 500;
const FAKE_VIEWER_MAX = 1000;
function randomFakeViewers(): number {
    return (
        Math.floor(Math.random() * (FAKE_VIEWER_MAX - FAKE_VIEWER_MIN + 1)) +
        FAKE_VIEWER_MIN
    );
}
const fakeViewerOffset = ref<number>(randomFakeViewers());
let fakeViewerHandle: ReturnType<typeof setInterval> | null = null;

const displayedPresenceCount = computed(
    () => presenceCount.value + fakeViewerOffset.value,
);

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

    // Refresh fake viewer offset every second.
    fakeViewerHandle = setInterval(() => {
        fakeViewerOffset.value = randomFakeViewers();
    }, 2000);

    unsubPublic = subscribeSukienPublicChannel(props.eventRoom.id, {
        onRoundStarted: (p: SukienRoundPayload) => {
            if (p.eventRoomId !== props.eventRoom.id) {
                return;
            }
            liveOpenRound.value = {
                id: p.eventRoundId,
                round_number: p.roundNumber,
                name: `Phiên #${p.roundNumber}`,
                started_at: new Date().toISOString(),
                auto_end_at: p.autoEndAt ?? null,
                duration_seconds: null,
            };
            liveUserBet.value = null;
            resetSelection();
            liveBetsCount.value = 0;
            liveTotalVnd.value = 0;
        },
        onRoundEnded: () => {
            liveOpenRound.value = null;
            liveUserBet.value = null;
            resetSelection();
            liveBetsCount.value = 0;
            liveTotalVnd.value = 0;
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

    // While the user has an unsettled bet on an open round, lightly poll so
    // settlements pushed by admin/staff (refund + commission) reflect in the
    // UI quickly — otherwise the cancel button would stay visible until the
    // next page refresh, even though the server already rejects the request.
    settlementPollHandle = setInterval(() => {
        const bet = liveUserBet.value;
        if (!bet || bet.is_settled) {
            return;
        }
        if (!liveOpenRound.value || timerExpired.value) {
            return;
        }
        if (typeof document !== 'undefined' && document.hidden) {
            return;
        }
        router.reload({ only: ['userBet', 'userBalanceVnd'] });
    }, 6000);
});

onUnmounted(() => {
    unsubPublic?.();
    unsubPresence?.();
    if (tickHandle) {
        clearInterval(tickHandle);
    }
    if (fakeViewerHandle) {
        clearInterval(fakeViewerHandle);
    }
    if (endReloadHandle) {
        clearTimeout(endReloadHandle);
    }
    if (settlementPollHandle) {
        clearInterval(settlementPollHandle);
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
        resetSelection();
        historyPage.value = 1;
        historyExtra.value = [];
        historyHasMore.value = props.recentRoundsTotal > props.recentRounds.length;
    },
    { deep: true },
);

const selectedIds = ref<Set<number>>(new Set());
const draftAmount = ref<number>(0);

function resetSelection() {
    selectedIds.value = new Set();
    draftAmount.value = 0;
}

const hasPlacedBet = computed(() => liveUserBet.value !== null);
const betSettled = computed(() => liveUserBet.value?.is_settled === true);

function isSelected(optionId: number): boolean {
    return selectedIds.value.has(optionId);
}

function toggleOption(optionId: number) {
    if (hasPlacedBet.value) {
        return;
    }
    const next = new Set(selectedIds.value);
    if (next.has(optionId)) {
        next.delete(optionId);
    } else {
        next.add(optionId);
    }
    selectedIds.value = next;
}

function selectAllAvailable() {
    if (hasPlacedBet.value) {
        return;
    }
    selectedIds.value = new Set(props.options.map((o) => o.id));
}

const selectedCount = computed(() => selectedIds.value.size);

const totalDraft = computed(() => draftAmount.value);

function setDraftAmount(n: number) {
    draftAmount.value = Math.max(0, Math.min(n, 1_000_000_000));
}

function applyQuickAmount(n: number) {
    draftAmount.value = n;
}

function resetAllDrafts() {
    resetSelection();
}

watch(liveBalance, () => {
    if (totalDraft.value > liveBalance.value) {
        draftAmount.value = 0;
    }
});

const insufficientBalance = computed(() => totalDraft.value > liveBalance.value);

const canPlaceBet = computed(() => {
    if (!liveOpenRound.value || timerExpired.value) {
        return false;
    }
    if (hasPlacedBet.value) {
        return false;
    }
    if (selectedCount.value === 0) {
        return false;
    }
    if (draftAmount.value < 1000) {
        return false;
    }
    if (insufficientBalance.value) {
        return false;
    }
    return true;
});

const betForm = useForm<{ option_ids: number[]; amount_vnd: number }>({
    option_ids: [],
    amount_vnd: 0,
});

function submitBet() {
    if (!canPlaceBet.value) {
        return;
    }
    const total = draftAmount.value;
    const optionIds = props.options
        .filter((o) => selectedIds.value.has(o.id))
        .map((o) => o.id);
    if (optionIds.length === 0) {
        return;
    }
    betForm.option_ids = optionIds;
    betForm.amount_vnd = total;
    betForm.post(EventBetController.store.url(props.eventRoom.slug), {
        preserveScroll: true,
        onSuccess: () => {
            betForm.clearErrors();
            resetSelection();
        },
    });
}

const cancelForm = useForm<Record<string, never>>({});
const cancelError = ref<string | null>(null);
const cancellingAll = ref(false);

function cancelAllBets() {
    if (!liveUserBet.value) {
        return;
    }
    if (liveUserBet.value.is_settled) {
        cancelError.value = 'Phiên đã được xử lý hoàn trả/hoa hồng, không thể huỷ.';
        return;
    }
    if (!confirm('Huỷ tham gia phiên này? Số tiền sẽ được hoàn lại số dư.')) {
        return;
    }
    cancelError.value = null;
    cancellingAll.value = true;
    cancelForm.delete(EventBetController.destroy.url(props.eventRoom.slug), {
        preserveScroll: true,
        onSuccess: () => {
            cancellingAll.value = false;
        },
        onError: (errors) => {
            cancelError.value = (errors.bet as string | undefined) ?? 'Không thể huỷ tham gia.';
            cancellingAll.value = false;
        },
        onFinish: () => {
            cancellingAll.value = false;
        },
    });
}

const placedOptionLabels = computed(() => liveUserBet.value?.option_labels ?? []);
const placedAmount = computed(() => liveUserBet.value?.amount_vnd ?? 0);

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
        historyError.value = e instanceof Error ? e.message : 'Không thể tải thêm phiên.';
    } finally {
        historyLoading.value = false;
    }
}
</script>

<template>

    <Head :title="eventRoom.name" />

    <div class="flex flex-col gap-2 px-2 pb-6 pt-2">
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
                        <span>{{ displayedPresenceCount }} đang xem</span>
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
            class="flex items-center justify-between gap-2 rounded-xl border-2 border-amber-300 bg-linear-to-r from-amber-50 to-amber-100 px-2 py-2 shadow-sm">
            <div class="flex min-w-0 items-center gap-2">
                <div
                    class="flex size-9 shrink-0 items-center justify-center rounded-full bg-amber-200 text-sm font-bold text-amber-900">
                    #{{ liveOpenRound.round_number }}
                </div>
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-amber-900/80">Phiên đang mở</p>
                    <p class="truncate text-sm font-medium text-stone-800">{{ liveOpenRound.name }}</p>
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
            Chưa có phiên nào đang mở. Vui lòng đợi phiên mới.
        </section>

        <section v-if="liveOpenRound && !isAdmin" class="rounded-xl border border-stone-200 bg-white p-2 shadow-sm">
            <div class="space-y-2">
                <div v-if="!hasPlacedBet"
                    class="flex flex-col gap-1.5 min-[400px]:flex-row min-[400px]:items-center min-[400px]:justify-between">
                    <p class="min-w-0 text-xs leading-snug text-stone-500">
                        Tick các mục muốn tham gia (có thể chọn tất cả) rồi nhập số tiền.
                    </p>
                    <button type="button"
                        class="shrink-0 self-start text-[11px] font-semibold text-amber-700 underline underline-offset-2 min-[400px]:self-auto hover:text-amber-800"
                        @click="selectAllAvailable">
                        Chọn tất cả
                    </button>
                </div>

                <ul class="grid grid-cols-1 gap-1.5 min-[360px]:grid-cols-2">
                    <li v-for="o in options" :key="o.id" class="min-w-0">
                        <button type="button" class="option-chip w-full min-w-0" :class="{
                            'option-chip-placed': hasPlacedBet,
                            'option-chip-selected': !hasPlacedBet && isSelected(o.id),
                        }" :disabled="hasPlacedBet" :title="o.label" @click="toggleOption(o.id)">
                            <span class="option-chip-label"
                                :style="{ backgroundColor: o.bg_color, color: o.text_color }">{{ o.label }}

                            </span>
                            <span class="option-chip-check">
                                <template v-if="hasPlacedBet">
                                    <span class="inline-flex size-5 items-center justify-center rounded border-2"
                                        :class="liveUserBet?.option_ids.includes(o.id)
                                            ? 'border-emerald-500 bg-emerald-500 text-white'
                                            : 'border-stone-200 bg-stone-50'">
                                        <Check v-if="liveUserBet?.option_ids.includes(o.id)" class="size-3.5" />
                                    </span>
                                </template>
                                <template v-else>
                                    <span class="inline-flex size-5 items-center justify-center rounded border-2"
                                        :class="isSelected(o.id) ? 'border-amber-600 bg-amber-600 text-white' : 'border-stone-300 bg-white'">
                                        <Check v-if="isSelected(o.id)" class="size-3.5" />
                                    </span>
                                </template>
                            </span>
                        </button>
                    </li>
                </ul>

                <div v-if="hasPlacedBet" class="rounded-lg border border-emerald-200 bg-emerald-50/60 px-3 py-2">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-xs text-emerald-900">
                            Đã tham gia
                            <strong class="font-mono text-sm">{{ formatVnd(placedAmount) }}</strong>
                            với mục:
                            <strong>{{ placedOptionLabels.join(', ') }}</strong>
                            <span v-if="betSettled"
                                class="ml-1 inline-flex items-center rounded-full bg-emerald-200 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-emerald-900">
                                Đã xử lý
                            </span>
                        </p>
                        <CButton v-if="!betSettled" type="button" variant="danger" size="sm" class="cancel-bet-btn"
                            :disabled="cancelForm.processing || timerExpired || cancelLocked || cancellingAll"
                            @click="cancelAllBets">
                            <X class="size-3.5" />
                            {{ cancellingAll ? 'Đang huỷ…' : 'Huỷ' }}
                        </CButton>
                    </div>
                    <p v-if="betSettled" class="mt-1 text-[11px] text-emerald-800">
                        Phiên đã được hoàn trả/hoa hồng. Số tiền tương ứng đã được cộng vào số dư.
                    </p>
                </div>

                <div v-if="!hasPlacedBet">
                    <label class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-stone-600">
                        Số tiền tham gia
                    </label>
                    <CurrencyInput id="bet-amount" :model-value="draftAmount" @update:model-value="setDraftAmount"
                        :max="liveBalance" placeholder="0" input-class="bet-amount-input w-full" />

                    <div class="mt-2 grid grid-cols-4 gap-1.5">
                        <button v-for="amt in QUICK_AMOUNTS" :key="amt" type="button" class="quick-chip"
                            :disabled="amt > liveBalance" @click="applyQuickAmount(amt)">
                            {{ amt >= 1_000_000 ? `${amt / 1_000_000}M` : `${amt / 1_000}K` }}
                        </button>
                    </div>

                    <p v-if="selectedCount > 0" class="mt-2 text-[11px] text-stone-500">
                        Bạn tham gia phiên với {{ selectedCount }} mục đã chọn.
                    </p>

                    <div class="mt-2 flex gap-2">
                        <CButton type="button" variant="ghost" size="md" class="flex-1"
                            :disabled="selectedCount === 0 && draftAmount === 0" @click="resetAllDrafts">
                            Đặt lại
                        </CButton>
                        <CButton type="button" variant="gold" size="md" class="flex-[1.4]"
                            :disabled="!canPlaceBet || betForm.processing" @click="submitBet">
                            {{ betForm.processing ? 'Đang gửi…' : 'Xác nhận' }}
                        </CButton>
                    </div>

                    <p v-if="insufficientBalance" class="mt-1.5 text-[11px] font-medium text-red-600">
                        Số tiền tham gia vượt số dư ({{ formatVnd(liveBalance) }}).
                    </p>
                    <p v-else-if="selectedCount > 0 && draftAmount > 0 && draftAmount < 1000"
                        class="mt-1.5 text-[11px] text-red-600">
                        Số tiền tối thiểu là 1.000đ.
                    </p>
                    <p v-else-if="betForm.errors.option_ids" class="mt-1.5 text-[11px] text-red-600">
                        {{ betForm.errors.option_ids }}
                    </p>
                    <p v-else-if="betForm.errors.amount_vnd" class="mt-1.5 text-[11px] text-red-600">
                        {{ betForm.errors.amount_vnd }}
                    </p>
                    <p v-else-if="timerExpired" class="mt-1.5 text-[11px] text-red-600">
                        Hết giờ — vui lòng đợi phiên tiếp theo.
                    </p>
                </div>

                <p v-if="cancelError" class="text-xs text-red-600">{{ cancelError }}</p>
                <p v-if="cancelLocked && hasPlacedBet" class="text-[11px] text-stone-500">
                    Còn dưới 5 giây — không thể huỷ tham gia.
                </p>
            </div>
        </section>

        <section class="rounded-xl border border-stone-200 bg-stone-50/80 p-3">
            <div class="mb-1.5 flex items-center justify-between gap-2">
                <div>
                    <h3 class="flex items-center gap-1 text-sm font-semibold text-stone-800">
                        <History class="size-4" />
                        Các phiên đã kết thúc
                    </h3>
                    <p class="mt-0.5 text-[11px] text-stone-500">
                        Kỳ đếm #{{ eventRoom.round_session }} — chỉ liệt kê phiên thuộc kỳ này
                    </p>
                </div>
                <span class="shrink-0 text-[11px] text-stone-500">{{ displayedRounds.length }}/{{
                    recentRoundsTotal }}</span>
            </div>
            <ul v-if="displayedRounds.length" class="space-y-1 text-sm">
                <li v-for="h in displayedRounds" :key="h.id"
                    class="flex items-center justify-between gap-2 rounded-lg bg-white px-2 py-1.5">
                    <span class="text-xs text-stone-700">Phiên #{{ h.round_number }}</span>
                    <span class="text-[11px] text-stone-500">{{ h.name }}</span>
                </li>
            </ul>
            <p v-else class="text-xs text-stone-500">Chưa có phiên nào kết thúc.</p>

            <div v-if="historyHasMore" class="mt-2">
                <CButton type="button" variant="outline" size="sm" block :disabled="historyLoading"
                    @click="loadMoreRounds">
                    <ChevronDown v-if="!historyLoading" class="size-4" />
                    {{ historyLoading ? 'Đang tải…' : `Xem thêm (còn ${recentRoundsTotal - displayedRounds.length})` }}
                </CButton>
            </div>
            <p v-if="historyError" class="mt-1 text-[11px] text-red-600">{{ historyError }}</p>
        </section>

        <span v-if="rtConnected" class="hidden">{{ presenceNames.length }}</span>
    </div>
</template>

<style scoped>
:deep(.bet-amount-input) {
    height: 2.25rem;
    font-size: 0.95rem;
    font-weight: 700;
    border-width: 1.5px;
    border-radius: 0.5rem;
    background-color: white;
}

:deep(.bet-amount-input:focus) {
    box-shadow: 0 0 0 3px rgb(254 243 199);
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

.option-chip {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.375rem;
    min-width: 0;
    min-height: 2.75rem;
    padding: 0.375rem 0.5rem;
    border: 2px solid rgb(231 229 228);
    border-radius: 0.625rem;
    background-color: white;
    text-align: left;
    transition: border-color 150ms ease, background-color 150ms ease, transform 100ms ease;
    cursor: pointer;
    user-select: none;
}

.option-chip-label {
    min-width: 0;
    flex: 1 1 0;
    border-radius: 0.375rem;
    padding: 0.375rem 0.5rem;
    font-size: 0.6875rem;
    font-weight: 600;
    line-height: 1.3;
    letter-spacing: 0.01em;
    overflow-wrap: anywhere;
    word-break: break-word;
    display: -webkit-box;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

@media (min-width: 400px) {
    .option-chip-label {
        font-size: 0.75rem;
    }
}

.option-chip-check {
    display: flex;
    flex-shrink: 0;
    align-items: center;
    align-self: center;
    justify-content: center;
    width: 1.25rem;
    height: 1.25rem;
}

.option-chip:hover:not(:disabled) {
    border-color: rgb(252 211 77);
    background-color: rgb(255 251 235);
}

.option-chip:active:not(:disabled) {
    transform: scale(0.98);
}

.option-chip-selected {
    border-color: rgb(217 119 6);
    background-color: rgb(255 251 235);
    box-shadow: 0 0 0 3px rgb(254 243 199);
}

.option-chip-placed {
    border-color: rgb(167 243 208);
    background-color: rgb(236 253 245);
    cursor: not-allowed;
    opacity: 0.85;
}

.cancel-bet-btn {
    flex-shrink: 0;
}
</style>
