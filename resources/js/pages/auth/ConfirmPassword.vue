<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import CButton from '@/components/client/CButton.vue';
import CLabel from '@/components/client/CLabel.vue';
import CPasswordInput from '@/components/client/CPasswordInput.vue';
import CSpinner from '@/components/client/CSpinner.vue';
import { store } from '@/routes/password/confirm';

defineOptions({
    layout: {
        title: 'Xác nhận mật khẩu',
        description:
            'Đây là khu vực bảo mật. Vui lòng nhập lại mật khẩu để tiếp tục.',
    },
});
</script>

<template>
    <Head title="Xác nhận mật khẩu" />

    <Form
        v-bind="store.form()"
        reset-on-success
        v-slot="{ errors, processing }"
        class="confirm-form"
    >
        <div class="field">
            <CLabel for="password" required>Mật khẩu</CLabel>
            <CPasswordInput
                id="password"
                name="password"
                required
                autocomplete="current-password"
                autofocus
            />
            <InputError :message="errors.password" />
        </div>

        <CButton
            type="submit"
            variant="primary"
            size="lg"
            block
            :disabled="processing"
            data-test="confirm-password-button"
        >
            <CSpinner v-if="processing" />
            Xác nhận mật khẩu
        </CButton>
    </Form>
</template>

<style scoped>
.confirm-form {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.field {
    display: grid;
    gap: 0.5rem;
}
</style>
