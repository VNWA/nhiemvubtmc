<script setup lang="ts">
import { Form, Head, Link, router } from '@inertiajs/vue3';
import StaffController from '@/actions/App/Http/Controllers/Admin/StaffController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { ref } from 'vue';

const props = defineProps<{
    staff: {
        id: number;
        name: string;
        username: string;
        email: string;
        phone: string | null;
        status: 'active' | 'locked';
        status_label: string;
        password: string | null;
        has_two_factor: boolean;
    };
}>();

const clearing2fa = ref(false);

function clearTwoFactor() {
    if (clearing2fa.value) {
        return;
    }
    if (
        !window.confirm(
            'Gỡ xác thực hai bước (2FA) cho nhân viên này?\n\n' +
                'Họ sẽ đăng nhập chỉ bằng mật khẩu cho tới khi tự bật lại 2FA trong tài khoản.',
        )
    ) {
        return;
    }
    clearing2fa.value = true;
    router.post(StaffController.clearTwoFactor.url({ staff: props.staff.id }), {}, {
        preserveScroll: true,
        onFinish: () => {
            clearing2fa.value = false;
        },
    });
}

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

        <div
            class="max-w-2xl space-y-3 rounded-xl border border-border/60 bg-card p-5 shadow-sm"
        >
            <h2 class="text-sm font-semibold text-foreground">Xác thực hai bước (2FA)</h2>
            <p v-if="!staff.has_two_factor" class="text-sm text-muted-foreground">
                Nhân viên chưa bật 2FA hoặc dữ liệu đã được gỡ. Không cần thao tác thêm ở đây.
            </p>
            <template v-else>
                <p class="text-sm text-muted-foreground">
                    Nếu nhân viên mất thiết bị, mã khôi phục hoặc không còn truy cập ứng dụng xác
                    thực, bạn có thể gỡ 2FA tại đây. Họ chỉ cần mật khẩu để đăng nhập cho tới
                    khi tự cấu hình lại 2FA.
                </p>
                <Button
                    type="button"
                    variant="destructive"
                    :disabled="clearing2fa"
                    @click="clearTwoFactor"
                >
                    <Spinner v-if="clearing2fa" class="size-4" />
                    Gỡ 2FA
                </Button>
            </template>
        </div>
    </div>
</template>
