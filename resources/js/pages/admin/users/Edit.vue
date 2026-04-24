<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { formatVnd, parseVndInput } from '@/lib/vnd';

const props = defineProps<{
    user: {
        id: number;
        name: string;
        username: string;
        email: string;
        balance_vnd: number;
        role: string;
    };
    roleOptions: string[];
}>();

const selectedRole = ref<string>(props.user.role);
const adjustAmount = ref(0);
const adjustText = ref('');
const adjustOperation = ref<'credit' | 'debit'>('credit');

function onAmountInput(e: Event) {
    const t = e.target as HTMLInputElement;
    const n = parseVndInput(t.value);
    adjustAmount.value = n;
    t.value = n > 0 ? new Intl.NumberFormat('vi-VN').format(n) : '';
    adjustText.value = t.value;
}

function resetAdjust() {
    adjustAmount.value = 0;
    adjustText.value = '';
}

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Users',
                href: UserController.index.url(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Edit user" />

    <div class="grid gap-6 p-4 lg:grid-cols-2">
        <section>
            <Heading
                variant="small"
                class="mb-6"
                title="Edit user"
                description="Update user details, role, or set a new password"
            />

            <Form
                v-bind="UserController.update.form({ user: user.id })"
                class="max-w-md space-y-6"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-2">
                    <Label for="name">Name</Label>
                    <Input
                        id="name"
                        name="name"
                        :default-value="user.name"
                        required
                        autocomplete="name"
                    />
                    <InputError :message="errors.name" />
                </div>
                <div class="grid gap-2">
                    <Label for="username">Username</Label>
                    <Input
                        id="username"
                        name="username"
                        :default-value="user.username"
                        required
                        autocomplete="username"
                    />
                    <InputError :message="errors.username" />
                </div>
                <div class="grid gap-2">
                    <Label>Email</Label>
                    <Input :default-value="user.email" disabled readonly />
                    <p class="text-xs text-muted-foreground">
                        Email is auto-generated and cannot be changed.
                    </p>
                </div>
                <div class="grid gap-2">
                    <Label for="role">Role</Label>
                    <Select v-model="selectedRole">
                        <SelectTrigger id="role" class="w-full">
                            <SelectValue placeholder="Chọn vai trò" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="r in roleOptions" :key="r" :value="r">
                                {{ r }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <input type="hidden" name="role" :value="selectedRole" />
                    <InputError :message="errors.role" />
                </div>
                <div class="grid gap-2">
                    <Label for="password">
                        New password <span class="text-muted-foreground">(optional)</span>
                    </Label>
                    <PasswordInput id="password" name="password" autocomplete="new-password" />
                    <InputError :message="errors.password" />
                </div>
                <div class="grid gap-2">
                    <Label for="password_confirmation">Confirm new password</Label>
                    <PasswordInput
                        id="password_confirmation"
                        name="password_confirmation"
                        autocomplete="new-password"
                    />
                </div>
                <div class="flex gap-2">
                    <Button type="submit" :disabled="processing">
                        <Spinner v-if="processing" />
                        Save
                    </Button>
                    <Button variant="secondary" as-child>
                        <Link :href="UserController.index.url()">Cancel</Link>
                    </Button>
                </div>
            </Form>
        </section>

        <section>
            <Heading
                variant="small"
                class="mb-6"
                title="Số dư"
                description="Nạp thêm hoặc trừ số dư VNĐ của tài khoản"
            />

            <div class="mb-4 rounded-xl border bg-muted/40 p-4">
                <p class="text-xs uppercase tracking-wide text-muted-foreground">Số dư hiện tại</p>
                <p class="font-mono text-2xl font-bold">{{ formatVnd(user.balance_vnd) }}</p>
            </div>

            <Form
                v-bind="UserController.adjustBalance.form({ user: user.id })"
                class="max-w-md space-y-4"
                v-slot="{ errors, processing }"
                @success="resetAdjust"
            >
                <div class="grid gap-2">
                    <Label for="amount_vnd">Số tiền (VNĐ)</Label>
                    <Input
                        id="amount_vnd"
                        type="text"
                        inputmode="numeric"
                        autocomplete="off"
                        class="font-mono"
                        placeholder="VD: 100.000"
                        :value="adjustText"
                        @input="onAmountInput"
                    />
                    <input type="hidden" name="amount_vnd" :value="adjustAmount" />
                    <InputError :message="errors.amount_vnd" />
                </div>

                <div class="grid gap-2">
                    <Label>Loại điều chỉnh</Label>
                    <div class="flex gap-2">
                        <label
                            class="flex flex-1 cursor-pointer items-center justify-center gap-2 rounded-md border p-2 text-sm"
                            :class="adjustOperation === 'credit' ? 'border-emerald-500 bg-emerald-50 text-emerald-700' : 'bg-background'"
                        >
                            <input
                                type="radio"
                                name="operation"
                                value="credit"
                                class="hidden"
                                :checked="adjustOperation === 'credit'"
                                @change="adjustOperation = 'credit'"
                            />
                            + Nạp tiền
                        </label>
                        <label
                            class="flex flex-1 cursor-pointer items-center justify-center gap-2 rounded-md border p-2 text-sm"
                            :class="adjustOperation === 'debit' ? 'border-red-500 bg-red-50 text-red-700' : 'bg-background'"
                        >
                            <input
                                type="radio"
                                name="operation"
                                value="debit"
                                class="hidden"
                                :checked="adjustOperation === 'debit'"
                                @change="adjustOperation = 'debit'"
                            />
                            − Trừ tiền
                        </label>
                    </div>
                    <InputError :message="errors.operation" />
                </div>

                <div class="grid gap-2">
                    <Label for="note">Ghi chú (tuỳ chọn)</Label>
                    <Input id="note" name="note" maxlength="255" />
                    <InputError :message="errors.note" />
                </div>

                <div class="flex gap-2">
                    <Button type="submit" :disabled="processing || adjustAmount <= 0">
                        <Spinner v-if="processing" />
                        Áp dụng
                    </Button>
                    <Button type="button" variant="ghost" @click="resetAdjust">Đặt lại</Button>
                </div>
            </Form>
        </section>
    </div>
</template>
