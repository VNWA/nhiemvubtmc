<script setup lang="ts">
import EventRoomController from '@/actions/App/Http/Controllers/Admin/EventRoomController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Form, Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{
    room: {
        id: number;
        name: string;
        slug: string;
        avatar_url: string | null;
        is_active: boolean;
    };
}>();

const isActive = ref(props.room.is_active);
const removeAvatar = ref(false);
const avatarPreview = ref<string | null>(null);

function onAvatarChange(e: Event) {
    const target = e.target as HTMLInputElement;
    const file = target.files?.[0] ?? null;
    if (avatarPreview.value) {
        URL.revokeObjectURL(avatarPreview.value);
        avatarPreview.value = null;
    }
    if (file) {
        avatarPreview.value = URL.createObjectURL(file);
        removeAvatar.value = false;
    }
}

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Phòng sự kiện', href: EventRoomController.index.url() },
            { title: 'Sửa', href: '#' },
        ],
    },
});
</script>

<template>
    <Head :title="`Sửa: ${room.name}`" />

    <div class="p-4">
        <Heading
            class="mb-6"
            title="Sửa phòng"
            description="Slug được sinh khi tạo phòng và không cần chỉnh; bạn có thể đổi tên, ảnh và trạng thái tại đây"
        />

        <Form
            :action="EventRoomController.update.url({ event_room: room.id })"
            method="post"
            enctype="multipart/form-data"
            class="max-w-xl space-y-4"
            v-slot="{ errors, processing }"
        >
            <input type="hidden" name="_method" value="put" />

            <div class="grid gap-2">
                <Label for="name">Tên phòng</Label>
                <Input id="name" name="name" :default-value="room.name" required />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-1 text-sm text-muted-foreground">
                <span>Slug: <code class="rounded bg-muted px-1.5 py-0.5 text-foreground">{{ room.slug }}</code></span>
                <span class="text-xs">(slug được sinh tự động từ tên khi tạo phòng)</span>
            </div>

            <div class="grid gap-2">
                <Label for="avatar">Ảnh đại diện</Label>
                <div class="flex items-center gap-3">
                    <div class="flex size-16 items-center justify-center overflow-hidden rounded-full border bg-muted">
                        <img
                            v-if="avatarPreview"
                            :src="avatarPreview"
                            alt="avatar preview"
                            class="size-full object-cover"
                        />
                        <img
                            v-else-if="room.avatar_url && !removeAvatar"
                            :src="room.avatar_url"
                            :alt="room.name"
                            class="size-full object-cover"
                        />
                        <span v-else class="text-xs text-muted-foreground">No image</span>
                    </div>
                    <div class="flex flex-col gap-2">
                        <Input id="avatar" name="avatar" type="file" accept="image/*" @change="onAvatarChange" />
                        <label v-if="room.avatar_url" class="flex items-center gap-2 text-sm text-muted-foreground">
                            <input
                                type="hidden"
                                name="remove_avatar"
                                :value="removeAvatar ? 1 : 0"
                            />
                            <input
                                :checked="removeAvatar"
                                type="checkbox"
                                @change="removeAvatar = ($event.target as HTMLInputElement).checked"
                            />
                            Xoá ảnh hiện tại
                        </label>
                    </div>
                </div>
                <InputError :message="errors.avatar" />
            </div>

            <div class="flex items-center gap-2">
                <input type="hidden" name="is_active" :value="isActive ? 1 : 0" />
                <input
                    id="is_active"
                    :checked="isActive"
                    type="checkbox"
                    @change="isActive = ($event.target as HTMLInputElement).checked"
                />
                <Label for="is_active">Đang hoạt động</Label>
            </div>
            <p v-if="errors.is_active" class="text-sm text-destructive">
                {{ errors.is_active }}
            </p>

            <div class="flex gap-2">
                <Button type="submit" :disabled="processing">Cập nhật</Button>
                <Button variant="outline" as-child>
                    <Link :href="EventRoomController.index.url()">Huỷ</Link>
                </Button>
            </div>
        </Form>
    </div>
</template>
