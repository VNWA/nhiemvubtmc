<script setup lang="ts">
import Link from '@tiptap/extension-link';
import Placeholder from '@tiptap/extension-placeholder';
import TextAlign from '@tiptap/extension-text-align';
import StarterKit from '@tiptap/starter-kit';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import {
    AlignCenter,
    AlignLeft,
    AlignRight,
    Bold,
    Code,
    Heading1,
    Heading2,
    Heading3,
    Italic,
    Link as LinkIcon,
    List,
    ListOrdered,
    Minus,
    Pilcrow,
    Quote,
    Redo2,
    Strikethrough,
    Undo2,
    Unlink,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        modelValue?: string;
        placeholder?: string;
        editable?: boolean;
        minHeight?: string;
    }>(),
    {
        modelValue: '',
        placeholder: 'Nhập nội dung…',
        editable: true,
        minHeight: '320px',
    },
);

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
}>();

const editor = useEditor({
    content: props.modelValue || '',
    editable: props.editable,
    extensions: [
        StarterKit.configure({
            link: false,
        }),
        Link.configure({
            openOnClick: false,
            autolink: true,
            HTMLAttributes: {
                class: 'tiptap-link',
                rel: 'noopener noreferrer nofollow',
                target: '_blank',
            },
        }),
        Placeholder.configure({
            placeholder: () => props.placeholder,
            emptyEditorClass: 'is-editor-empty',
        }),
        TextAlign.configure({
            types: ['heading', 'paragraph'],
        }),
    ],
    editorProps: {
        attributes: {
            class: 'tiptap-content prose prose-sm max-w-none focus:outline-none',
        },
    },
    onUpdate: ({ editor: e }) => {
        const html = e.getHTML();
        const isEmpty = e.isEmpty;
        emit('update:modelValue', isEmpty ? '' : html);
    },
});

watch(
    () => props.modelValue,
    (incoming) => {
        if (!editor.value) {
            return;
        }
        const current = editor.value.getHTML();
        const next = incoming || '';
        if (next === current) {
            return;
        }
        if (next === '' && editor.value.isEmpty) {
            return;
        }
        editor.value.commands.setContent(next, { emitUpdate: false });
    },
);

watch(
    () => props.editable,
    (value) => editor.value?.setEditable(value),
);

onBeforeUnmount(() => {
    editor.value?.destroy();
});

function setLink() {
    if (!editor.value) {
        return;
    }
    const previousUrl = editor.value.getAttributes('link').href as string | undefined;
    const url = window.prompt('Nhập URL liên kết', previousUrl ?? 'https://');

    if (url === null) {
        return;
    }

    if (url === '') {
        editor.value.chain().focus().extendMarkRange('link').unsetLink().run();

        return;
    }

    editor.value
        .chain()
        .focus()
        .extendMarkRange('link')
        .setLink({ href: url })
        .run();
}

function unsetLink() {
    editor.value?.chain().focus().unsetLink().run();
}

