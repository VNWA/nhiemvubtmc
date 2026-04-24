<script setup lang="ts">
import AccountController from '@/actions/App/Http/Controllers/Client/AccountController';
import AccountPageHeader from '@/components/AccountPageHeader.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { Save } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    profile: {
        id: number;
        name: string;
        username: string;
        email: string;
        phone: string | null;
        created_at: string | null;
        role: string;
    };
}>();

const page = usePage();
const flash = computed(() => (page.props as { flash?: { success?: string } }).flash ?? {});

const form = useForm({
    phone: props.profile.phone ?? '',
});

function submit() {
    form.patch(AccountController.updateProfile.url(), { preserveScroll: true });
}

function onPhoneInput(e: Event) {
    const el = e.target as HTMLInputElement;
    const cleaned = el.value.replace(/[^0-9+]/g, '');
    if (el.value !== cleaned) {
        el.value = cleaned;
    }
    form.phone = cleaned;
}
</script>

<template>
    <Head title="Thông tin tài khoản" />

    <div class="space-y-3 px-3 pb-24 pt-3">
        <AccountPageHeader title="Thông tin tài khoản" description="Xem thông tin tài khoản và cập nhật số điện thoại" />

        <div v-if="flash.success" class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
            {{ flash.success }}
        </div>

        <form class="space-y-3 rounded-2xl border border-stone-200 bg-white p-4 shadow-sm" @submit.prevent="submit">
            <div>
                <label class="account-label">Họ tên</label>
                <input :value="profile.name" type="text" class="account-input is-readonly" readonly />
                <p class="mt-1 text-[11px] text-stone-500">Liên hệ quản trị viên nếu cần đổi họ tên.</p>
            </div>

            <div>
                <label class="account-label">Tên đăng nhập</label>
                <input :value="profile.username" type="text" class="account-input is-readonly" readonly />
                <p class="mt-1 text-[11px] text-stone-500">Tên đăng nhập không thể thay đổi.</p>
            </div>

            <div>
                <label class="account-label">Email hệ thống</label>
                <input :value="profile.email" type="text" class="account-input is-readonly" readonly />
                <p class="mt-1 text-[11px] text-stone-500">Email do hệ thống cấp, không thể thay đổi.</p>
            </div>

            <div>
                <label for="phone" class="account-label">Số điện thoại</label>
                <input
                    id="phone"
                    :value="form.phone"
                    type="tel"
                    inputmode="tel"
                    autocomplete="tel"
                    maxlength="20"
                    placeholder="VD: 0901234567"
                    class="account-input"
                    :class="{ 'is-invalid': form.errors.phone }"
                    @input="onPhoneInput"
                />
                <p v-if="form.errors.phone" class="account-error">{{ form.errors.phone }}</p>
                <p v-else class="mt-1 text-[11px] text-stone-500">
                    Dùng cho xác minh giao dịch và liên lạc khi cần.
                </p>
            </div>

            <button
                type="submit"
                class="submit-btn"
                :disabled="form.processing"
            >
                <Save class="size-4" />
                {{ form.processing ? 'Đang lưu…' : 'Lưu thay đổi' }}
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

.account-input::placeholder {
    color: rgb(214 211 209);
}

.account-input:hover:not(:disabled):not(.is-readonly) {
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

.account-input.is-readonly {
    background: rgb(250 250 249);
    color: rgb(120 113 108);
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
