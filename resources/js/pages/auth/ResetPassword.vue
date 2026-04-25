<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import CButton from '@/components/client/CButton.vue';
import CInput from '@/components/client/CInput.vue';
import CLabel from '@/components/client/CLabel.vue';
import CPasswordInput from '@/components/client/CPasswordInput.vue';
import CSpinner from '@/components/client/CSpinner.vue';
import { update } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Đặt lại mật khẩu',
        description: 'Nhập mật khẩu mới của bạn bên dưới.',
    },
});

const props = defineProps<{
    token: string;
    email: string;
}>();

const inputEmail = ref(props.email);
</script>

<template>
    <Head title="Đặt lại mật khẩu" />

    <Form
        v-bind="update.form()"
        :transform="(data) => ({ ...data, token, email })"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
        class="reset-form"
    >
        <div class="field">
            <CLabel for="email">Email</CLabel>
            <CInput
                id="email"
                type="email"
                name="email"
                autocomplete="email"
                v-model="inputEmail"
                readonly
            />
            <InputError :message="errors.email" />
        </div>

        <div class="field">
            <CLabel for="password" required>Mật khẩu mới</CLabel>
            <CPasswordInput
                id="password"
                name="password"
                autocomplete="new-password"
                autofocus
                placeholder="Mật khẩu"
            />
            <InputError :message="errors.password" />
        </div>

        <div class="field">
            <CLabel for="password_confirmation" required>Xác nhận mật khẩu</CLabel>
            <CPasswordInput
                id="password_confirmation"
                name="password_confirmation"
                autocomplete="new-password"
                placeholder="Nhập lại mật khẩu"
            />
            <InputError :message="errors.password_confirmation" />
        </div>

        <CButton
            type="submit"
            variant="primary"
            size="lg"
            block
            :disabled="processing"
            data-test="reset-password-button"
        >
            <CSpinner v-if="processing" />
            Đặt lại mật khẩu
        </CButton>
    </Form>
</template>

<style scoped>
.reset-form {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.field {
    display: grid;
    gap: 0.5rem;
}
</style>