const tools = computed(() => {
    const e = editor.value;
    if (!e) {
        return [];
    }

    return [
        {
            key: 'bold',
            icon: Bold,
            label: 'Đậm',
            onClick: () => e.chain().focus().toggleBold().run(),
            isActive: () => e.isActive('bold'),
        },
        {
            key: 'italic',
            icon: Italic,
            label: 'Nghiêng',
            onClick: () => e.chain().focus().toggleItalic().run(),
            isActive: () => e.isActive('italic'),
        },
        {
            key: 'strike',
            icon: Strikethrough,
            label: 'Gạch ngang',
            onClick: () => e.chain().focus().toggleStrike().run(),
            isActive: () => e.isActive('strike'),
        },
        {
            key: 'code',
            icon: Code,
            label: 'Code',
            onClick: () => e.chain().focus().toggleCode().run(),
            isActive: () => e.isActive('code'),
        },
        { divider: true, key: 'd1' },
        {
            key: 'h1',
            icon: Heading1,
            label: 'Tiêu đề 1',
            onClick: () => e.chain().focus().toggleHeading({ level: 1 }).run(),
            isActive: () => e.isActive('heading', { level: 1 }),
        },
        {
            key: 'h2',
            icon: Heading2,
            label: 'Tiêu đề 2',
            onClick: () => e.chain().focus().toggleHeading({ level: 2 }).run(),
            isActive: () => e.isActive('heading', { level: 2 }),
        },
        {
            key: 'h3',
            icon: Heading3,
            label: 'Tiêu đề 3',
            onClick: () => e.chain().focus().toggleHeading({ level: 3 }).run(),
            isActive: () => e.isActive('heading', { level: 3 }),
        },
        {
            key: 'paragraph',
            icon: Pilcrow,
            label: 'Đoạn văn',
            onClick: () => e.chain().focus().setParagraph().run(),
            isActive: () => e.isActive('paragraph'),
        },
        { divider: true, key: 'd2' },
        {
            key: 'bullet',
            icon: List,
            label: 'Danh sách',
            onClick: () => e.chain().focus().toggleBulletList().run(),
            isActive: () => e.isActive('bulletList'),
        },
        {
            key: 'ordered',
            icon: ListOrdered,
            label: 'Đánh số',
            onClick: () => e.chain().focus().toggleOrderedList().run(),
            isActive: () => e.isActive('orderedList'),
        },
        {
            key: 'quote',
            icon: Quote,
            label: 'Trích dẫn',
            onClick: () => e.chain().focus().toggleBlockquote().run(),
            isActive: () => e.isActive('blockquote'),
        },
        {
            key: 'rule',
            icon: Minus,
            label: 'Đường kẻ',
            onClick: () => e.chain().focus().setHorizontalRule().run(),
            isActive: () => false,
        },
        { divider: true, key: 'd3' },
        {
            key: 'left',
            icon: AlignLeft,
            label: 'Căn trái',
            onClick: () => e.chain().focus().setTextAlign('left').run(),
            isActive: () => e.isActive({ textAlign: 'left' }),
        },
        {
            key: 'center',
            icon: AlignCenter,
            label: 'Căn giữa',
            onClick: () => e.chain().focus().setTextAlign('center').run(),
            isActive: () => e.isActive({ textAlign: 'center' }),
        },
        {
            key: 'right',
            icon: AlignRight,
            label: 'Căn phải',
            onClick: () => e.chain().focus().setTextAlign('right').run(),
            isActive: () => e.isActive({ textAlign: 'right' }),
        },
        { divider: true, key: 'd4' },
        {
            key: 'link',
            icon: LinkIcon,
            label: 'Chèn liên kết',
            onClick: setLink,
            isActive: () => e.isActive('link'),
        },
        {
            key: 'unlink',
            icon: Unlink,
            label: 'Bỏ liên kết',
            onClick: unsetLink,
            isActive: () => false,
            disabled: () => !e.isActive('link'),
        },
        { divider: true, key: 'd5' },
        {
            key: 'undo',
            icon: Undo2,
            label: 'Hoàn tác',
            onClick: () => e.chain().focus().undo().run(),
            isActive: () => false,
            disabled: () => !e.can().undo(),
        },
        {
            key: 'redo',
            icon: Redo2,
            label: 'Làm lại',
            onClick: () => e.chain().focus().redo().run(),
            isActive: () => false,
            disabled: () => !e.can().redo(),
        },
    ];
});

defineExpose({ editor });
</script>

<template>
    <div class="rte" :class="{ 'rte--readonly': !editable }">
        <div v-if="editable" class="rte-toolbar">
            <template v-for="tool in tools" :key="tool.key">
                <span v-if="(tool as { divider?: boolean }).divider" class="rte-divider" aria-hidden="true" />
                <button
                    v-else
                    type="button"
                    class="rte-btn"
                    :class="{ 'is-active': tool.isActive?.() }"
                    :disabled="tool.disabled?.() ?? false"
                    :title="tool.label"
                    :aria-label="tool.label"
                    @click="tool.onClick"
                >
                    <component :is="tool.icon" class="size-4" />
                </button>
            </template>
        </div>

        <EditorContent
            class="rte-surface"
            :style="{ minHeight }"
            :editor="editor"
        />
    </div>
</template>

