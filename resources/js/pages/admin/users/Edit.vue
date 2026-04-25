<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { Coins, Landmark } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const NO_BANK = '__none';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
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
import { Spinner } from '@/components/ui/spinner';
import { formatVnd } from '@/lib/vnd';

type ManagerOption = { id: number; name: string; username: string };

const props = defineProps<{
    user: {
        id: number;
        name: string;
        username: string;
        email: string;
        phone: string | null;
        balance_vnd: number;
        role: string;
        bank_name: string | null;
        bank_account_number: string | null;
        bank_account_name: string | null;
        created_by: number | null;
    };
    roleOptions: string[];
    bankOptions: string[];
    managerOptions: ManagerOption[];
    defaultManagerId: number;
    canAssignManager: boolean;
}>();

const selectedRole = ref<string>(props.user.role);
const selectedBank = ref<string>(props.user.bank_name && props.user.bank_name !== '' ? props.user.bank_name : NO_BANK);
const submittedBank = computed(() => (selectedBank.value === NO_BANK ? '' : selectedBank.value));
const selectedManager = ref<string>(
    props.user.created_by !== null && props.user.created_by > 0
        ? String(props.user.created_by)
        : props.defaultManagerId > 0
            ? String(props.defaultManagerId)
            : '',
);

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

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Users', href: UserController.index.url() },
            { title: 'Chỉnh sửa', href: '#' },
        ],
    },
});
</script>

<template>

    <Head title="Chỉnh sửa người dùng" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <Heading variant="small" title="Chỉnh sửa người dùng"
                description="Cập nhật thông tin tài khoản, vai trò, mật khẩu và thông tin ngân hàng." />

            <div class="flex flex-wrap items-center gap-2">
                <div class="inline-flex items-center gap-2 rounded-lg border border-border/60 bg-muted/40 px-3 py-1.5 text-xs text-muted-foreground">
                    <span>Số dư:</span>
                    <span class="font-mono font-semibold text-foreground">
                        {{ formatVnd(user.balance_vnd) }}
                    </span>
                </div>
                <Button size="sm" variant="outline" as-child>
                    <Link :href="UserController.deposit.url({ user: user.id })">
                        <Coins class="size-3.5" />
                        Nạp / trừ tiền
                    </Link>
                </Button>
            </div>
        </div>

        <Form v-bind="UserController.update.form({ user: user.id })" class="grid gap-5 lg:grid-cols-2"
            v-slot="{ errors, processing }">
            <section class="rounded-xl border border-border/60 bg-card p-5 shadow-sm">
                <h3 class="mb-4 text-sm font-semibold text-foreground">Thông tin tài khoản</h3>
                <div class="space-y-4">
                    <div class="grid gap-2">
                        <Label for="name">Họ tên</Label>
                        <Input id="name" name="name" :default-value="user.name" required autocomplete="name" />
                        <InputError :message="errors.name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="username">Tên đăng nhập</Label>
                        <Input id="username" name="username" :default-value="user.username" required
                            autocomplete="username" />
                        <InputError :message="errors.username" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="phone">Số điện thoại</Label>
                        <Input id="phone" name="phone" :default-value="user.phone ?? ''" autocomplete="tel" />
                        <InputError :message="errors.phone" />
                    </div>
                    <div class="grid gap-2">
                        <Label>Email</Label>
                        <Input :default-value="user.email" disabled readonly />
                        <p class="text-xs text-muted-foreground">Email được hệ thống tự sinh.</p>
                    </div>
                    <div class="grid gap-2">
                        <Label for="role">Vai trò</Label>
                        <Select v-model="selectedRole">
                            <SelectTrigger id="role" class="w-full">
                                <SelectValue placeholder="Chọn vai trò" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="r in roleOptions" :key="r" :value="r">
                                    {{ roleLabel(r) }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <input type="hidden" name="role" :value="selectedRole" />
                        <InputError :message="errors.role" />
                    </div>
                    <div v-if="canAssignManager && managerOptions.length" class="grid gap-2">
                        <Label for="created_by">Nhân viên quản lý</Label>
                        <Select v-model="selectedManager">
                            <SelectTrigger id="created_by" class="w-full">
                                <SelectValue placeholder="Chọn nhân viên quản lý" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="m in managerOptions"
                                    :key="m.id"
                                    :value="String(m.id)"
                                >
                                    {{ m.name }} (@{{ m.username }})
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <input type="hidden" name="created_by" :value="selectedManager" />
                        <InputError :message="errors.created_by" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="password">
                            Mật khẩu mới
                            <span class="text-muted-foreground">(không bắt buộc)</span>
                        </Label>
                        <PasswordInput id="password" name="password" autocomplete="new-password" />
                        <InputError :message="errors.password" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="password_confirmation">Nhập lại mật khẩu mới</Label>
                        <PasswordInput id="password_confirmation" name="password_confirmation"
                            autocomplete="new-password" />
                    </div>
                </div>
            </section>

            <section class="rounded-xl border border-border/60 bg-card p-5 shadow-sm">
                <div class="mb-4 flex items-center gap-2">
                    <Landmark class="size-4 text-amber-700" />
                    <h3 class="text-sm font-semibold text-foreground">Thông tin ngân hàng</h3>
                </div>
                <p class="mb-4 text-xs text-muted-foreground">
                    Quản trị viên / nhân viên cập nhật thông tin ngân hàng của khách hàng. Khách hàng chỉ xem được.
                </p>
                <div class="space-y-4">
                    <div class="grid gap-2">
                        <Label for="bank_name">Tên ngân hàng</Label>
                        <Select v-model="selectedBank">
                            <SelectTrigger id="bank_name" class="w-full">
                                <SelectValue placeholder="Chọn ngân hàng" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem :value="NO_BANK">— Chưa chọn —</SelectItem>
                                <SelectItem v-for="b in bankOptions" :key="b" :value="b">
                                    {{ b }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <input type="hidden" name="bank_name" :value="submittedBank" />
                        <InputError :message="errors.bank_name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="bank_account_number">Số tài khoản</Label>
                        <Input id="bank_account_number" name="bank_account_number" inputmode="numeric"
                            :default-value="user.bank_account_number ?? ''" autocomplete="off"
                            class="font-mono tracking-wider" />
                        <InputError :message="errors.bank_account_number" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="bank_account_name">Tên chủ tài khoản</Label>
                        <Input id="bank_account_name" name="bank_account_name"
                            :default-value="user.bank_account_name ?? ''" autocomplete="off"
                            class="uppercase" />
                        <InputError :message="errors.bank_account_name" />
                    </div>
                </div>
            </section>

            <div class="flex gap-2 lg:col-span-2">
                <Button type="submit" :disabled="processing">
                    <Spinner v-if="processing" />
                    Lưu thay đổi
                </Button>
                <Button variant="secondary" as-child>
                    <Link :href="UserController.index.url()">Hủy</Link>
                </Button>
            </div>
        </Form>
    </div>
</template>
