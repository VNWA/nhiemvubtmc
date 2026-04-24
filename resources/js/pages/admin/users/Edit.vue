<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { Coins } from 'lucide-vue-next';
import { ref } from 'vue';
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

const props = defineProps<{
    user: {
        id: number;
        name: string;
        username: string;
        email: string;
        balance_vnd: number;
        role: string;
    };
    roleOptions: string[];
}>();

const selectedRole = ref<string>(props.user.role);

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Users',
                href: UserController.index.url(),
            },
            {
                title: 'Chỉnh sửa',
                href: '#',
            },
        ],
    },
});
</script>

<template>
    <Head title="Chỉnh sửa người dùng" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <Heading
                variant="small"
                title="Chỉnh sửa người dùng"
                description="Cập nhật thông tin, vai trò hoặc đặt lại mật khẩu cho người dùng."
            />

            <div class="flex flex-wrap items-center gap-2">
                <div
                    class="inline-flex items-center gap-2 rounded-lg border border-border/60 bg-muted/40 px-3 py-1.5 text-xs text-muted-foreground"
                >
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

        <section
            class="max-w-2xl rounded-xl border border-border/60 bg-card p-5 shadow-sm"
        >
            <Form
                v-bind="UserController.update.form({ user: user.id })"
                class="space-y-6"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-2">
                    <Label for="name">Họ tên</Label>
                    <Input
                        id="name"
                        name="name"
                        :default-value="user.name"
                        required
                        autocomplete="name"
                    />
                    <InputError :message="errors.name" />
                </div>
                <div class="grid gap-2">
                    <Label for="username">Tên đăng nhập</Label>
                    <Input
                        id="username"
                        name="username"
                        :default-value="user.username"
                        required
                        autocomplete="username"
                    />
                    <InputError :message="errors.username" />
                </div>
                <div class="grid gap-2">
                    <Label>Email</Label>
                    <Input :default-value="user.email" disabled readonly />
                    <p class="text-xs text-muted-foreground">
                        Email được hệ thống tự sinh và không thể thay đổi.
                    </p>
                </div>
                <div class="grid gap-2">
                    <Label for="role">Vai trò</Label>
                    <Select v-model="selectedRole">
                        <SelectTrigger id="role" class="w-full">
                            <SelectValue placeholder="Chọn vai trò" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="r in roleOptions" :key="r" :value="r">
                                {{ r }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <input type="hidden" name="role" :value="selectedRole" />
                    <InputError :message="errors.role" />
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
                    <PasswordInput
                        id="password_confirmation"
                        name="password_confirmation"
                        autocomplete="new-password"
                    />
                </div>
                <div class="flex gap-2">
                    <Button type="submit" :disabled="processing">
                        <Spinner v-if="processing" />
                        Lưu thay đổi
                    </Button>
                    <Button variant="secondary" as-child>
                        <Link :href="UserController.index.url()">Hủy</Link>
                    </Button>
                </div>
            </Form>
        </section>

        <p class="text-xs text-muted-foreground">
            Để nạp / trừ số dư và xem lịch sử giao dịch, hãy mở
            <Link
                :href="UserController.deposit.url({ user: user.id })"
                class="font-medium text-primary underline-offset-2 hover:underline"
            >
                trang nạp tiền riêng
            </Link>
            của người dùng này.
        </p>
    </div>
</template>
