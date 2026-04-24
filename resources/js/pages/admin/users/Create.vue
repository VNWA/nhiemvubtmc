<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
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

const props = defineProps<{
    roleOptions: string[];
}>();

const selectedRole = ref<string>(props.roleOptions[0] ?? '');

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Users',
                href: UserController.index.url(),
            },
            {
                title: 'Add user',
                href: UserController.create.url(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Add user" />

    <div class="flex flex-col gap-6 p-4">
        <Heading
            variant="small"
            title="Thêm người dùng"
            description="Tạo tài khoản mới và gán vai trò."
        />

        <Form
            v-bind="UserController.store.form()"
            :reset-on-success="['password', 'password_confirmation']"
            class="max-w-2xl space-y-6 rounded-xl border border-border/60 bg-card p-5 shadow-sm"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="name">Họ tên</Label>
                <Input id="name" name="name" required autocomplete="name" />
                <InputError :message="errors.name" />
            </div>
            <div class="grid gap-2">
                <Label for="username">Tên đăng nhập</Label>
                <Input id="username" name="username" required autocomplete="username" />
                <InputError :message="errors.username" />
                <p class="text-xs text-muted-foreground">
                    Email hệ thống sẽ được sinh tự động.
                </p>
            </div>
            <div class="grid gap-2">
                <Label for="role">Vai trò</Label>
                <Select v-model="selectedRole">
                    <SelectTrigger id="role" class="w-full">
                        <SelectValue placeholder="Chọn vai trò" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="r in props.roleOptions" :key="r" :value="r">
                            {{ r }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <input type="hidden" name="role" :value="selectedRole" />
                <InputError :message="errors.role" />
            </div>
            <div class="grid gap-2">
                <Label for="password">Mật khẩu</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    autocomplete="new-password"
                />
                <InputError :message="errors.password" />
            </div>
            <div class="grid gap-2">
                <Label for="password_confirmation">Nhập lại mật khẩu</Label>
                <PasswordInput
                    id="password_confirmation"
                    name="password_confirmation"
                    required
                    autocomplete="new-password"
                />
            </div>
            <div class="flex gap-2">
                <Button type="submit" :disabled="processing">
                    <Spinner v-if="processing" />
                    Tạo người dùng
                </Button>
                <Button variant="secondary" as-child>
                    <Link :href="UserController.index.url()">Hủy</Link>
                </Button>
            </div>
        </Form>
    </div>
</template>
