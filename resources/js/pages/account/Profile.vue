<script setup lang="ts">
import AccountPageHeader from '@/components/AccountPageHeader.vue';
import { Head } from '@inertiajs/vue3';
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

const phoneDisplay = computed(() => props.profile.phone?.trim() || '—');
</script>

<template>
    <Head title="Thông tin tài khoản" />

    <div class="space-y-3 px-3 pb-24 pt-3">
        <AccountPageHeader
            title="Thông tin tài khoản"
            description="Xem thông tin tài khoản. Số điện thoại do nhân viên quản lý hoặc quản trị viên cập nhật khi cần."
        />

        <div class="space-y-3 rounded-2xl border border-stone-200 bg-white p-4 shadow-sm">
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
                <label class="account-label">Số điện thoại</label>
                <input :value="phoneDisplay" type="text" class="account-input is-readonly" readonly />
                <p class="mt-1 text-[11px] text-stone-500">
                    Số này do nhân viên quản lý hoặc quản trị viên cập nhật; bạn không chỉnh trực tiếp trên đây.
                </p>
            </div>
        </div>
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
    outline: none;
}

.account-input.is-readonly {
    background: rgb(250 250 249);
    color: rgb(120 113 108);
}
</style>
