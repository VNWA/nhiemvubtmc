<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import TextLink from '@/components/TextLink.vue';
import CButton from '@/components/client/CButton.vue';
import CSpinner from '@/components/client/CSpinner.vue';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

defineOptions({
    layout: {
        title: 'Xác minh email',
        description:
            'Vui lòng xác minh địa chỉ email bằng cách nhấn vào liên kết chúng tôi vừa gửi.',
    },
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head title="Xác minh email" />

    <div v-if="status === 'verification-link-sent'" class="status-banner">
        Liên kết xác minh mới đã được gửi đến email đăng ký.
    </div>

    <Form v-bind="send.form()" class="verify-form" v-slot="{ processing }">
        <CButton type="submit" variant="outline" size="lg" block :disabled="processing">
            <CSpinner v-if="processing" />
            Gửi lại email xác minh
        </CButton>

        <TextLink :href="logout()" as="button" class="logout-link">
            Đăng xuất
        </TextLink>
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

.verify-form {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
    text-align: center;
}

.logout-link {
    margin: 0 auto;
    display: block;
    font-size: 0.875rem;
    color: var(--primary-1, #0d4f9e);
    text-decoration: underline;
    text-underline-offset: 2px;
    background: transparent;
    border: 0;
    cursor: pointer;
}

.logout-link:hover {
    color: var(--primary-1-hover, #0a3d7b);
}
</style>