<style scoped>
.rte {
    border: 1px solid var(--border, #dbe4ed);
    border-radius: 0.75rem;
    background: #ffffff;
    overflow: hidden;
    transition: border-color 150ms ease, box-shadow 150ms ease;
}

.rte:focus-within {
    border-color: var(--primary-1, #0d4f9e);
    box-shadow: 0 0 0 3px rgba(13, 79, 158, 0.14);
}

.rte--readonly {
    background: var(--surface-muted, #f3f7fc);
}

.rte-toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.125rem;
    padding: 0.375rem;
    background: var(--surface-muted, #f3f7fc);
    border-bottom: 1px solid var(--border, #dbe4ed);
    position: sticky;
    top: 0;
    z-index: 1;
}

.rte-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    height: 1.875rem;
    width: 1.875rem;
    border-radius: 0.375rem;
    color: var(--text-body, #102a43);
    background: transparent;
    border: 1px solid transparent;
    cursor: pointer;
    transition: background-color 120ms ease, color 120ms ease, border-color 120ms ease;
}

.rte-btn:hover:not(:disabled) {
    background: rgba(13, 79, 158, 0.08);
    color: var(--primary-1, #0d4f9e);
}

.rte-btn.is-active {
    background: var(--primary-1, #0d4f9e);
    color: #fdf8e8;
    border-color: var(--primary-1, #0d4f9e);
}

.rte-btn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

.rte-divider {
    display: inline-block;
    width: 1px;
    height: 1.25rem;
    margin: 0 0.25rem;
    background: var(--border, #dbe4ed);
}

.rte-surface {
    padding: 0.875rem 1rem;
}

:deep(.tiptap-content) {
    min-height: inherit;
    color: var(--text-body, #102a43);
    line-height: 1.6;
    font-size: 0.9375rem;
}

:deep(.tiptap-content p) {
    margin: 0.5rem 0;
}

:deep(.tiptap-content :is(h1, h2, h3, h4)) {
    color: var(--primary-1, #0d4f9e);
    font-weight: 700;
    line-height: 1.25;
    margin: 1rem 0 0.5rem;
}

:deep(.tiptap-content h1) {
    font-size: 1.5rem;
}

:deep(.tiptap-content h2) {
    font-size: 1.25rem;
}

:deep(.tiptap-content h3) {
    font-size: 1.125rem;
}

:deep(.tiptap-content :is(ul, ol)) {
    padding-left: 1.5rem;
    margin: 0.5rem 0;
}

:deep(.tiptap-content ul) {
    list-style: disc;
}

:deep(.tiptap-content ol) {
    list-style: decimal;
}

:deep(.tiptap-content li > p) {
    margin: 0.125rem 0;
}

:deep(.tiptap-content blockquote) {
    border-left: 3px solid var(--primary-2, #e8a500);
    padding: 0.25rem 0 0.25rem 0.875rem;
    color: var(--text-muted, #5a6b7e);
    font-style: italic;
    margin: 0.75rem 0;
}

:deep(.tiptap-content code) {
    background: var(--surface-muted, #f3f7fc);
    color: var(--primary-1, #0d4f9e);
    padding: 0.05rem 0.35rem;
    border-radius: 0.25rem;
    font-size: 0.85em;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace;
}

:deep(.tiptap-content pre) {
    background: #0f172a;
    color: #f8fafc;
    padding: 0.75rem 1rem;
    border-radius: 0.5rem;
    overflow-x: auto;
    margin: 0.75rem 0;
    font-size: 0.85em;
}

:deep(.tiptap-content pre code) {
    background: transparent;
    color: inherit;
    padding: 0;
}

:deep(.tiptap-content hr) {
    border: 0;
    border-top: 1px solid var(--border, #dbe4ed);
    margin: 1rem 0;
}

:deep(.tiptap-content .tiptap-link) {
    color: var(--primary-1, #0d4f9e);
    text-decoration: underline;
    text-underline-offset: 2px;
}

:deep(.tiptap-content .tiptap-link:hover) {
    color: var(--primary-1-hover, #0a3d7b);
}

:deep(.tiptap-content p.is-editor-empty:first-child::before) {
    content: attr(data-placeholder);
    float: left;
    color: var(--text-muted, #5a6b7e);
    pointer-events: none;
    height: 0;
}

:deep(.tiptap-content:focus) {
    outline: none;
}
</style>
