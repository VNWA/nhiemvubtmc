<script setup lang="ts">
import AccountController from '@/actions/App/Http/Controllers/Client/AccountController';
import AccountPageHeader from '@/components/AccountPageHeader.vue';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { Landmark, Save } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    bank: {
        bank_name: string | null;
        bank_account_number: string | null;
        bank_account_name: string | null;
    };
    bankOptions: string[];
}>();

const page = usePage();
const flash = computed(() => (page.props as { flash?: { success?: string } }).flash ?? {});

const form = useForm({
    bank_name: props.bank.bank_name ?? '',
    bank_account_number: props.bank.bank_account_number ?? '',
    bank_account_name: props.bank.bank_account_name ?? '',
});

function onAccountNumberInput(e: Event) {
    const el = e.target as HTMLInputElement;
    const cleaned = el.value.replace(/\D+/g, '');
    if (el.value !== cleaned) {
        el.value = cleaned;
    }
    form.bank_account_number = cleaned;
}

function submit() {
    form.put(AccountController.updateBank.url(), { preserveScroll: true });
}
</script>

<template>
    <Head title="Liên kết ngân hàng" />

    <div class="space-y-3 px-3 pb-24 pt-3">
        <AccountPageHeader
            title="Liên kết ngân hàng"
            description="Thông tin dùng để nạp và rút tiền"
        />

        <div v-if="flash.success" class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
            {{ flash.success }}
        </div>

        <form class="space-y-3 rounded-2xl border border-stone-200 bg-white p-4 shadow-sm" @submit.prevent="submit">
            <div class="flex items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-[11px] text-amber-800">
                <Landmark class="size-4 shrink-0" />
                <span>Đảm bảo thông tin chính xác. Tên chủ tài khoản phải trùng khớp với tên đăng ký tại ngân hàng.</span>
            </div>

            <div>
                <label for="bank_name" class="account-label">Tên ngân hàng</label>
                <Select v-model="form.bank_name">
                    <SelectTrigger id="bank_name" class="h-11 w-full rounded-[0.625rem] border-1.5 border-stone-200 bg-white text-[0.9375rem] focus:border-amber-600 focus:ring-3 focus:ring-amber-100">
                        <SelectValue placeholder="Chọn ngân hàng" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="bank in bankOptions" :key="bank" :value="bank">
                            {{ bank }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <p v-if="form.errors.bank_name" class="account-error">{{ form.errors.bank_name }}</p>
            </div>

            <div>
                <label for="bank_account_number" class="account-label">Số tài khoản</label>
                <input
                    id="bank_account_number"
                    :value="form.bank_account_number"
                    type="text"
                    inputmode="numeric"
                    autocomplete="off"
                    maxlength="32"
                    placeholder="Chỉ số, không dấu cách"
                    class="account-input font-mono tracking-wider"
                    :class="{ 'is-invalid': form.errors.bank_account_number }"
                    @input="onAccountNumberInput"
                />
                <p v-if="form.errors.bank_account_number" class="account-error">{{ form.errors.bank_account_number }}</p>
            </div>

            <div>
                <label for="bank_account_name" class="account-label">Tên chủ tài khoản</label>
                <input
                    id="bank_account_name"
                    v-model="form.bank_account_name"
                    type="text"
                    autocomplete="off"
                    maxlength="160"
                    placeholder="VIẾT HOA KHÔNG DẤU"
                    class="account-input uppercase"
                    :class="{ 'is-invalid': form.errors.bank_account_name }"
                />
                <p v-if="form.errors.bank_account_name" class="account-error">{{ form.errors.bank_account_name }}</p>
            </div>

            <button type="submit" class="submit-btn" :disabled="form.processing">
                <Save class="size-4" />
                {{ form.processing ? 'Đang lưu…' : 'Lưu thông tin' }}
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
