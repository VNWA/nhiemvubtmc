<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    CalendarHeart,
    Coins,
    Eye,
    EyeOff,
    Lock,
    LockOpen,
    Pencil,
    Search,
    Trash2,
    UserPlus,
    X,
} from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
import UserEventController from '@/actions/App/Http/Controllers/Admin/UserEventController';
import Heading from '@/components/Heading.vue';
import Pagination, { type PaginationLink } from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatVnd } from '@/lib/vnd';

type CreatorRef = {
    id: number;
    name: string;
    username: string;
} | null;

type Row = {
    id: number;
    name: string;
    username: string;
    email: string;
    phone: string | null;
    balance_vnd: number;
    role: string;
    event_count: number;
    status: 'active' | 'locked';
    status_label: string;
    last_login_at: string | null;
    last_login_ip: string | null;
    created_at: string | null;
    creator: CreatorRef;
    can_view_password: boolean;
    can_lock: boolean;
};

type Paginator = {
    data: Row[];
    current_page: number;
    from: number | null;
    to: number | null;
    total: number;
    per_page: number;
    last_page: number;
    links: PaginationLink[];
};

type ManagerOption = { id: number; name: string; username: string };

const props = defineProps<{
    users: Paginator;
    filters: {
        q: string;
        ip: string;
        status: string;
        manager_id: number | null;
        per_page: number;
    };
    statusOptions: { value: string; label: string }[];
    managerOptions: ManagerOption[];
}>();

const page = usePage();
const currentUserId = computed(() => page.props.auth.user?.id as number | undefined);

const search = ref<string>(props.filters.q ?? '');
const ipFilter = ref<string>(props.filters.ip ?? '');
const statusFilter = ref<string>(props.filters.status ?? '');
const managerFilter = ref<string>(
    props.filters.manager_id !== null ? String(props.filters.manager_id) : '',
);

let debounceTimer: ReturnType<typeof setTimeout> | null = null;

function pushFilters() {
    const params: Record<string, string | number> = {};
    const cleaned = search.value.trim();
    if (cleaned !== '') params.q = cleaned;
    const ipClean = ipFilter.value.trim();
    if (ipClean !== '') params.ip = ipClean;
    if (statusFilter.value && statusFilter.value !== '__all') params.status = statusFilter.value;
    if (managerFilter.value && managerFilter.value !== '__all') params.manager_id = managerFilter.value;
    if (props.filters.per_page && props.filters.per_page !== 15) params.per_page = props.filters.per_page;

    router.get(UserController.index.url(), params, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['users', 'filters'],
    });
}

watch(search, () => {
    if (debounceTimer) clearTimeout(debounceTimer);
    debounceTimer = setTimeout(pushFilters, 300);
});

let ipDebounce: ReturnType<typeof setTimeout> | null = null;
watch(ipFilter, () => {
    if (ipDebounce) clearTimeout(ipDebounce);
    ipDebounce = setTimeout(pushFilters, 300);
});

watch([statusFilter, managerFilter], () => pushFilters());

function clearSearch() {
    search.value = '';
}

function clearIpFilter() {
    ipFilter.value = '';
}

function resetFilters() {
    statusFilter.value = '';
    managerFilter.value = '';
    search.value = '';
    ipFilter.value = '';
}

const hasFilters = computed(
    () => !!(search.value || ipFilter.value || statusFilter.value || managerFilter.value),
);

function statusClass(status: string): string {
    return status === 'locked'
        ? 'bg-stone-200 text-stone-700 dark:bg-stone-700/40 dark:text-stone-200'
        : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300';
}

const passwordCache = reactive<Record<number, string | null>>({});
const passwordLoading = reactive<Record<number, boolean>>({});
const passwordVisible = reactive<Record<number, boolean>>({});

async function togglePassword(row: Row) {
    if (passwordVisible[row.id]) {
        passwordVisible[row.id] = false;
        return;
    }
    if (passwordCache[row.id] !== undefined) {
        passwordVisible[row.id] = true;
        return;
    }
    passwordLoading[row.id] = true;
    try {
        const res = await fetch(UserController.password.url({ user: row.id }), {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        if (!res.ok) {
            passwordCache[row.id] = null;
        } else {
            const json = (await res.json()) as { password: string | null };
            passwordCache[row.id] = json.password ?? null;
        }
        passwordVisible[row.id] = true;
    } finally {
        passwordLoading[row.id] = false;
    }
}

const rowProcessing = reactive<Record<number, boolean>>({});

function deleteUser(row: Row) {
    if (rowProcessing[row.id]) return;
    if (!window.confirm(`Xóa người dùng "${row.name}"?\n\nHành động này không thể hoàn tác.`)) {
        return;
    }
    rowProcessing[row.id] = true;
    router.delete(UserController.destroy.url({ user: row.id }), {
        preserveScroll: true,
        onFinish: () => {
            rowProcessing[row.id] = false;
        },
    });
}

function toggleLock(row: Row) {
    if (rowProcessing[row.id]) return;

    let reason = '';
    if (row.status === 'active') {
        const input = window.prompt(
            `Lý do khóa tài khoản "${row.name}" (có thể bỏ trống):`,
            '',
        );
        if (input === null) return;
        reason = input;
    } else if (!window.confirm(`Mở khóa tài khoản "${row.name}"?`)) {
        return;
    }

    rowProcessing[row.id] = true;
    router.post(
        UserController.toggleLock.url({ user: row.id }),
        { reason },
        {
            preserveScroll: true,
            onFinish: () => {
                rowProcessing[row.id] = false;
            },
        },
    );
}

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Users',
                href: UserController.index.url(),
            },
        ],
    },
});
</script>

