<script setup lang="ts">
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
import UserEventController from '@/actions/App/Http/Controllers/Admin/UserEventController';
import CurrencyInput from '@/components/CurrencyInput.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import Pagination, { type PaginationLink } from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { formatVnd } from '@/lib/vnd';
import { Form, Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, CalendarHeart } from 'lucide-vue-next';
import { reactive } from 'vue';

type Bet = {
    id: number;
    amount_vnd: number;
    refund_vnd: number;
    commission_vnd: number;
    status: 'pending' | 'completed';
    status_label: string;
    net_vnd: number;
    created_at: string | null;
    option_labels: string[];
    round: { id: number; number: number; name: string | null } | null;
    room: { id: number; name: string; slug: string } | null;
};

type StatusOption = { value: 'pending' | 'completed'; label: string };

type Paginator = {
    data: Bet[];
    current_page: number;
    from: number | null;
    to: number | null;
    total: number;
    per_page: number;
    last_page: number;
    links: PaginationLink[];
};

const props = defineProps<{
    user: { id: number; name: string; username: string; balance_vnd: number };
    bets: Paginator;
    statusOptions: StatusOption[];
}>();

type DraftMap = Record<
    number,
    { status: 'pending' | 'completed'; refund_vnd: number; commission_vnd: number }
>;

const drafts = reactive<DraftMap>({});

for (const bet of props.bets.data) {
    drafts[bet.id] = {
        status: bet.status,
        refund_vnd: bet.refund_vnd,
        commission_vnd: bet.commission_vnd,
    };
}

function formatDate(iso: string | null): string {
    if (!iso) return '—';
    try {
        return new Date(iso).toLocaleString('vi-VN', { hour12: false });
    } catch {
        return iso;
    }
}

function netForDraft(bet: Bet): number {
    const draft = drafts[bet.id];
    if (!draft) return bet.net_vnd;
    return draft.refund_vnd + draft.commission_vnd - bet.amount_vnd;
}

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Users', href: UserController.index.url() },
            { title: 'Sự kiện đã tham gia', href: '#' },
        ],
    },
});
</script>

<template>

    <Head title="Sự kiện của người dùng" />

    <div class="flex flex-col gap-5 p-4">
        <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
            <Heading variant="small" :title="`Sự kiện của ${user.name}`"
                :description="`@${user.username} · Số dư: ${formatVnd(user.balance_vnd)}`" />
            <div class="flex items-center gap-2">
                <Button variant="secondary" as-child>
                    <Link :href="UserController.index.url()">
                        <ArrowLeft class="size-4" />
                        Quay lại
                    </Link>
                </Button>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-border/60 bg-card shadow-sm dark:border-sidebar-border">
            <div
                class="flex items-center justify-between gap-2 border-b border-border/60 bg-muted/30 px-4 py-3 text-sm dark:border-sidebar-border">
                <p class="flex items-center gap-2 text-muted-foreground">
                    <CalendarHeart class="size-4" />
                    Tổng <span class="font-semibold text-foreground">{{ bets.total }}</span> phiên tham gia
                </p>
            </div>

            <div v-if="bets.data.length === 0" class="px-4 py-10 text-center text-sm text-muted-foreground">
                Người dùng chưa tham gia phiên sự kiện nào.
            </div>

            <ul v-else class="divide-y divide-border/40 dark:divide-sidebar-border/60">
                <li v-for="bet in bets.data" :key="bet.id" class="grid gap-3 p-4 lg:grid-cols-[1fr,auto]">
                    <div class="space-y-1">
                        <p class="text-sm font-semibold text-foreground">
                            🎬 {{ bet.room?.name ?? '—' }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            📌 {{ bet.option_labels.length ? bet.option_labels.join(', ') : '—' }}


                        </p>
                        <p class="text-[11px] text-muted-foreground">
                            🕒 Phiên #{{ bet.id }} · {{ formatDate(bet.created_at) }}

                        </p>
                        <p class="text-xs">
                            <span class="text-muted-foreground">Kết quả hiện tại:</span>
                            <span class="ml-1 font-mono font-semibold"
                                :class="netForDraft(bet) >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'">
                                {{ netForDraft(bet) >= 0 ? '+' : '-' }}{{ formatVnd(Math.abs(netForDraft(bet))) }}
                            </span>
                        </p>
                    </div>

                    <Form v-bind="UserEventController.update.form({ user: user.id, bet: bet.id })"
                        class="flex flex-col gap-3 lg:items-end" v-slot="{ errors, processing }">
                        <div class="grid w-full grid-cols-2 gap-2 sm:grid-cols-4 lg:w-auto">
                            <div class="grid gap-1">
                                <Label class="text-[11px]">Phí tham gia</Label>
                                <div
                                    class="flex h-11 items-center justify-end rounded-[0.625rem] border border-input bg-muted/40 px-3 font-mono text-sm font-semibold tracking-wide">
                                    {{ formatVnd(bet.amount_vnd) }}
                                </div>
                            </div>
                            <div class="grid gap-1">
                                <Label :for="`refund-${bet.id}`" class="text-[11px]">Hoàn trả</Label>
                                <CurrencyInput :id="`refund-${bet.id}`" v-model="drafts[bet.id].refund_vnd"
                                    name="refund_vnd" :max="1_000_000_000" placeholder="0" />
                                <InputError :message="errors.refund_vnd" />
                            </div>
                            <div class="grid gap-1">
                                <Label :for="`commission-${bet.id}`" class="text-[11px]">Hoa hồng</Label>
                                <CurrencyInput :id="`commission-${bet.id}`" v-model="drafts[bet.id].commission_vnd"
                                    name="commission_vnd" :max="1_000_000_000" placeholder="0" />
                                <InputError :message="errors.commission_vnd" />
                            </div>
                            <div class="grid gap-1">
                                <Label :for="`status-${bet.id}`" class="text-[11px]">Trạng thái</Label>
                                <Select v-model="drafts[bet.id].status">
                                    <SelectTrigger :id="`status-${bet.id}`" class="status-trigger w-full">
                                        <SelectValue placeholder="Chọn trạng thái" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="opt in statusOptions" :key="opt.value" :value="opt.value">
                                            {{ opt.label }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <input type="hidden" name="status" :value="drafts[bet.id].status" />
                                <InputError :message="errors.status" />
                            </div>
                        </div>
                        <Button type="submit" size="sm" :disabled="processing">
                            <Spinner v-if="processing" />
                            Cập nhật
                        </Button>
                    </Form>
                </li>
            </ul>

            <Pagination v-if="bets.last_page > 1" :meta="bets" item-label="phiên" />
        </div>
    </div>
</template>

<style scoped>
.status-trigger {
    height: 2.75rem;
}
</style>
