<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import StaffController from '@/actions/App/Http/Controllers/Admin/StaffController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

defineProps<{
    staff: {
        id: number;
        name: string;
        username: string;
        email: string;
        phone: string | null;
        status: 'active' | 'locked';
        status_label: string;
        password: string | null;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Nhân viên', href: StaffController.index.url() },
            { title: 'Chỉnh sửa', href: '#' },
        ],
    },
});
</script>

<template>

    <Head title="Sửa nhân viên" />

    <div class="flex flex-col gap-6 p-4">
        <Heading variant="small" :title="`Chỉnh sửa: ${staff.name}`"
            :description="`Trạng thái: ${staff.status_label}`" />

        <Form v-bind="StaffController.update.form({ staff: staff.id })"
            class="max-w-2xl space-y-6 rounded-xl border border-border/60 bg-card p-5 shadow-sm"
            v-slot="{ errors, processing }">
            <div class="grid gap-2">
                <Label for="name">Họ tên</Label>
                <Input id="name" name="name" :default-value="staff.name" required autocomplete="name" />
                <InputError :message="errors.name" />
            </div>
            <div class="grid gap-2">
                <Label for="username">Tên đăng nhập</Label>
                <Input id="username" name="username" :default-value="staff.username" required autocomplete="username" />
                <InputError :message="errors.username" />
            </div>
            <div class="grid gap-2">
                <Label for="phone">Số điện thoại</Label>
                <Input id="phone" name="phone" :default-value="staff.phone ?? ''" autocomplete="tel" />
                <InputError :message="errors.phone" />
            </div>
            <div class="grid gap-2">
                <Label>Email</Label>
                <Input :default-value="staff.email" disabled readonly />
            </div>
            <div class="grid gap-2">
                <Label for="password">
                    Mật khẩu mới <span class="text-muted-foreground">(không bắt buộc)</span>
                </Label>
                <PasswordInput id="password" name="password" autocomplete="new-password" />
                <InputError :message="errors.password" />
            </div>
            <div class="grid gap-2">
                <Label for="password_confirmation">Nhập lại mật khẩu mới</Label>
                <PasswordInput id="password_confirmation" name="password_confirmation"
                    autocomplete="new-password" />
            </div>
            <div class="flex gap-2">
                <Button type="submit" :disabled="processing">
                    <Spinner v-if="processing" />
                    Lưu thay đổi
                </Button>
                <Button variant="secondary" as-child>
                    <Link :href="StaffController.index.url()">Hủy</Link>
                </Button>
            </div>
        </Form>
    </div>
</template>
