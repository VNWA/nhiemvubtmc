<script setup lang="ts">
import { Eye, EyeOff } from 'lucide-vue-next';
import { ref, useTemplateRef } from 'vue';
import CInput from './CInput.vue';

defineOptions({ inheritAttrs: false });

defineProps<{
    modelValue?: string;
    ariaInvalid?: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
}>();

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
            :model-value="modelValue ?? ''"
            :aria-invalid="ariaInvalid"
            v-bind="$attrs"
            @update:model-value="(v) => emit('update:modelValue', v as string)"
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