<template>

    <Head title="Quản lý người dùng" />

    <div class="flex flex-col gap-5 p-4">
        <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
            <Heading variant="small" title="Người dùng"
                description="Quản lý tài khoản khách hàng, lọc theo nhân viên quản lý / trạng thái." />
            <Button as-child>
                <Link :href="UserController.create.url()">
                    <UserPlus class="size-4" />
                    Thêm người dùng
                </Link>
            </Button>
        </div>

        <div class="rounded-xl border border-border/60 bg-card p-3 shadow-sm dark:border-sidebar-border">
            <div class="grid gap-3 md:grid-cols-2 2xl:grid-cols-4">
                <div class="relative">
                    <Search
                        class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="search" type="search" placeholder="Tìm tên, đăng nhập, email, SĐT…"
                        class="h-10 pl-9 pr-9" autocomplete="off" />
                    <button v-if="search !== ''" type="button"
                        class="absolute right-2 top-1/2 -translate-y-1/2 rounded-md p-1 text-muted-foreground transition hover:bg-muted hover:text-foreground"
                        aria-label="Xóa tìm kiếm" @click="clearSearch">
                        <X class="size-4" />
                    </button>
                </div>

                <div class="relative">
                    <Search
                        class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="ipFilter" type="search" inputmode="search" placeholder="Lọc theo IP đăng nhập…"
                        class="h-10 pl-9 pr-9 font-mono text-sm" autocomplete="off" />
                    <button v-if="ipFilter !== ''" type="button"
                        class="absolute right-2 top-1/2 -translate-y-1/2 rounded-md p-1 text-muted-foreground transition hover:bg-muted hover:text-foreground"
                        aria-label="Xóa lọc IP" @click="clearIpFilter">
                        <X class="size-4" />
                    </button>
                </div>

                <Select v-model="statusFilter">
                    <SelectTrigger class="h-10 w-full">
                        <SelectValue placeholder="Trạng thái" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="__all">Tất cả trạng thái</SelectItem>
                        <SelectItem v-for="s in statusOptions" :key="s.value" :value="s.value">
                            {{ s.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>

                <Select v-if="managerOptions.length" v-model="managerFilter">
                    <SelectTrigger class="h-10 w-full">
                        <SelectValue placeholder="Nhân viên quản lý" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="__all">Tất cả nhân viên</SelectItem>
                        <SelectItem v-for="m in managerOptions" :key="m.id" :value="String(m.id)">
                            {{ m.name }} (@{{ m.username }})
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div class="mt-3 flex items-center justify-between gap-3 text-xs text-muted-foreground">
                <span>
                    Tổng
                    <span class="font-semibold text-foreground">{{ users.total }}</span> người dùng
                </span>
                <button v-if="hasFilters" type="button"
                    class="inline-flex items-center gap-1 rounded-md border border-border/60 bg-background px-2 py-1 font-medium text-foreground/80 transition hover:bg-muted dark:border-sidebar-border"
                    @click="resetFilters">
                    <X class="size-3.5" /> Xóa bộ lọc
                </button>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-border/60 bg-card shadow-sm dark:border-sidebar-border">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1100px] text-left text-sm">
                    <thead
                        class="border-b border-border/60 bg-muted/50 text-xs uppercase tracking-wide text-muted-foreground dark:border-sidebar-border">
                        <tr>
                            <th class="p-3 font-semibold">Người dùng</th>
                            <th class="p-3 font-semibold">Mật khẩu</th>
                            <th class="p-3 font-semibold">Liên hệ</th>
                            <th class="p-3 font-semibold">Trạng thái</th>
                            <th class="p-3 font-semibold">Đăng nhập cuối</th>
                            <th class="p-3 font-semibold">Tạo lúc</th>
                            <th class="p-3 font-semibold">NV quản lý</th>
                            <th class="p-3 text-end font-semibold">Số dư</th>
                            <th class="p-3 text-center font-semibold">Sự kiện</th>
                            <th class="p-3 text-end font-semibold">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="users.data.length === 0">
                            <td colspan="10" class="px-3 py-10 text-center text-sm text-muted-foreground">
                                Không có người dùng phù hợp.
                            </td>
                        </tr>
                        <tr v-for="u in users.data" :key="u.id"
                            class="border-b border-border/40 transition hover:bg-muted/40 last:border-0 dark:border-sidebar-border/60">
                            <td class="p-3">
                                <div class="flex flex-col">
                                    <span class="font-medium text-foreground">{{ u.name }}</span>
                                    <span class="font-mono text-[11px] text-muted-foreground">@{{ u.username }}</span>
                                </div>
                            </td>
                            <td class="p-3">
                                <div v-if="u.can_view_password" class="flex items-center gap-1.5">
                                    <span class="font-mono text-xs">
                                        {{ passwordVisible[u.id] && passwordCache[u.id] !== null
                                            ? (passwordCache[u.id] ?? '—')
                                            : '••••••••' }}
                                    </span>
                                    <button type="button"
                                        class="inline-flex size-7 items-center justify-center rounded-md border border-border/60 text-muted-foreground transition hover:bg-muted hover:text-foreground dark:border-sidebar-border"
                                        :title="passwordVisible[u.id] ? 'Ẩn' : 'Hiện mật khẩu'"
                                        :disabled="passwordLoading[u.id]" @click="togglePassword(u)">
                                        <Eye v-if="!passwordVisible[u.id]" class="size-3.5" />
                                        <EyeOff v-else class="size-3.5" />
                                    </button>
                                </div>
                                <span v-else class="text-xs text-muted-foreground">—</span>
                            </td>
                            <td class="p-3">
                                <div class="flex flex-col gap-0.5">
                                    <span class="text-foreground/90">{{ u.email }}</span>
                                    <span class="font-mono text-[11px] text-muted-foreground">{{ u.phone || '—'
                                    }}</span>
                                </div>
                            </td>

                            <td class="p-3">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-medium"
                                    :class="statusClass(u.status)">
                                    {{ u.status_label }}
                                </span>
                            </td>
                            <td class="p-3 text-xs text-muted-foreground">
                                <div v-if="u.last_login_at" class="flex flex-col leading-tight">
                                    <span>{{ u.last_login_at }}</span>
                                    <span class="font-mono text-[10px]">{{ u.last_login_ip || '—' }}</span>
                                </div>
                                <span v-else>—</span>
                            </td>
                            <td class="p-3 text-xs text-muted-foreground">{{ u.created_at ?? '—' }}</td>
                            <td class="p-3 text-xs">
                                <div v-if="u.creator" class="flex flex-col leading-tight">
                                    <span class="text-foreground">{{ u.creator.name }}</span>
                                    <span class="font-mono text-[11px] text-muted-foreground">@{{ u.creator.username
                                    }}</span>
                                </div>
                                <span v-else class="italic text-muted-foreground">Tự đăng ký</span>
                            </td>
                            <td class="p-3 text-end">
                                <div class="flex items-center justify-end gap-2">
                                    <span class="font-mono text-xs font-semibold">{{ formatVnd(u.balance_vnd) }}</span>
                                    <Link :href="UserController.deposit.url({ user: u.id })"
                                        class="inline-flex items-center gap-1 rounded-full border border-emerald-300 bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700 transition hover:bg-emerald-100 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300 dark:hover:bg-emerald-500/20"
                                        title="Nạp / trừ tiền">
                                        <Coins class="size-3" />
                                        Nạp
                                    </Link>
                                </div>
                            </td>
                            <td class="p-3 text-center">
                                <Link :href="UserEventController.index.url({ user: u.id })"
                                    class="inline-flex items-center gap-1 rounded-full border border-blue-300 bg-blue-50 px-2 py-0.5 text-[11px] font-semibold text-blue-700 transition hover:bg-blue-100 dark:border-blue-500/30 dark:bg-blue-500/10 dark:text-blue-300 dark:hover:bg-blue-500/20"
                                    :title="`Xem ${u.event_count} sự kiện đã tham gia`">
                                    <CalendarHeart class="size-3" />
                                    {{ u.event_count }}
                                </Link>
                            </td>
                            <td class="p-3 text-end">
                                <div class="flex flex-wrap items-center justify-end gap-2">
                                    <Button variant="secondary" size="sm" as-child>
                                        <Link :href="UserController.edit.url({ user: u.id })">
                                            <Pencil class="size-3.5" />
                                            Sửa
                                        </Link>
                                    </Button>
                                    <Button v-if="u.can_lock" type="button" size="sm"
                                        :variant="u.status === 'locked' ? 'outline' : 'destructive'"
                                        :disabled="rowProcessing[u.id]"
                                        @click="toggleLock(u)">
                                        <LockOpen v-if="u.status === 'locked'" class="size-3.5" />
                                        <Lock v-else class="size-3.5" />
                                        {{ u.status === 'locked' ? 'Mở khóa' : 'Khóa' }}
                                    </Button>
                                    <Button v-if="u.id !== currentUserId" type="button"
                                        variant="destructive" size="sm"
                                        :disabled="rowProcessing[u.id]"
                                        @click="deleteUser(u)">
                                        <Trash2 class="size-3.5" />
                                        Xóa
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Pagination :meta="users" :only="['users', 'filters']" item-label="người dùng" />
        </div>
    </div>
</template>
