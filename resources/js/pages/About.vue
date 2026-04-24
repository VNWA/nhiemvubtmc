<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { BookOpen } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    content?: string | null;
}>();

const hasContent = computed(() => !!props.content && props.content.trim() !== '');
</script>

<template>
    <Head title="Giới thiệu" />

    <div class="space-y-4 px-3 pb-24 pt-3">
        <section class="about-hero">
            <div class="about-hero__icon">
                <BookOpen class="size-6" />
            </div>
            <div>
                <p class="about-hero__eyebrow">Giới thiệu</p>
                <h1 class="about-hero__title">Về chúng tôi</h1>
            </div>
        </section>

        <section class="about-card">
            <div v-if="hasContent" class="about-content" v-html="content" />
            <div v-else class="about-empty">
                <BookOpen class="size-10 text-stone-300" />
                <p class="mt-2 text-sm font-medium text-stone-600">Chưa có nội dung giới thiệu</p>
                <p class="mt-1 text-xs text-stone-500">Quản trị viên sẽ sớm cập nhật thông tin tại đây.</p>
            </div>
        </section>
    </div>
</template>

<style scoped>
.about-hero {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.875rem 1rem;
    border-radius: 1rem;
    background: linear-gradient(135deg, #0a3d7b 0%, #0d4f9e 55%, #1565c0 100%);
    color: #fdf8e8;
    border: 1.5px solid rgba(232, 165, 0, 0.5);
    box-shadow:
        inset 0 -2px 0 rgba(232, 165, 0, 0.55),
        0 10px 24px -12px rgba(13, 79, 158, 0.55);
}

.about-hero__icon {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 2.75rem;
    width: 2.75rem;
    border-radius: 9999px;
    background: rgba(232, 165, 0, 0.22);
    color: #ffd766;
    border: 1px solid rgba(232, 165, 0, 0.35);
}

.about-hero__eyebrow {
    font-size: 0.6875rem;
    font-weight: 700;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: #ffd766;
}

.about-hero__title {
    margin-top: 0.125rem;
    font-size: 1.125rem;
    font-weight: 800;
    color: #ffffff;
}

.about-card {
    background: #ffffff;
    border: 1px solid var(--border, #dbe4ed);
    border-radius: 1rem;
    padding: 1rem 1.125rem;
    box-shadow: 0 6px 18px -12px rgba(13, 79, 158, 0.2);
}

.about-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 2rem 1rem;
    text-align: center;
}

.about-content {
    color: var(--text-body, #102a43);
    line-height: 1.65;
    font-size: 0.9375rem;
    word-break: break-word;
}

.about-content :deep(:is(h1, h2, h3, h4)) {
    color: var(--primary-1, #0d4f9e);
    font-weight: 700;
    line-height: 1.25;
    margin: 1rem 0 0.5rem;
}

.about-content :deep(h1) { font-size: 1.375rem; }
.about-content :deep(h2) { font-size: 1.175rem; }
.about-content :deep(h3) { font-size: 1.0625rem; }

.about-content :deep(p) {
    margin: 0.5rem 0;
}

.about-content :deep(:is(ul, ol)) {
    padding-left: 1.25rem;
    margin: 0.5rem 0;
}

.about-content :deep(ul) { list-style: disc; }
.about-content :deep(ol) { list-style: decimal; }

.about-content :deep(blockquote) {
    border-left: 3px solid var(--primary-2, #e8a500);
    padding: 0.25rem 0 0.25rem 0.875rem;
    color: var(--text-muted, #5a6b7e);
    font-style: italic;
    margin: 0.75rem 0;
}

.about-content :deep(a) {
    color: var(--primary-1, #0d4f9e);
    text-decoration: underline;
    text-underline-offset: 2px;
}

.about-content :deep(a:hover) {
    color: var(--primary-1-hover, #0a3d7b);
}

.about-content :deep(hr) {
    border: 0;
    border-top: 1px solid var(--border, #dbe4ed);
    margin: 1rem 0;
}

.about-content :deep(code) {
    background: var(--surface-muted, #f3f7fc);
    color: var(--primary-1, #0d4f9e);
    padding: 0.05rem 0.35rem;
    border-radius: 0.25rem;
    font-size: 0.85em;
}

.about-content :deep(pre) {
    background: #0f172a;
    color: #f8fafc;
    padding: 0.75rem 1rem;
    border-radius: 0.5rem;
    overflow-x: auto;
    margin: 0.75rem 0;
    font-size: 0.85em;
}
</style>
