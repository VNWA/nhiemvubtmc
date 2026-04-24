<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';
import { LogIn } from 'lucide-vue-next';

defineOptions({
    layout: {
        title: 'Đăng nhập SJC Sự Kiện',
        description: 'Nhập tên đăng nhập và mật khẩu để truy cập tài khoản của bạn.',
    },
});

defineProps<{
    status?: string;
    canResetPassword: boolean;
    canRegister: boolean;
}>();
</script>

<template>

    <Head title="Đăng nhập" />

    <div v-if="status"
        class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-center text-sm font-medium text-emerald-800">
        {{ status }}
    </div>

    <Form v-bind="store.form()" :reset-on-success="['password']" v-slot="{ errors, processing }"
        class="flex flex-col gap-5">
        <div class="grid gap-2">
            <Label for="username" class="login-label">Tên đăng nhập</Label>
            <Input id="username" type="text" name="username" required autofocus :tabindex="1" autocomplete="username"
                placeholder="Nhập tên đăng nhập" class="login-input" />
            <InputError :message="errors.username" />
        </div>

        <div class="grid gap-2">
            <div class="flex items-center justify-between">
                <Label for="password" class="login-label">Mật khẩu</Label>

            </div>
            <PasswordInput id="password" name="password" required :tabindex="2" autocomplete="current-password"
                placeholder="Nhập mật khẩu" class="login-input" />
            <InputError :message="errors.password" />
        </div>

        <div class="flex items-center justify-between">
            <Label for="remember" class="flex items-center gap-2 text-sm text-stone-700">
                <Checkbox id="remember" name="remember" :tabindex="3" />
                <span>Ghi nhớ đăng nhập</span>
            </Label>
            <TextLink v-if="canResetPassword" :href="request()" class="login-link text-xs text-stone-700" :tabindex="5">
                Quên mật khẩu?
            </TextLink>
        </div>

        <Button type="submit" class="login-submit" :tabindex="4" :disabled="processing" data-test="login-button">
            <Spinner v-if="processing" />
            <LogIn v-else class="size-4" />
            Đăng nhập
        </Button>

    </Form>
</template>

<style scoped>
.login-label {
    color: var(--primary-1, #0d4f9e);
    font-size: 0.8125rem;
    font-weight: 600;
}

/* Pierce scoped boundary so rules apply to <input> inside Input/PasswordInput children */
:deep(.login-input) {
    height: 2.75rem;
    border-width: 1.5px;
    border-color: var(--border, #dbe4ed);
    background-color: #ffffff !important;
    color: var(--text-body, #102a43);
    font-size: 0.9375rem;
    border-radius: 0.5rem;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    transition: border-color 150ms ease, box-shadow 150ms ease, background-color 150ms ease;
}

:deep(.login-input::placeholder) {
    color: #94a3b8;
}

:deep(.login-input:hover) {
    border-color: rgba(13, 79, 158, 0.35) !important;
}

:deep(.login-input:focus),
:deep(.login-input:focus-visible) {
    border-color: var(--primary-1, #0d4f9e) !important;
    box-shadow: 0 0 0 3px rgba(13, 79, 158, 0.16) !important;
    outline: none !important;
    background-color: #ffffff !important;
}

:deep(.login-input:-webkit-autofill),
:deep(.login-input:-webkit-autofill:hover),
:deep(.login-input:-webkit-autofill:focus) {
    -webkit-text-fill-color: var(--text-body, #102a43) !important;
    -webkit-box-shadow: 0 0 0 1000px #ffffff inset !important;
    caret-color: var(--text-body, #102a43);
    transition: background-color 5000s ease-in-out 0s;
}

/* Password toggle eye button */
:deep(.login-input) ~ button,
:deep(button[aria-label='Hide password']),
:deep(button[aria-label='Show password']) {
    color: var(--primary-1, #0d4f9e);
}

:deep(button[aria-label='Hide password']:hover),
:deep(button[aria-label='Show password']:hover) {
    color: var(--primary-1-hover, #0a3d7b);
}

.login-link {
    color: var(--primary-1, #0d4f9e);
    text-decoration: underline;
    text-underline-offset: 2px;
    transition: color 150ms ease;
}

.login-link:hover {
    color: var(--primary-1-hover, #0a3d7b);
}

/* Signature SJC submit — blue body, gold inner underline */
.login-submit {
    width: 100%;
    height: 2.875rem;
    margin-top: 0.25rem;
    background: var(--primary-1, #0d4f9e) !important;
    color: var(--text-inverse, #fdf8e8) !important;
    font-weight: 700 !important;
    font-size: 0.9375rem !important;
    letter-spacing: 0.02em;
    border-radius: 0.625rem !important;
    box-shadow:
        inset 0 -2px 0 rgba(232, 165, 0, 0.85),
        0 4px 12px rgba(13, 79, 158, 0.32) !important;
    transition: background-color 150ms ease, transform 100ms ease !important;
}

.login-submit:hover:not(:disabled) {
    background: var(--primary-1-hover, #0a3d7b) !important;
}

.login-submit:active:not(:disabled) {
    transform: translateY(1px);
}

.login-submit:disabled {
    opacity: 0.65;
}
</style>
