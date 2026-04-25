<script setup lang="ts">
import EventRoomController from '@/actions/App/Http/Controllers/Admin/EventRoomController';
import EventRoundController from '@/actions/App/Http/Controllers/Admin/EventRoundController';
import SukienEventRoomController from '@/actions/App/Http/Controllers/Sukien/SukienEventRoomController';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    echo,
    joinSukienPresence,
    subscribeSukienPublicChannel,
    type PresenceMember,
    type SukienOptionStat,
    type SukienRoundPayload,
    type SukienStatsPayload,
} from '@/echo';
import { formatVnd } from '@/lib/vnd';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ExternalLink, Pencil, Power, PowerOff, Timer, Users, Wifi, WifiOff } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

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

const props = defineProps<{
    eventRoom: {
        id: number;
        name: string;
        slug: string;
        avatar_url: string | null;
        is_active: boolean;
        viewer_offset: number;
    };
    options: Opt[];
    openRound: OpenRoundT | null;
    betsStats: { betsCount: number; totalAmountVnd: number; perOption: SukienOptionStat[] };
    recentRounds: RoundHistory[];
    durationLimits: { minSeconds: number; maxSeconds: number };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Sự kiện (phòng)', href: EventRoomController.index.url() },
            { title: 'Quản lí phòng', href: '#' },
        ],
    },
});

const liveOpenRound = ref<OpenRoundT | null>(props.openRound ? { ...props.openRound } : null);
const liveBetsCount = ref<number>(props.betsStats.betsCount);
const liveTotalVnd = ref<number>(props.betsStats.totalAmountVnd);
const livePerOption = ref<SukienOptionStat[]>([...props.betsStats.perOption]);
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

const displayedPresenceCount = computed(
    () => presenceCount.value + (props.eventRoom.viewer_offset ?? 0),
);

const ratios = computed(() => {
    const total = liveTotalVnd.value;
    return props.options.map((o) => {
        const stat = livePerOption.value.find((s) => s.optionId === o.id) ?? {
            optionId: o.id,
            betsCount: 0,
            totalAmountVnd: 0,
        };
        const pct = total > 0 ? (stat.totalAmountVnd / total) * 100 : 0;
        return {
            ...o,
            betsCount: stat.betsCount,
            totalAmountVnd: stat.totalAmountVnd,
            percent: pct,
        };
    });
});

