<script setup lang="ts">
import { Form, Head, Link, router } from '@inertiajs/vue3';
import {
    Eye,
    EyeOff,
    Lock,
    LockOpen,
    Pencil,
    Search,
    Trash2,
    UserPlus,
    Users,
    X,
} from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';
import StaffController from '@/actions/App/Http/Controllers/Admin/StaffController';
import AdminListReloadButton from '@/components/admin/AdminListReloadButton.vue';
import Heading from '@/components/Heading.vue';
import Pagination from '@/components/Pagination.vue';
import type {PaginationLink} from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type Row = {
    id: number;
    name: string;
    username: string;
    email: string;
    phone: string | null;
    status: 'active' | 'locked';
    status_label: string;
    managed_users_count: number;
    event_bets_count: number;
    last_login_at: string | null;
    last_login_ip: string | null;
    created_at: string | null;
    password: string | null;
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

const props = defineProps<{
    staff: Paginator;
    filters: { q: string; ip: string; status: string; per_page: number };
    statusOptions: { value: string; label: string }[];
}>();

const search = ref<string>(props.filters.q ?? '');
const ipFilter = ref<string>(props.filters.ip ?? '');
const statusFilter = ref<string>(props.filters.status ?? '');

let debounceTimer: ReturnType<typeof setTimeout> | null = null;

function pushFilters() {
    const params: Record<string, string | number> = {};
    const cleaned = search.value.trim();

    if (cleaned) {
params.q = cleaned;
}

    const ipClean = ipFilter.value.trim();

    if (ipClean) {
params.ip = ipClean;
}

    if (statusFilter.value && statusFilter.value !== '__all') {
params.status = statusFilter.value;
}

    router.get(StaffController.index.url(), params, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['staff', 'filters'],
    });
}

watch(search, () => {
    if (debounceTimer) {
clearTimeout(debounceTimer);
}

    debounceTimer = setTimeout(pushFilters, 300);
});

let ipDebounce: ReturnType<typeof setTimeout> | null = null;
watch(ipFilter, () => {
    if (ipDebounce) {
clearTimeout(ipDebounce);
}

    ipDebounce = setTimeout(pushFilters, 300);
});

watch(statusFilter, pushFilters);

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
        delete passwordCache[row.id];

        return;
    }

    if (passwordCache[row.id] !== undefined) {
        passwordVisible[row.id] = true;

        return;
    }

    passwordLoading[row.id] = true;

    try {
        const res = await fetch(StaffController.password.url({ staff: row.id }), {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        passwordCache[row.id] = res.ok
            ? ((await res.json()) as { password: string | null }).password ?? null
            : null;
        passwordVisible[row.id] = true;
    } finally {
        passwordLoading[row.id] = false;
    }
}

function lockPrompt(row: Row): string | null {
    if (row.status === 'locked') {
return null;
}

    return window.prompt(`Lý do khóa nhân viên "${row.name}" (có thể bỏ trống):`, '') ?? '__cancel__';
}

function confirmDelete(name: string) {
    return window.confirm(`Xóa nhân viên "${name}"?\nUser do nhân viên tạo sẽ trở thành tự đăng ký.`);
}

const rowProcessing = reactive<Record<number, boolean>>({});

/**
 * Dùng router.delete thay vì Form gắn submit, vì Inertia Form vẫn có thể gửi khi bấm Hủy ở hộp thoại confirm.
 */
function deleteStaff(row: Row) {
    if (rowProcessing[row.id]) {
        return;
    }

    if (!confirmDelete(row.name)) {
        return;
    }

    rowProcessing[row.id] = true;
    router.delete(StaffController.destroy.url({ staff: row.id }), {
        preserveScroll: true,
        onFinish: () => {
            rowProcessing[row.id] = false;
        },
    });
}

const hasFilters = computed(() => !!(search.value || ipFilter.value || statusFilter.value));

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Nhân viên', href: StaffController.index.url() }],
    },
});
</script>

