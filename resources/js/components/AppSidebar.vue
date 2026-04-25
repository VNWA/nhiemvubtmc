<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { BookOpen, Banknote, CalendarHeart, FolderGit2, LayoutGrid, Palette, ScrollText, UserCog, Users } from 'lucide-vue-next';
import ActivityLogController from '@/actions/App/Http/Controllers/Admin/ActivityLogController';
import EventRoomController from '@/actions/App/Http/Controllers/Admin/EventRoomController';
import StaffController from '@/actions/App/Http/Controllers/Admin/StaffController';
import WithdrawalController from '@/actions/App/Http/Controllers/Admin/WithdrawalController';
import { computed } from 'vue';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes/admin';
import type { NavItem } from '@/types';
import appearance from '@/routes/admin/appearance';

const page = usePage();

const mainNavItems = computed((): NavItem[] => {
    const items: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboard(),
            icon: LayoutGrid,
        },
    ];

    const auth = page.props.auth;
    const isAdmin = auth?.isAdmin ?? false;
    const canManage = auth?.canManageUsers ?? false;

    if (isAdmin) {
        items.push({
            title: 'Giới thiệu',
            href: appearance.view('About'),
            icon: Palette,
        });
    }

    if (canManage) {
        items.push({
            title: 'Users',
            href: UserController.index.url(),
            icon: Users,
        });
    }

    if (isAdmin) {
        items.push({
            title: 'Nhân viên',
            href: StaffController.index.url(),
            icon: UserCog,
        });
        items.push({
            title: 'Sự kiện (phòng)',
            href: EventRoomController.index.url(),
            icon: CalendarHeart,
        });
    }

    if (canManage) {
        items.push({
            title: 'Yêu cầu rút tiền',
            href: WithdrawalController.index.url(),
            icon: Banknote,
        });
    }

    if (isAdmin) {
        items.push({
            title: 'Lịch sử thao tác',
            href: ActivityLogController.index.url(),
            icon: ScrollText,
        });
    }

    return items;
});

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/vue-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#vue',
        icon: BookOpen,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
