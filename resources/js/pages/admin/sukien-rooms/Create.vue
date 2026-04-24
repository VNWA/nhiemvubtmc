<script setup lang="ts">
import EventRoomController from '@/actions/App/Http/Controllers/Admin/EventRoomController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { randomColorPair } from '@/lib/colors';
import { Form, Head, Link } from '@inertiajs/vue3';
import { Plus, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';

type Opt = { label: string; bg_color: string; text_color: string };

const options = ref<Opt[]>([
    { label: 'Tài', bg_color: '#1565c0', text_color: '#ffffff' },
    { label: 'Xỉu', bg_color: '#c62828', text_color: '#ffffff' },
]);

const avatarFile = ref<File | null>(null);
const avatarPreview = ref<string | null>(null);

function onAvatarChange(e: Event) {
    const target = e.target as HTMLInputElement;
    const file = target.files?.[0] ?? null;
    avatarFile.value = file;
    if (avatarPreview.value) {
        URL.revokeObjectURL(avatarPreview.value);
        avatarPreview.value = null;
    }
    if (file) {
        avatarPreview.value = URL.createObjectURL(file);
    }
}

function clearAvatar() {
    avatarFile.value = null;
    if (avatarPreview.value) {
        URL.revokeObjectURL(avatarPreview.value);
        avatarPreview.value = null;
    }
}

function addOption() {
    const pair = randomColorPair();
    options.value.push({ label: '', ...pair });
}

function shuffleOptionColors(i: number) {
    const pair = randomColorPair();
    options.value[i].bg_color = pair.bg_color;
    options.value[i].text_color = pair.text_color;
}

function removeOption(i: number) {
    if (options.value.length <= 2) {
        return;
    }
    options.value.splice(i, 1);
}

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Phòng sự kiện', href: EventRoomController.index.url() },
            { title: 'Tạo mới', href: EventRoomController.create.url() },
        ],
    },
});
</script>

<template>
    <Head title="Tạo phòng sự kiện" />

    <div class="p-4">
        <Heading
            class="mb-6"
            title="Tạo phòng sự kiện"
            description="Đặt tên, chọn ảnh đại diện (tuỳ chọn) và ít nhất 2 mặt kết quả. Slug sẽ được tự sinh từ tên."
        />

        <Form
            :action="EventRoomController.store.url()"
            method="post"
            enctype="multipart/form-data"
            class="max-w-xl space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="name">Tên phòng</Label>
                <Input id="name" name="name" required />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="viewer_offset">Số người xem bù</Label>
                <Input
                    id="viewer_offset"
                    name="viewer_offset"
                    type="number"
                    min="0"
                    max="999999"
                    :default-value="0"
                    class="max-w-[200px]"
                />
                <p class="text-xs text-muted-foreground">
                    Giá trị này sẽ được cộng thêm vào số người xem hiển thị cho user (bù vào số
                    realtime thực). VD: set 19 → user thấy "19 + số thật đang xem".
                </p>
                <InputError :message="errors.viewer_offset" />
            </div>

            <div class="grid gap-2">
                <Label for="avatar">Ảnh đại diện (tuỳ chọn)</Label>
                <div class="flex items-center gap-3">
                    <div class="flex size-16 items-center justify-center overflow-hidden rounded-full border bg-muted">
                        <img
                            v-if="avatarPreview"
                            :src="avatarPreview"
                            alt="avatar preview"
                            class="size-full object-cover"
                        />
                        <span v-else class="text-xs text-muted-foreground">No image</span>
                    </div>
                    <div class="flex flex-col gap-2">
                        <Input
                            id="avatar"
                            name="avatar"
                            type="file"
                            accept="image/*"
                            @change="onAvatarChange"
                        />
                        <Button v-if="avatarFile" type="button" variant="ghost" size="sm" @click="clearAvatar">
                            Bỏ chọn
                        </Button>
                    </div>
                </div>
                <InputError :message="errors.avatar" />
            </div>

            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <Label>Kết quả (tối thiểu 2)</Label>
                    <Button type="button" variant="outline" size="sm" @click="addOption">
                        <Plus class="mr-1 size-4" />
                        Thêm
                    </Button>
                </div>
                <div
                    v-for="(opt, i) in options"
                    :key="i"
                    class="flex flex-wrap items-end gap-2 rounded-md border p-2"
                >
                    <div class="min-w-0 flex-1">
                        <Label :for="'l-' + i" class="text-xs">Nhãn</Label>
                        <Input :id="'l-' + i" v-model="opt.label" :name="`options[${i}][label]`" required />
                    </div>
                    <div>
                        <Label :for="'bg-' + i" class="text-xs">Nền</Label>
                        <Input
                            :id="'bg-' + i"
                            v-model="opt.bg_color"
                            :name="`options[${i}][bg_color]`"
                            type="color"
                            class="h-9 w-12 p-0"
                        />
                    </div>
                    <div>
                        <Label :for="'tc-' + i" class="text-xs">Chữ</Label>
                        <Input
                            :id="'tc-' + i"
                            v-model="opt.text_color"
                            :name="`options[${i}][text_color]`"
                            type="color"
                            class="h-9 w-12 p-0"
                        />
                    </div>
                    <div>
                        <Label class="text-xs">Xem trước</Label>
                        <span
                            class="inline-flex h-9 min-w-[64px] items-center justify-center rounded-md px-3 text-sm font-semibold"
                            :style="{ backgroundColor: opt.bg_color, color: opt.text_color }"
                        >
                            {{ opt.label || 'Mẫu' }}
                        </span>
                    </div>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        title="Đổi màu ngẫu nhiên"
                        @click="shuffleOptionColors(i)"
                    >
                        🎲
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        :disabled="options.length <= 2"
                        @click="removeOption(i)"
                    >
                        <Trash2 class="size-4" />
                    </Button>
                </div>
                <InputError :message="errors['options.0.label']" />
            </div>

            <div class="flex gap-2">
                <Button type="submit" :disabled="processing">Lưu</Button>
                <Button variant="outline" as-child>
                    <Link :href="EventRoomController.index.url()">Huỷ</Link>
                </Button>
            </div>
        </Form>
    </div>
</template>
