<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { Coins } from 'lucide-vue-next';
import { computed } from 'vue';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { formatVnd } from '@/lib/vnd';

type Row = {
    id: number;
    name: string;
    username: string;
    email: string;
    balance_vnd: number;
    role: string;
};

const props = defineProps<{
    users: Row[];
}>();

const page = usePage();
const currentUserId = computed(() => page.props.auth.user?.id as number | undefined);

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
    <Head title="Users" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
            <Heading
                variant="small"
                title="Users"
                description="Manage user accounts and roles"
            />
            <Button as-child>
                <Link :href="UserController.create.url()">Add user</Link>
            </Button>
        </div>

        <div
            class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <table class="w-full min-w-xl text-left text-sm">
                <thead
                    class="border-b border-sidebar-border/70 bg-muted/40 dark:border-sidebar-border"
                >
                    <tr>
                        <th class="p-3 font-medium">Name</th>
                        <th class="p-3 font-medium">Username</th>
                        <th class="p-3 font-medium">Email</th>
                        <th class="p-3 text-end font-medium">Balance</th>
                        <th class="p-3 font-medium">Role</th>
                        <th class="p-3 text-end font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="u in props.users"
                        :key="u.id"
                        class="border-b border-sidebar-border/50 last:border-0"
                    >
                        <td class="p-3">{{ u.name }}</td>
                        <td class="p-3 font-mono text-xs">{{ u.username }}</td>
                        <td class="p-3 text-muted-foreground">{{ u.email }}</td>
                        <td class="p-3 text-end">
                            <div class="flex items-center justify-end gap-2">
                                <span class="font-mono text-xs">{{ formatVnd(u.balance_vnd) }}</span>
                                <Link
                                    :href="UserController.deposit.url({ user: u.id })"
                                    class="inline-flex items-center gap-1 rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700 transition hover:bg-emerald-100"
                                    title="Nạp / trừ tiền và xem lịch sử"
                                >
                                    <Coins class="size-3" />
                                    Nạp tiền
                                </Link>
                            </div>
                        </td>
                        <td class="p-3">
                            <span
                                class="inline-flex rounded-md bg-secondary px-2 py-0.5 text-xs capitalize text-secondary-foreground"
                            >
                                {{ u.role }}
                            </span>
                        </td>
                        <td class="p-3 text-end">
                            <div class="flex flex-wrap items-center justify-end gap-2">
                                <Button size="sm" variant="outline" as-child>
                                    <Link
                                        :href="UserController.deposit.url({ user: u.id })"
                                    >
                                        <Coins class="size-3.5" />
                                        Nạp tiền
                                    </Link>
                                </Button>
                                <Button variant="secondary" size="sm" as-child>
                                    <Link
                                        :href="UserController.edit.url({ user: u.id })"
                                    >
                                        Edit
                                    </Link>
                                </Button>
                                <Form
                                    v-if="u.id !== currentUserId"
                                    v-bind="UserController.destroy.form({ user: u.id })"
                                    #default="{ processing }"
                                >
                                    <Button
                                        type="submit"
                                        variant="destructive"
                                        size="sm"
                                        :disabled="processing"
                                    >
                                        Delete
                                    </Button>
                                </Form>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
