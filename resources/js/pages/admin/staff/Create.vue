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

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Nhân viên', href: StaffController.index.url() },
            { title: 'Thêm nhân viên', href: StaffController.create.url() },
        ],
    },
});
</script>

<template>

    <Head title="Thêm nhân viên" />

    <div class="flex flex-col gap-6 p-4">
        <Heading variant="small" title="Thêm nhân viên" description="Tạo tài khoản nhân viên mới." />

        <Form v-bind="StaffController.store.form()"
            :reset-on-success="['password', 'password_confirmation']"
            class="max-w-2xl space-y-6 rounded-xl border border-border/60 bg-card p-5 shadow-sm"
            v-slot="{ errors, processing }">
            <div class="grid gap-2">
                <Label for="name">Họ tên</Label>
                <Input id="name" name="name" required autocomplete="name" />
                <InputError :message="errors.name" />
            </div>
            <div class="grid gap-2">
                <Label for="username">Tên đăng nhập</Label>
                <Input id="username" name="username" required autocomplete="username" />
                <InputError :message="errors.username" />
                <p class="text-xs text-muted-foreground">Email hệ thống sẽ được sinh tự động.</p>
            </div>
            <div class="grid gap-2">
                <Label for="phone">Số điện thoại</Label>
                <Input id="phone" name="phone" autocomplete="tel" />
                <InputError :message="errors.phone" />
            </div>
            <div class="grid gap-2">
                <Label for="password">Mật khẩu</Label>
                <PasswordInput id="password" name="password" required autocomplete="new-password" />
                <InputError :message="errors.password" />
            </div>
            <div class="grid gap-2">
                <Label for="password_confirmation">Nhập lại mật khẩu</Label>
                <PasswordInput id="password_confirmation" name="password_confirmation" required
                    autocomplete="new-password" />
            </div>
            <div class="flex gap-2">
                <Button type="submit" :disabled="processing">
                    <Spinner v-if="processing" />
                    Tạo nhân viên
                </Button>
                <Button variant="secondary" as-child>
                    <Link :href="StaffController.index.url()">Hủy</Link>
                </Button>
            </div>
        </Form>
    </div>
</template>
