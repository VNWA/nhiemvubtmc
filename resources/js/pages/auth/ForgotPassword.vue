<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import CButton from '@/components/client/CButton.vue';
import CInput from '@/components/client/CInput.vue';
import CLabel from '@/components/client/CLabel.vue';
import CSpinner from '@/components/client/CSpinner.vue';
import { login } from '@/routes';
import { email } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Quên mật khẩu',
        description: 'Nhập email để nhận liên kết đặt lại mật khẩu.',
    },
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head title="Quên mật khẩu" />

    <div v-if="status" class="status-banner">
        {{ status }}
    </div>

    <Form v-bind="email.form()" v-slot="{ errors, processing }" class="forgot-form">
        <div class="field">
            <CLabel for="email" required>Email</CLabel>
            <CInput
                id="email"
                type="email"
                name="email"
                autocomplete="off"
                autofocus
                placeholder="email@example.com"
            />
            <InputError :message="errors.email" />
        </div>

        <CButton
            type="submit"
            variant="primary"
            size="lg"
            block
            :disabled="processing"
            data-test="email-password-reset-link-button"
        >
            <CSpinner v-if="processing" />
            Gửi liên kết đặt lại
        </CButton>

        <p class="back-link">
            <span>Quay lại</span>
            <TextLink :href="login()">đăng nhập</TextLink>
        </p>
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

.forgot-form {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.field {
    display: grid;
    gap: 0.5rem;
}

.back-link {
    text-align: center;
    font-size: 0.8125rem;
    color: var(--text-muted, #5a6b7e);
}

.back-link a {
    margin-left: 0.25rem;
    color: var(--primary-1, #0d4f9e);
    text-decoration: underline;
    text-underline-offset: 2px;
}
</style>
