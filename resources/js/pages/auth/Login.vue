<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import CButton from '@/components/client/CButton.vue';
import CCheckbox from '@/components/client/CCheckbox.vue';
import CInput from '@/components/client/CInput.vue';
import CLabel from '@/components/client/CLabel.vue';
import CPasswordInput from '@/components/client/CPasswordInput.vue';
import CSpinner from '@/components/client/CSpinner.vue';
import TextLink from '@/components/TextLink.vue';
import { request } from '@/routes/password';
import { store } from '@/routes/login';
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
        class="status-banner">
        {{ status }}
    </div>

    <Form v-bind="store.form()" :reset-on-success="['password']" v-slot="{ errors, processing }"
        class="login-form">
        <div class="field">
            <CLabel for="username" required>Tên đăng nhập</CLabel>
            <CInput id="username" type="text" name="username" required autofocus :tabindex="1" autocomplete="username"
                placeholder="Nhập tên đăng nhập" />
            <InputError :message="errors.username" />
        </div>

        <div class="field">
            <CLabel for="password" required>Mật khẩu</CLabel>
            <CPasswordInput id="password" name="password" required :tabindex="2" autocomplete="current-password"
                placeholder="Nhập mật khẩu" />
            <InputError :message="errors.password" />
        </div>

        <div class="row">
            <label for="remember" class="remember">
                <CCheckbox id="remember" name="remember" :tabindex="3" />
                <span>Ghi nhớ đăng nhập</span>
            </label>
            <TextLink v-if="canResetPassword" :href="request()" class="forgot-link" :tabindex="5">
                Quên mật khẩu?
            </TextLink>
        </div>

        <CButton type="submit" variant="primary" size="lg" block class="login-submit" :tabindex="4"
            :disabled="processing" data-test="login-button">
            <CSpinner v-if="processing" />
            <LogIn v-else class="size-4" />
            Đăng nhập
        </CButton>
    </Form>
</template>

<style scoped>
.status-banner {
    margin-bottom: 1rem;
    padding: 0.5rem 0.75rem;
    border-radius: 0.5rem;
    border: 1px solid #a7f3d0;
    background: #ecfdf5;
    color: #065f46;
    font-size: 0.875rem;
    font-weight: 500;
    text-align: center;
}

.login-form {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.field {
    display: grid;
    gap: 0.5rem;
}

.row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
}

.remember {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--text-body, #102a43);
    font-size: 0.875rem;
    cursor: pointer;
    user-select: none;
}

.forgot-link {
    color: var(--primary-1, #0d4f9e);
    text-decoration: underline;
    text-underline-offset: 2px;
    font-size: 0.75rem;
}

.forgot-link:hover {
    color: var(--primary-1-hover, #0a3d7b);
}

.login-submit {
    margin-top: 0.25rem;
    letter-spacing: 0.02em;
}
</style>
