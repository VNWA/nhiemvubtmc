<script setup lang="ts">
import { Form, Head, Link, router, usePage } from '@inertiajs/vue3';
import { CalendarHeart, Coins, Pencil, Search, Trash2, UserPlus, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
import UserEventController from '@/actions/App/Http/Controllers/Admin/UserEventController';
import Heading from '@/components/Heading.vue';
import Pagination, { type PaginationLink } from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
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
    created_at: string | null;
    creator: CreatorRef;
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
    users: Paginator;
    filters: { q: string; per_page: number };
}>();

const page = usePage();
const currentUserId = computed(() => page.props.auth.user?.id as number | undefined);

const search = ref<string>(props.filters.q ?? '');
let debounceTimer: ReturnType<typeof setTimeout> | null = null;

watch(search, (next) => {
    if (debounceTimer) {
        clearTimeout(debounceTimer);
    }
    debounceTimer = setTimeout(() => {
        const cleaned = next.trim();
        const params: Record<string, string | number> = {};
        if (cleaned !== '') params.q = cleaned;
        if (props.filters.per_page && props.filters.per_page !== 15) {
            params.per_page = props.filters.per_page;
        }
        router.get(UserController.index.url(), params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['users', 'filters'],
        });
    }, 300);
});

function clearSearch() {
    search.value = '';
}

function confirmDelete(name: string): boolean {
    return window.confirm(`Xóa người dùng "${name}"?\n\nHành động này không thể hoàn tác.`);
}

function roleLabel(role: string): string {
    switch (role) {
        case 'admin':
            return 'Admin';
        case 'staff':
            return 'Nhân viên';
        case 'user':
            return 'Khách hàng';
        default:
            return role;
    }
}

function roleClass(role: string): string {
    switch (role) {
        case 'admin':
            return 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300';
        case 'staff':
            return 'bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300';
        default:
            return 'bg-secondary text-secondary-foreground';
    }
}

function formatDate(iso: string | null): string {
    if (!iso) {
        return '—';
    }
    try {
        return new Date(iso).toLocaleString('vi-VN', { hour12: false });
    } catch {
        return iso;
    }
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
                description="Quản lý tài khoản, tìm kiếm theo tên / email / số điện thoại / tên đăng nhập." />
            <Button as-child>
                <Link :href="UserController.create.url()">
                    <UserPlus class="size-4" />
                    Thêm người dùng
                </Link>
            </Button>
        </div>

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="relative w-full sm:max-w-sm">
                <Search
                    class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input v-model="search" type="search" placeholder="Tìm theo tên, tên đăng nhập, email, số điện thoại…"
                    class="h-10 pl-9 pr-9" autocomplete="off" />
                <button v-if="search !== ''" type="button"
                    class="absolute right-2 top-1/2 -translate-y-1/2 rounded-md p-1 text-muted-foreground transition hover:bg-muted hover:text-foreground"
                    aria-label="Xóa tìm kiếm" @click="clearSearch">
                    <X class="size-4" />
                </button>
            </div>
            <div class="text-xs text-muted-foreground">
                Tổng
                <span class="font-semibold text-foreground">{{ users.total }}</span>
                người dùng
                <span v-if="filters.q">
                    khớp với "<span class="font-medium">{{ filters.q }}</span>"
                </span>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-border/60 bg-card shadow-sm dark:border-sidebar-border">
            <div class="overflow-x-auto">
                <table class="w-full min-w-5xl text-left text-sm">
                    <thead
                        class="border-b border-border/60 bg-muted/50 text-xs uppercase tracking-wide text-muted-foreground dark:border-sidebar-border">
                        <tr>
                            <th class="p-3 font-semibold">Người dùng</th>
                            <th class="p-3 font-semibold">Liên hệ</th>
                            <th class="p-3 text-end font-semibold">Số dư</th>
                            <th class="p-3 font-semibold">Vai trò</th>
                            <th class="p-3 font-semibold">Thời gian tạo</th>
                            <th class="p-3 font-semibold">Nhân viên quản lý</th>
                            <th class="p-3 text-center font-semibold">Sự kiện</th>
                            <th class="p-3 text-end font-semibold">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="users.data.length === 0">
                            <td colspan="8" class="px-3 py-10 text-center text-sm text-muted-foreground">
                                Không có người dùng phù hợp.
                            </td>
                        </tr>
                        <tr v-for="u in users.data" :key="u.id"
                            class="border-b border-border/40 transition hover:bg-muted/40 last:border-0 dark:border-sidebar-border/60">
                            <td class="p-3">
                                <div class="flex flex-col">
                                    <span class="font-medium text-foreground">{{ u.name }}</span>
                                    <span class="font-mono text-[11px] text-muted-foreground">
                                        @{{ u.username }}
                                    </span>
                                </div>
                            </td>
                            <td class="p-3">
                                <div class="flex flex-col gap-0.5">
                                    <span class="text-foreground/90">{{ u.email }}</span>
                                    <span class="font-mono text-[11px] text-muted-foreground">
                                        {{ u.phone || '—' }}
                                    </span>
                                </div>
                            </td>
                            <td class="p-3 text-end">
                                <div class="flex items-center justify-end gap-2">
                                    <span class="font-mono text-xs font-semibold">
                                        {{ formatVnd(u.balance_vnd) }}
                                    </span>
                                    <Link :href="UserController.deposit.url({ user: u.id })"
                                        class="inline-flex items-center gap-1 rounded-full border border-emerald-300 bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700 transition hover:bg-emerald-100 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300 dark:hover:bg-emerald-500/20"
                                        title="Nạp / trừ tiền và xem lịch sử">
                                        <Coins class="size-3" />
                                        Nạp tiền
                                    </Link>
                                </div>
                            </td>

                            <td class="p-3">
                                <span class="inline-flex rounded-md px-2 py-0.5 text-xs font-medium"
                                    :class="roleClass(u.role)">
                                    {{ roleLabel(u.role) }}
                                </span>
                            </td>
                            <td class="p-3 text-xs text-muted-foreground">
                                {{ formatDate(u.created_at) }}
                            </td>
                            <td class="p-3 text-xs">
                                <div v-if="u.creator" class="flex flex-col leading-tight">
                                    <span class="text-foreground">{{ u.creator.name }}</span>
                                    <span class="font-mono text-[11px] text-muted-foreground">
                                        @{{ u.creator.username }}
                                    </span>
                                </div>
                                <span v-else class="italic text-muted-foreground">Tự đăng ký</span>
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
                                    <Button size="sm" variant="outline" as-child>
                                        <Link :href="UserController.deposit.url({ user: u.id })">
                                            <Coins class="size-3.5" />
                                            Nạp tiền
                                        </Link>
                                    </Button>
                                    <Button variant="secondary" size="sm" as-child>
                                        <Link :href="UserController.edit.url({ user: u.id })">
                                            <Pencil class="size-3.5" />
                                            Sửa
                                        </Link>
                                    </Button>
                                    <Form v-if="u.id !== currentUserId"
                                        v-bind="UserController.destroy.form({ user: u.id })" @submit="(event: SubmitEvent) => {
                                            if (!confirmDelete(u.name)) {
                                                event.preventDefault();
                                            }
                                        }" #default="{ processing }">
                                        <Button type="submit" variant="destructive" size="sm" :disabled="processing">
                                            <Trash2 class="size-3.5" />
                                            Xóa
                                        </Button>
                                    </Form>
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
