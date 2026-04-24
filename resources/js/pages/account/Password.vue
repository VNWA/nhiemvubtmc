<script setup lang="ts">
import AccountController from '@/actions/App/Http/Controllers/Client/AccountController';
import AccountPageHeader from '@/components/AccountPageHeader.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { Eye, EyeOff, ShieldCheck } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const page = usePage();
const flash = computed(() => (page.props as { flash?: { success?: string } }).flash ?? {});

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const showCurrent = ref(false);
const showNew = ref(false);

function submit() {
    form.put(AccountController.updatePassword.url(), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}
</script>

<template>
    <Head title="Mật khẩu đăng nhập" />

    <div class="space-y-3 px-3 pb-24 pt-3">
        <AccountPageHeader title="Mật khẩu đăng nhập" description="Thay đổi mật khẩu để bảo vệ tài khoản" />

        <div v-if="flash.success" class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
            {{ flash.success }}
        </div>

        <form class="space-y-3 rounded-2xl border border-stone-200 bg-white p-4 shadow-sm" @submit.prevent="submit">
            <div>
                <label for="current-password" class="account-label">Mật khẩu hiện tại</label>
                <div class="relative">
                    <input
                        id="current-password"
                        v-model="form.current_password"
                        :type="showCurrent ? 'text' : 'password'"
                        autocomplete="current-password"
                        class="account-input pr-11"
                        :class="{ 'is-invalid': form.errors.current_password }"
                    />
                    <button type="button" class="toggle-btn" @click="showCurrent = !showCurrent">
                        <Eye v-if="!showCurrent" class="size-4" />
                        <EyeOff v-else class="size-4" />
                    </button>
                </div>
                <p v-if="form.errors.current_password" class="account-error">{{ form.errors.current_password }}</p>
            </div>

            <div>
                <label for="new-password" class="account-label">Mật khẩu mới</label>
                <div class="relative">
                    <input
                        id="new-password"
                        v-model="form.password"
                        :type="showNew ? 'text' : 'password'"
                        autocomplete="new-password"
                        class="account-input pr-11"
                        :class="{ 'is-invalid': form.errors.password }"
                    />
                    <button type="button" class="toggle-btn" @click="showNew = !showNew">
                        <Eye v-if="!showNew" class="size-4" />
                        <EyeOff v-else class="size-4" />
                    </button>
                </div>
                <p v-if="form.errors.password" class="account-error">{{ form.errors.password }}</p>
            </div>

            <div>
                <label for="confirm-password" class="account-label">Nhập lại mật khẩu mới</label>
                <input
                    id="confirm-password"
                    v-model="form.password_confirmation"
                    :type="showNew ? 'text' : 'password'"
                    autocomplete="new-password"
                    class="account-input"
                />
            </div>

            <p class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-[11px] text-amber-800">
                Lưu ý: sau khi đổi mật khẩu, hãy ghi nhớ mật khẩu mới để không bị mất quyền truy cập.
            </p>

            <button type="submit" class="submit-btn" :disabled="form.processing">
                <ShieldCheck class="size-4" />
                {{ form.processing ? 'Đang lưu…' : 'Đổi mật khẩu' }}
            </button>
        </form>
    </div>
</template>

<style scoped>
.account-label {
    display: block;
    margin-bottom: 0.25rem;
    font-size: 0.75rem;
    font-weight: 600;
    color: rgb(68 64 60);
}

.account-input {
    display: block;
    width: 100%;
    height: 2.75rem;
    padding: 0 0.875rem;
    border: 1.5px solid rgb(231 229 228);
    border-radius: 0.625rem;
    background: white;
    color: rgb(28 25 23);
    font-size: 0.9375rem;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
    transition: border-color 150ms ease, box-shadow 150ms ease;
    outline: none;
}

.account-input:hover:not(:disabled) {
    border-color: rgb(214 211 209);
}

.account-input:focus {
    border-color: rgb(217 119 6);
    box-shadow: 0 0 0 3px rgb(254 243 199);
}

.account-input.is-invalid {
    border-color: rgb(220 38 38);
    box-shadow: 0 0 0 3px rgb(254 226 226);
}

.toggle-btn {
    position: absolute;
    inset-block: 0;
    right: 0.25rem;
    display: inline-flex;
    width: 2.25rem;
    align-items: center;
    justify-content: center;
    color: rgb(120 113 108);
    background: transparent;
    border: none;
    cursor: pointer;
}

.toggle-btn:hover {
    color: rgb(68 64 60);
}

.account-error {
    margin-top: 0.25rem;
    font-size: 0.6875rem;
    color: rgb(220 38 38);
    font-weight: 500;
}

.submit-btn {
    display: inline-flex;
    width: 100%;
    height: 2.75rem;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    border-radius: 0.75rem;
    background: rgb(217 119 6);
    color: white;
    font-size: 0.875rem;
    font-weight: 600;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
    transition: background-color 150ms ease;
    cursor: pointer;
}

.submit-btn:hover:not(:disabled) {
    background: rgb(180 83 9);
}

.submit-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}
</style>