watch(timerExpired, (expired) => {
    if (!expired) {
        return;
    }
    if (endReloadHandle) {
        clearTimeout(endReloadHandle);
    }
    endReloadHandle = setTimeout(() => {
        router.reload({ only: ['openRound', 'recentRounds', 'betsStats'] });
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
            liveBetsCount.value = 0;
            liveTotalVnd.value = 0;
            livePerOption.value = [];
            router.reload({ only: ['openRound'] });
        },
        onRoundEnded: () => {
            liveOpenRound.value = null;
            liveBetsCount.value = 0;
            liveTotalVnd.value = 0;
            livePerOption.value = [];
            router.reload({ only: ['openRound', 'recentRounds', 'betsStats'] });
        },
        onStats: (p: SukienStatsPayload) => {
            if (p.eventRoomId !== props.eventRoom.id) {
                return;
            }
            if (liveOpenRound.value && p.eventRoundId === liveOpenRound.value.id) {
                liveBetsCount.value = p.betsCount;
                liveTotalVnd.value = p.totalAmountVnd;
                livePerOption.value = p.perOption ?? [];
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
    () => [props.openRound, props.betsStats] as const,
    () => {
        liveOpenRound.value = props.openRound ? { ...props.openRound } : null;
        liveBetsCount.value = props.betsStats.betsCount;
        liveTotalVnd.value = props.betsStats.totalAmountVnd;
        livePerOption.value = [...props.betsStats.perOption];
    },
    { deep: true },
);

const QUICK_MINUTES = [1, 2, 5, 10, 30];
const minMinutes = Math.max(1, Math.ceil(props.durationLimits.minSeconds / 60));
const maxMinutes = Math.max(minMinutes, Math.floor(props.durationLimits.maxSeconds / 60));

const startForm = useForm({
    preset_option_id: props.options[0]?.id ?? 0,
    name: '' as string,
    duration_minutes: 2,
});

const presetOptionModel = computed<string>({
    get: () => (startForm.preset_option_id ? String(startForm.preset_option_id) : ''),
    set: (v) => {
        startForm.preset_option_id = v ? Number(v) : 0;
    },
});

function setDurationMinutes(m: number) {
    const clamped = Math.max(minMinutes, Math.min(maxMinutes, Math.round(m)));
    startForm.duration_minutes = clamped;
}

function submitStart() {
    if (!startForm.preset_option_id) {
        return;
    }
    startForm
        .transform((data) => {
            const minutes = Math.max(
                minMinutes,
                Math.min(maxMinutes, Math.round(Number(data.duration_minutes) || 0)),
            );
            return {
                preset_option_id: data.preset_option_id,
                name: data.name,
                duration_seconds: minutes * 60,
            };
        })
        .post(EventRoundController.start.url({ event_room: props.eventRoom.id }), {
            preserveScroll: true,
            onSuccess: () => {
                startForm.name = '';
            },
        });
}

const endForm = useForm({});

function submitEnd() {
    if (!liveOpenRound.value) {
        return;
    }
    if (!confirm('Kết thúc kỳ này ngay?')) {
        return;
    }
    endForm.post(
        EventRoundController.end.url({
            event_room: props.eventRoom.id,
            round: liveOpenRound.value.id,
        }),
        { preserveScroll: true },
    );
}
</script>

<template>

    <Head :title="`Quản lí: ${eventRoom.name}`" />

    <div class="flex flex-col gap-4 p-4">
        <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
            <div class="flex items-center gap-3">
                <div
                    class="flex size-12 shrink-0 items-center justify-center overflow-hidden rounded-full border bg-muted">
                    <img v-if="eventRoom.avatar_url" :src="eventRoom.avatar_url" :alt="eventRoom.name"
                        class="size-full object-cover" />
                    <span v-else class="text-xs text-muted-foreground">N/A</span>
                </div>
                <Heading :title="eventRoom.name" :description="`/${eventRoom.slug} · ${options.length} mặt cược`" />
                <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium" :class="eventRoom.is_active
                    ? 'bg-emerald-100 text-emerald-800'
                    : 'bg-stone-200 text-stone-600'
                    ">
                    <Power v-if="eventRoom.is_active" class="size-3" />
                    <PowerOff v-else class="size-3" />
                    {{ eventRoom.is_active ? 'Đang bật' : 'Đang tắt' }}
                </span>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs" :class="rtConnected
                    ? 'bg-emerald-100 text-emerald-800'
                    : 'bg-stone-200 text-stone-600'
                    ">
                    <Wifi v-if="rtConnected" class="size-3.5" />
                    <WifiOff v-else class="size-3.5" />
                    {{ rtConnected ? 'Realtime' : 'Realtime tắt' }}
                </span>
                <Button as-child variant="outline" size="sm">
                    <Link :href="SukienEventRoomController.show.url(eventRoom.slug)">
                        <ExternalLink class="size-4" /> Xem như user
                    </Link>
                </Button>
                <Button as-child variant="secondary" size="sm">
                    <Link :href="EventRoomController.edit.url(eventRoom.id)">
                        <Pencil class="size-4" /> Sửa phòng
                    </Link>
                </Button>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div class="rounded-xl border bg-card p-3">
                <p class="text-xs text-muted-foreground">Người đang xem (hiển thị)</p>
                <p class="mt-1 flex items-center gap-1 text-2xl font-bold">
                    <Users class="size-5 text-amber-700" />
                    {{ displayedPresenceCount }}
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    Thật <span class="font-semibold text-foreground">{{ presenceCount }}</span>
                    <span v-if="eventRoom.viewer_offset > 0">
                        + bù
                        <span class="font-semibold text-amber-700">
                            {{ eventRoom.viewer_offset }}
                        </span>
                    </span>
                </p>
                <p v-if="presenceNames.length" class="mt-1 truncate text-xs text-muted-foreground"
                    :title="presenceNames.join(' · ')">
                    {{ presenceNames.slice(0, 6).join(' · ') }}
                    <span v-if="presenceNames.length > 6"> +{{ presenceNames.length - 6 }}…</span>
                </p>
            </div>
            <div class="rounded-xl border bg-card p-3">
                <p class="text-xs text-muted-foreground">Tổng lượt đặt kỳ này</p>
                <p class="mt-1 text-2xl font-bold">{{ liveBetsCount }}</p>
            </div>
            <div class="rounded-xl border bg-card p-3">
                <p class="text-xs text-muted-foreground">Tổng tiền cược kỳ này</p>
                <p class="mt-1 font-mono text-2xl font-bold text-amber-800">
                    {{ formatVnd(liveTotalVnd) }}
                </p>
            </div>
        </div>

        <section v-if="liveOpenRound" class="rounded-2xl border-2 border-amber-200 bg-amber-50/60 p-4 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-amber-900/80">
                        Kỳ đang mở
                    </p>
                    <p class="text-2xl font-bold text-amber-950">
                        #{{ liveOpenRound.round_number }}
                        <span class="ml-1 text-sm font-normal text-amber-900/70">
                            {{ liveOpenRound.name }}
                        </span>
                    </p>
                </div>
                <div v-if="remainingLabel !== null"
                    class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-sm font-semibold"
                    :class="timerColorClass">
                    <Timer class="size-4" />
                    <span class="font-mono">{{ remainingLabel }}</span>
                    <span class="text-xs font-normal opacity-80">còn lại</span>
                </div>
                <div v-else
                    class="inline-flex items-center gap-1.5 rounded-full bg-stone-100 px-3 py-1 text-xs text-stone-600">
                    <Timer class="size-4" /> Không giới hạn
                </div>
            </div>

            <div v-if="liveOpenRound.preset" class="mt-3 flex items-center gap-2 text-sm">
                <span class="text-stone-700">Kết quả định sẵn:</span>
                <span class="inline-flex items-center rounded px-2 py-0.5 text-sm font-semibold shadow" :style="{
                    backgroundColor: liveOpenRound.preset.bg_color,
                    color: liveOpenRound.preset.text_color,
                }">
                    {{ liveOpenRound.preset.label }}
                </span>
            </div>

            <div class="mt-4">
                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-stone-700">
                    Tỉ lệ đặt cược theo mặt (realtime)
                </p>
                <ul class="space-y-2">
                    <li v-for="r in ratios" :key="r.id">
                        <div class="mb-1 flex items-center justify-between text-xs">
                            <span class="inline-flex items-center rounded px-2 py-0.5 font-semibold"
                                :style="{ backgroundColor: r.bg_color, color: r.text_color }">
                                {{ r.label }}
                            </span>
                            <span class="font-mono text-stone-700">
                                {{ r.betsCount }} lượt · {{ formatVnd(r.totalAmountVnd) }} ·
                                <strong>{{ r.percent.toFixed(1) }}%</strong>
                            </span>
                        </div>
                        <div class="h-2 w-full overflow-hidden rounded-full bg-stone-200">
                            <div class="h-full rounded-full transition-all duration-500" :style="{
                                width: r.percent + '%',
                                backgroundColor: r.bg_color,
                            }" />
                        </div>
                    </li>
                </ul>
                <p v-if="liveTotalVnd === 0" class="mt-2 text-xs text-stone-500">
                    Chưa có lượt đặt nào. Tỉ lệ sẽ cập nhật theo thời gian thực.
                </p>
            </div>

            <Button class="mt-4 w-full bg-stone-800 text-white hover:bg-stone-900" :disabled="endForm.processing"
                @click="submitEnd">
                {{ endForm.processing ? 'Đang gửi…' : 'Kết thúc kỳ này ngay' }}
            </Button>
        </section>

        <section v-else class="rounded-2xl border bg-card p-4">
            <h3 class="text-sm font-semibold text-stone-800">Bắt đầu kỳ mới</h3>
            <p class="mt-1 text-xs text-muted-foreground">
                Chọn kết quả định sẵn và thời lượng (phút). Khi hết giờ, kỳ sẽ tự động kết thúc.
            </p>

            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                <div>
                    <Label for="preset">Kết quả định sẵn</Label>
                    <Select v-model="presetOptionModel">
                        <SelectTrigger id="preset" class="mt-1 w-full">
                            <SelectValue placeholder="Chọn kết quả định sẵn" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="o in options" :key="o.id" :value="String(o.id)">
                                <span class="inline-flex items-center rounded px-2 py-0.5 text-xs font-semibold"
                                    :style="{ backgroundColor: o.bg_color, color: o.text_color }">
                                    {{ o.label }}
                                </span>
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <p v-if="startForm.errors.preset_option_id" class="mt-1 text-xs text-red-600">
                        {{ startForm.errors.preset_option_id }}
                    </p>
                </div>

                <div>
                    <Label for="kname">Tên kỳ (tuỳ chọn)</Label>
                    <Input id="kname" v-model="startForm.name" class="mt-1" placeholder="VD: Vòng 1 tối nay" />
                </div>
            </div>

            <div class="mt-3">
                <Label for="duration">Thời lượng (phút) — {{ minMinutes }}–{{ maxMinutes }}</Label>
                <Input id="duration" v-model.number="startForm.duration_minutes" type="number" :min="minMinutes"
                    :max="maxMinutes" class="mt-1 max-w-[180px]" />
                <div class="mt-2 flex flex-wrap gap-1.5 ">
                    <button v-for="m in QUICK_MINUTES" :key="m" type="button"
                        class="rounded-md border border-stone-200  px-2 py-0.5 text-xs " :class="startForm.duration_minutes === m
                            ? 'bg-amber-500 text-white'
                            : 'bg-stone-50 text-black'
                            " @click="setDurationMinutes(m)">
                        {{ m }} phút
                    </button>
                </div>
                <p v-if="startForm.errors.duration_seconds" class="mt-1 text-xs text-red-600">
                    {{ startForm.errors.duration_seconds }}
                </p>
            </div>

            <Button class="mt-4 w-full bg-amber-700 text-white hover:bg-amber-800 sm:w-auto"
                :disabled="!startForm.preset_option_id || startForm.processing" @click="submitStart">
                {{ startForm.processing ? 'Đang mở…' : 'Mở kỳ mới' }}
            </Button>
        </section>

        <section class="rounded-2xl border bg-card p-3">
            <h3 class="mb-2 text-sm font-semibold text-stone-800">Các kỳ đã kết thúc gần đây</h3>
            <ul v-if="recentRounds.length" class="max-h-72 space-y-1 overflow-y-auto pr-1 text-sm">
                <li v-for="h in recentRounds" :key="h.id"
                    class="flex items-center justify-between gap-2 rounded-lg bg-muted/40 px-2 py-1.5">
                    <span class="text-stone-700">Kỳ #{{ h.round_number }} <span class="text-xs text-stone-500">— {{
                        h.name }}</span></span>
                    <span class="rounded px-1.5 py-0.5 text-xs font-medium" :style="{
                        backgroundColor: h.preset.bg_color,
                        color: h.preset.text_color,
                    }">
                        {{ h.preset.label }}
                    </span>
                </li>
            </ul>
            <p v-else class="text-sm text-muted-foreground">Chưa có kỳ nào kết thúc.</p>
        </section>
    </div>
</template>