<template>

    <Head title="Quản lý nhân viên" />

    <div class="flex flex-col gap-5 p-4">
        <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
            <Heading variant="small" title="Nhân viên" description="Quản lý các tài khoản nhân viên trong hệ thống." />
            <div class="flex flex-wrap items-center justify-end gap-2">
                <AdminListReloadButton :only="['staff', 'filters', 'statusOptions']" />
                <Button as-child>
                    <Link :href="StaffController.create.url()">
                        <UserPlus class="size-4" />
                        Thêm nhân viên
                    </Link>
                </Button>
            </div>
        </div>

        <div class="rounded-xl border border-border/60 bg-card p-3 shadow-sm dark:border-sidebar-border">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <div class="relative">
                    <Search
                        class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="search" type="search" placeholder="Tìm tên / đăng nhập / email / SĐT…"
                        class="h-10 pl-9" autocomplete="off" />
                </div>
                <div class="relative">
                    <Search
                        class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="ipFilter" type="search" inputmode="search" placeholder="Lọc theo IP đăng nhập…"
                        class="h-10 pl-9 font-mono text-sm" autocomplete="off" />
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
            </div>
            <div class="mt-3 flex items-center justify-between gap-3 text-xs text-muted-foreground">
                <span>
                    Tổng <span class="font-semibold text-foreground">{{ staff.total }}</span> nhân viên
                </span>
                <button v-if="hasFilters" type="button"
                    class="inline-flex items-center gap-1 rounded-md border border-border/60 px-2 py-1 font-medium text-foreground/80 transition hover:bg-muted dark:border-sidebar-border"
                    @click="() => { search = ''; ipFilter = ''; statusFilter = ''; }">
                    <X class="size-3.5" /> Xóa bộ lọc
                </button>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-border/60 bg-card shadow-sm dark:border-sidebar-border">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1000px] text-left text-sm">
                    <thead
                        class="border-b border-border/60 bg-muted/50 text-xs uppercase tracking-wide text-muted-foreground dark:border-sidebar-border">
                        <tr>
                            <th class="p-3">Nhân viên</th>
                            <th class="p-3">Mật khẩu</th>
                            <th class="p-3">Liên hệ</th>
                            <th class="p-3 text-center">User quản lý</th>
                            <th class="p-3">Trạng thái</th>
                            <th class="p-3">Đăng nhập cuối</th>
                            <th class="p-3">Tạo lúc</th>
                            <th class="p-3 text-end">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="staff.data.length === 0">
                            <td colspan="8" class="px-3 py-10 text-center text-sm text-muted-foreground">
                                Chưa có nhân viên nào.
                            </td>
                        </tr>
                        <tr v-for="u in staff.data" :key="u.id"
                            class="border-b border-border/40 transition hover:bg-muted/40 last:border-0 dark:border-sidebar-border/60">
                            <td class="p-3">
                                <div class="flex flex-col">
                                    <span class="font-medium text-foreground">{{ u.name }}</span>
                                    <span class="font-mono text-[11px] text-muted-foreground">@{{ u.username }}</span>
                                </div>
                            </td>
                            <td class="p-3">
                                <div class="flex items-center gap-1.5">
                                    <span class="font-mono text-xs">
                                        {{ passwordVisible[u.id] && passwordCache[u.id] !== null
                                            ? (passwordCache[u.id] ?? '—')
                                            : '••••••••' }}
                                    </span>
                                    <button type="button"
                                        class="inline-flex size-7 items-center justify-center rounded-md border border-border/60 text-muted-foreground transition hover:bg-muted hover:text-foreground dark:border-sidebar-border"
                                        :title="passwordVisible[u.id] ? 'Ẩn' : 'Hiện'"
                                        :disabled="passwordLoading[u.id]" @click="togglePassword(u)">
                                        <Eye v-if="!passwordVisible[u.id]" class="size-3.5" />
                                        <EyeOff v-else class="size-3.5" />
                                    </button>
                                </div>
                            </td>
                            <td class="p-3">
                                <div class="flex flex-col gap-0.5 text-xs">
                                    <span class="text-foreground/90">{{ u.email }}</span>
                                    <span class="font-mono text-[11px] text-muted-foreground">{{ u.phone || '—' }}</span>
                                </div>
                            </td>
                            <td class="p-3 text-center">
                                <span
                                    class="inline-flex items-center gap-1 rounded-full bg-sky-100 px-2 py-0.5 text-[11px] font-semibold text-sky-700 dark:bg-sky-500/15 dark:text-sky-300">
                                    <Users class="size-3" />
                                    {{ u.managed_users_count }}
                                </span>
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
                            <td class="p-3 text-end">
                                <div class="flex flex-wrap items-center justify-end gap-2">
                                    <Button variant="secondary" size="sm" as-child>
                                        <Link :href="StaffController.edit.url({ staff: u.id })">
                                            <Pencil class="size-3.5" /> Sửa
                                        </Link>
                                    </Button>
                                    <Form v-bind="StaffController.toggleLock.form({ staff: u.id })"
                                        @submit="(event: SubmitEvent) => {
                                            if (u.status === 'active') {
                                                const reason = lockPrompt(u);
                                                if (reason === '__cancel__') {
                                                    event.preventDefault();
                                                    return;
                                                }
                                                const fd = (event.target as HTMLFormElement);
                                                const input = fd.querySelector('input[name=reason]') as HTMLInputElement | null;
                                                if (input && reason) input.value = reason;
                                            }
                                        }" #default="{ processing }">
                                        <input type="hidden" name="reason" value="" />
                                        <Button type="submit" size="sm"
                                            :variant="u.status === 'locked' ? 'outline' : 'destructive'"
                                            :disabled="processing">
                                            <LockOpen v-if="u.status === 'locked'" class="size-3.5" />
                                            <Lock v-else class="size-3.5" />
                                            {{ u.status === 'locked' ? 'Mở' : 'Khóa' }}
                                        </Button>
                                    </Form>
                                    <Button
                                        type="button"
                                        variant="destructive"
                                        size="sm"
                                        :disabled="rowProcessing[u.id]"
                                        @click="deleteStaff(u)"
                                    >
                                        <Trash2 class="size-3.5" />
                                        Xóa
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Pagination :meta="staff" :only="['staff', 'filters']" item-label="nhân viên" />
        </div>
    </div>
</template>
