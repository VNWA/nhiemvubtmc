<script setup lang="ts">
import AccountPageHeader from '@/components/AccountPageHeader.vue';
import { Head } from '@inertiajs/vue3';
import { Info, Landmark } from 'lucide-vue-next';

defineProps<{
    bank: {
        bank_name: string | null;
        bank_account_number: string | null;
        bank_account_name: string | null;
    };
}>();
</script>

<template>

    <Head title="Liên kết ngân hàng" />

    <div class="space-y-3 px-3 pb-24 pt-3">
        <AccountPageHeader title="Liên kết ngân hàng" description="Thông tin tài khoản dùng để nạp / rút tiền" />

        <div class="flex items-start gap-2 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2.5 text-[12px] leading-relaxed text-amber-900">
            <Info class="mt-0.5 size-4 shrink-0" />
            <span>
                Thông tin ngân hàng do quản trị viên / nhân viên cập nhật. Nếu cần thay đổi, vui lòng liên hệ
                chăm sóc khách hàng để được hỗ trợ.
            </span>
        </div>

        <section class="space-y-3 rounded-2xl border border-stone-200 bg-white p-4 shadow-sm">
            <div class="flex items-center gap-2 text-stone-700">
                <Landmark class="size-4 text-amber-700" />
                <h3 class="text-sm font-semibold">Tài khoản ngân hàng đã liên kết</h3>
            </div>

            <div v-if="!bank.bank_name && !bank.bank_account_number && !bank.bank_account_name"
                class="rounded-xl border border-dashed border-stone-200 bg-stone-50 p-4 text-center text-sm text-stone-500">
                Chưa có thông tin ngân hàng. Vui lòng liên hệ quản trị viên để cập nhật.
            </div>

            <div v-else class="space-y-2">
                <div class="info-row">
                    <span class="info-label">Ngân hàng</span>
                    <span class="info-value">{{ bank.bank_name || '—' }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Số tài khoản</span>
                    <span class="info-value font-mono tracking-wider">{{ bank.bank_account_number || '—' }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Tên chủ tài khoản</span>
                    <span class="info-value uppercase">{{ bank.bank_account_name || '—' }}</span>
                </div>
            </div>
        </section>
    </div>
</template>

<style scoped>
.info-row {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.625rem 0.875rem;
    border: 1.5px solid rgb(231 229 228);
    border-radius: 0.625rem;
    background: rgb(250 250 249);
}

.info-label {
    font-size: 0.75rem;
    font-weight: 600;
    color: rgb(120 113 108);
}

.info-value {
    flex: 1;
    text-align: right;
    color: rgb(28 25 23);
    font-size: 0.9375rem;
    font-weight: 500;
}
</style>
