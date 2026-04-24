<script lang="ts" setup>
import AccountController from '@/actions/App/Http/Controllers/Client/AccountController';
import SukienEventRoomController from '@/actions/App/Http/Controllers/Sukien/SukienEventRoomController';
import { formatVnd } from '@/lib/vnd';
import { home } from '@/routes';
import { Link, usePage } from '@inertiajs/vue3';
import { CalendarHeart, HomeIcon, User2, Wallet } from 'lucide-vue-next';
import { computed } from 'vue';

const page = usePage();

const balanceVnd = computed<number>(() => {
    const auth = page.props.auth as { balanceVnd?: number } | undefined;
    return auth?.balanceVnd ?? 0;
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
    <div class="min-h-screen w-full bg-stone-100 text-stone-900">
        <main class="client-main mx-auto flex min-h-screen w-full max-w-5xl flex-1 flex-col bg-white">
            <header class="sticky top-0 z-20 bg-[#9b0101] px-3 py-2 text-[#f0f48d] shadow-md">
                <div class="flex items-center justify-between gap-2">
                    <Link :href="home().url" class="flex items-center gap-2 active:opacity-80">
                        <h1 class="text-lg font-bold uppercase tracking-wide">Bảo tín minh châu</h1>
                    </Link>
                    <Link
                        :href="AccountController.show.url()"
                        class="flex items-center gap-1.5 rounded-full bg-white/15 px-2.5 py-1 text-xs font-semibold backdrop-blur transition active:scale-95"
                    >
                        <Wallet class="size-3.5" />
                        <span class="font-mono">{{ formatVnd(balanceVnd) }}</span>
                    </Link>
                </div>
                <p class="mt-0.5 text-[11px] text-[#f0f48d]/80">Xin chào, {{ userName }}</p>
            </header>

            <div class="flex-1">
                <slot />
            </div>
        </main>

        <nav class="client-bottom-nav">
            <ul class="grid grid-cols-3">
                <li v-for="item in navItems" :key="item.label">
                    <Link
                        :href="item.href"
                        class="nav-item"
                        :class="{ 'is-active': item.matches(currentUrl) }"
                    >
                        <component :is="item.icon" class="size-5" />
                        <span>{{ item.label }}</span>
                    </Link>
                </li>
            </ul>
        </nav>
    </div>
</template>

<style scoped>
.client-main {
    color: rgb(28 25 23);
    font-family: 'Times New Roman', serif;
    padding-bottom: calc(64px + env(safe-area-inset-bottom));
}

.client-bottom-nav {
    position: fixed;
    inset-inline: 0;
    bottom: 0;
    z-index: 30;
    background: white;
    border-top: 1px solid rgb(231 229 228);
    box-shadow: 0 -2px 12px rgba(0, 0, 0, 0.04);
    padding-bottom: env(safe-area-inset-bottom);
}

.nav-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.125rem;
    padding: 0.5rem 0.25rem;
    color: rgb(120 113 108);
    font-size: 0.6875rem;
    font-weight: 500;
    text-transform: capitalize;
    transition: color 150ms ease, background-color 150ms ease;
}

.nav-item:active {
    background: rgb(254 243 199);
}

.nav-item.is-active {
    color: rgb(180 83 9);
    font-weight: 700;
}

.nav-item.is-active svg {
    color: rgb(217 119 6);
}
</style>
