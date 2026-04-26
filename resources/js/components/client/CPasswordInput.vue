<script setup lang="ts">
import { Eye, EyeOff } from 'lucide-vue-next';
import { ref, useTemplateRef, watch } from 'vue';
import CInput from './CInput.vue';

defineOptions({ inheritAttrs: false });

const props = defineProps<{
    modelValue?: string;
    ariaInvalid?: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
}>();

/**
 * Bộ đệm nội bộ: khi trang dùng input chỉ gắn `name` (không v-model) thì
 * `modelValue` từ cha luôn undefined — nếu gán thẳng `modelValue ?? ''` xuống
 * CInput, mỗi lần bật/tắt “hiện mật khẩu” re-render sẽ reset về rỗng.
 * Có v-model: đồng bộ từ props vào bộ đệm khi phía cha đổi giá trị.
 */
const inner = ref('');

watch(
    () => props.modelValue,
    (v) => {
        if (v !== undefined) {
            inner.value = v == null ? '' : String(v);
        }
    },
    { immediate: true },
);

function onInput(v: string) {
    inner.value = v;
    emit('update:modelValue', v);
}

const showPassword = ref(false);
const inputRef = useTemplateRef<InstanceType<typeof CInput>>('inputRef');

defineExpose({
    focus: () => inputRef.value?.focus(),
});
</script>

<template>
    <div class="c-password-wrap">
        <CInput
            ref="inputRef"
            :type="showPassword ? 'text' : 'password'"
            has-trailing
            :model-value="inner"
            :aria-invalid="ariaInvalid"
            v-bind="$attrs"
            @update:model-value="onInput"
        />
        <button
            type="button"
            class="c-password-eye"
            :aria-label="showPassword ? 'Ẩn mật khẩu' : 'Hiện mật khẩu'"
            :tabindex="-1"
            @click="showPassword = !showPassword"
        >
            <EyeOff v-if="showPassword" class="size-4" />
            <Eye v-else class="size-4" />
        </button>
    </div>
</template>
