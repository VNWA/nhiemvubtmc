<script lang="ts" setup>
import AccountController from '@/actions/App/Http/Controllers/Client/AccountController';
import SukienEventRoomController from '@/actions/App/Http/Controllers/Sukien/SukienEventRoomController';
import { formatVnd } from '@/lib/vnd';
import { home } from '@/routes';
import { Link, usePage } from '@inertiajs/vue3';
import { CalendarHeart, HomeIcon, User2, Wallet } from 'lucide-vue-next';
import { computed } from 'vue';
import '../../css/client.css';
const page = usePage();

const availableVnd = computed<number>(() => {
    const auth = page.props.auth as { availableVnd?: number; balanceVnd?: number } | undefined;
    if (auth?.availableVnd != null) {
        return auth.availableVnd;
    }

    return auth?.balanceVnd ?? 0;
});

const frozenVnd = computed<number>(() => {
    const auth = page.props.auth as { frozenVnd?: number } | undefined;
    return auth?.frozenVnd ?? 0;
});

const userName = computed<string>(() => {
    const auth = page.props.auth as { user?: { name?: string } } | undefined;
    return auth?.user?.name ?? 'Khách';
});

type NavItem = { icon: typeof HomeIcon; label: string; href: string; matches: (url: string) => boolean };

const navItems: NavItem[] = [
    {
        icon: HomeIcon,
        label: 'Trang chủ',
        href: home().url,
        matches: (u) => u === '/' || u.startsWith('/?'),
    },
    {
        icon: CalendarHeart,
        label: 'Sự kiện',
        href: SukienEventRoomController.index.url(),
        matches: (u) => u.startsWith('/sukien'),
    },
    {
        icon: User2,
        label: 'Cá nhân',
        href: AccountController.show.url(),
        matches: (u) => u.startsWith('/tai-khoan'),
    },
];

const currentUrl = computed<string>(() => {
    const url = page.url;
    if (typeof url !== 'string') return '/';
    return url;
});
</script>

<template>
    <div class="client-shell w-full">
        <main class="client-main mx-auto flex min-h-screen w-full max-w-5xl flex-1 flex-col">
            <header class="client-header">
                <div class="flex items-center justify-between gap-2">
                    <Link :href="home().url" class="client-header-brand">
                        <img src="/images/logo-blue.png" alt="SJC" class="client-header-logo" />
                        <span class="client-header-title">
                            <span class="title">{{ page.props.name }}</span>
                            <span class="subtitle">Vàng · Bạc · Đá quý</span>
                        </span>
                    </Link>
                    <Link
                        :href="AccountController.show.url()"
                        class="client-balance-pill"
                        :title="
                            frozenVnd > 1
                                ? 'Khả dụng ' +
                                  formatVnd(availableVnd) +
                                  ' · Tổng ' +
                                  formatVnd(
                                      (page.props.auth as { balanceVnd?: number } | undefined)?.balanceVnd ?? 0,
                                  ) +
                                  ' · Đóng băng ' +
                                  formatVnd(frozenVnd)
                                : 'Số dư khả dụng'
                        "
                        aria-label="Tài khoản"
                    >
                        <Wallet class="size-3.5" />
                        <span class="amount">{{ formatVnd(availableVnd) }}</span>
                    </Link>
                </div>
                <p class="client-greeting">Xin chào, <strong>{{ userName }}</strong></p>
            </header>

            <div class="flex-1">
                <slot />
            </div>
        </main>

        <nav class="client-bottom-nav">
            <ul class="grid grid-cols-3">
                <li v-for="item in navItems" :key="item.label">
                    <Link :href="item.href" class="nav-item" :class="{ 'is-active': item.matches(currentUrl) }">
                        <component :is="item.icon" class="size-5" />
                        <span>{{ item.label }}</span>
                    </Link>
                </li>
            </ul>
        </nav>
    </div>
</template>
