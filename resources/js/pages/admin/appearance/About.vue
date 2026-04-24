<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Loader2, Save } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import AppearanceController from '@/actions/App/Http/Controllers/Admin/AppearanceController';
import Heading from '@/components/Heading.vue';
import RichTextEditor from '@/components/RichTextEditor.vue';
import { Button } from '@/components/ui/button';

type AboutData = {
    content?: string;
};

const props = defineProps<{
    appearanceKey: string;
    data: AboutData | unknown[];
}>();

const initialContent = computed<string>(() => {
    const value = (props.data as AboutData)?.content;
    return typeof value === 'string' ? value : '';
});

const content = ref<string>(initialContent.value);
const savedContent = ref<string>(initialContent.value);
const saving = ref(false);

watch(initialContent, (incoming) => {
    content.value = incoming;
    savedContent.value = incoming;
});

const isDirty = computed(() => content.value !== savedContent.value);

async function save() {
    if (!isDirty.value || saving.value) {
        return;
    }

    saving.value = true;
    try {
        const res = await fetch(AppearanceController.update(props.appearanceKey).url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': decodeURIComponent(getCookie('XSRF-TOKEN') ?? ''),
            },
            body: JSON.stringify({ content: content.value }),
        });

        if (!res.ok) {
            throw new Error(`Lưu thất bại (${res.status})`);
        }

        savedContent.value = content.value;
        toast.success('Đã lưu nội dung giới thiệu');
    } catch (error) {
        const message = error instanceof Error ? error.message : 'Có lỗi xảy ra';
        toast.error(message);
    } finally {
        saving.value = false;
    }
}

function reset() {
    content.value = savedContent.value;
}

function getCookie(name: string): string | undefined {
    if (typeof document === 'undefined') {
        return undefined;
    }
    const match = document.cookie
        .split('; ')
        .find((row) => row.startsWith(`${name}=`));
    return match ? match.split('=')[1] : undefined;
}

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Giao diện',
                href: AppearanceController.view('About').url,
            },
            {
                title: 'Giới thiệu',
                href: AppearanceController.view('About').url,
            },
        ],
    },
});
</script>

<template>
    <Head title="Giới thiệu" />

    <div class="space-y-4 p-4">
        <header class="flex flex-wrap items-end justify-between gap-3">
            <Heading
                title="Nội dung giới thiệu"
                description="Cập nhật phần giới thiệu hiển thị trên trang công khai. Hỗ trợ định dạng văn bản đầy đủ."
            />

            <div class="flex items-center gap-2">
                <Button
                    type="button"
                    variant="outline"
                    :disabled="!isDirty || saving"
                    @click="reset"
                >
                    Khôi phục
                </Button>
                <Button
                    type="button"
                    :disabled="!isDirty || saving"
                    @click="save"
                >
                    <Loader2 v-if="saving" class="size-4 animate-spin" />
                    <Save v-else class="size-4" />
                    {{ saving ? 'Đang lưu…' : 'Lưu thay đổi' }}
                </Button>
            </div>
        </header>

        <RichTextEditor
            v-model="content"
            placeholder="Viết nội dung giới thiệu cho khách hàng…"
            min-height="420px"
        />

        <p class="text-xs text-stone-500">
            Mẹo: dùng Ctrl/⌘ + B để in đậm, Ctrl/⌘ + I để in nghiêng, Ctrl/⌘ + Z để hoàn tác.
        </p>
    </div>
</template>
