<script setup lang="ts">
import { Form, Head, setLayoutProps } from '@inertiajs/vue3';
import { computed, ref, watchEffect } from 'vue';
import InputError from '@/components/InputError.vue';
import CButton from '@/components/client/CButton.vue';
import CInput from '@/components/client/CInput.vue';
import COtpInput from '@/components/client/COtpInput.vue';
import { store } from '@/routes/two-factor/login';
import type { TwoFactorConfigContent } from '@/types';

const showRecoveryInput = ref<boolean>(false);

const authConfigContent = computed<TwoFactorConfigContent>(() => {
    if (showRecoveryInput.value) {
        return {
            title: 'Mã khôi phục',
            description: 'Nhập một trong các mã khôi phục để xác minh tài khoản.',
            buttonText: 'đăng nhập bằng mã xác thực',
        };
    }

    return {
        title: 'Mã xác thực',
        description: 'Nhập mã xác thực từ ứng dụng Authenticator của bạn.',
        buttonText: 'đăng nhập bằng mã khôi phục',
    };
});

watchEffect(() => {
    setLayoutProps({
        title: authConfigContent.value.title,
        description: authConfigContent.value.description,
    });
});

const toggleRecoveryMode = (clearErrors: () => void): void => {
    showRecoveryInput.value = !showRecoveryInput.value;
    clearErrors();
    code.value = '';
};

const code = ref<string>('');
</script>

<template>
    <Head title="Xác thực hai bước" />

    <div class="two-factor">
        <template v-if="!showRecoveryInput">
            <Form
                v-bind="store.form()"
                class="form"
                reset-on-error
                @error="code = ''"
                #default="{ errors, processing, clearErrors }"
            >
                <input type="hidden" name="code" :value="code" />
                <div class="otp-row">
                    <COtpInput v-model="code" :length="6" :disabled="processing" />
                </div>
                <InputError :message="errors.code" />

                <CButton type="submit" variant="primary" size="lg" block :disabled="processing">
                    Tiếp tục
                </CButton>

                <p class="alt">
                    Hoặc
                    <button type="button" class="alt-link" @click="() => toggleRecoveryMode(clearErrors)">
                        {{ authConfigContent.buttonText }}
                    </button>
                </p>
            </Form>
        </template>

        <template v-else>
            <Form
                v-bind="store.form()"
                class="form"
                reset-on-error
                #default="{ errors, processing, clearErrors }"
            >
                <CInput
                    name="recovery_code"
                    type="text"
                    placeholder="Nhập mã khôi phục"
                    :autofocus="showRecoveryInput"
                    required
                />
                <InputError :message="errors.recovery_code" />

                <CButton type="submit" variant="primary" size="lg" block :disabled="processing">
                    Tiếp tục
                </CButton>

                <p class="alt">
                    Hoặc
                    <button type="button" class="alt-link" @click="() => toggleRecoveryMode(clearErrors)">
                        {{ authConfigContent.buttonText }}
                    </button>
                </p>
            </Form>
        </template>
    </div>
</template>

<style scoped>
.two-factor {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.form {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.otp-row {
    display: flex;
    justify-content: center;
}

.alt {
    text-align: center;
    font-size: 0.8125rem;
    color: var(--text-muted, #5a6b7e);
}

.alt-link {
    margin-left: 0.25rem;
    background: transparent;
    border: 0;
    color: var(--primary-1, #0d4f9e);
    text-decoration: underline;
    text-underline-offset: 2px;
    cursor: pointer;
    font: inherit;
}

.alt-link:hover {
    color: var(--primary-1-hover, #0a3d7b);
}
</style>
