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

const props = defineProps<{
    roleOptions: string[];
}>();

const selectedRole = ref<string>(props.roleOptions[0] ?? '');

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Users',
                href: UserController.index.url(),
            },
            {
                title: 'Add user',
                href: UserController.create.url(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Add user" />

    <div class="p-4">
        <Heading
            variant="small"
            class="mb-6"
            title="Add user"
            description="Create a new user and assign a role"
        />

        <Form
            v-bind="UserController.store.form()"
            :reset-on-success="['password', 'password_confirmation']"
            class="max-w-md space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="name">Name</Label>
                <Input
                    id="name"
                    name="name"
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
                    required
                    autocomplete="username"
                />
                <InputError :message="errors.username" />
                <p class="text-xs text-muted-foreground">
                    Email will be generated automatically.
                </p>
            </div>
            <div class="grid gap-2">
                <Label for="role">Role</Label>
                <Select v-model="selectedRole">
                    <SelectTrigger id="role" class="w-full">
                        <SelectValue placeholder="Chọn vai trò" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="r in props.roleOptions" :key="r" :value="r">
                            {{ r }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <input type="hidden" name="role" :value="selectedRole" />
                <InputError :message="errors.role" />
            </div>
            <div class="grid gap-2">
                <Label for="password">Password</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    autocomplete="new-password"
                />
                <InputError :message="errors.password" />
            </div>
            <div class="grid gap-2">
                <Label for="password_confirmation">Confirm password</Label>
                <PasswordInput
                    id="password_confirmation"
                    name="password_confirmation"
                    required
                    autocomplete="new-password"
                />
            </div>
            <div class="flex gap-2">
                <Button type="submit" :disabled="processing">
                    <Spinner v-if="processing" />
                    Create user
                </Button>
                <Button variant="secondary" as-child>
                    <Link :href="UserController.index.url()">Cancel</Link>
                </Button>
            </div>
        </Form>
    </div>
</template>
