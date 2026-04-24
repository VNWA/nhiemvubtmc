<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type PaginationMeta = {
    current_page: number;
    from: number | null;
    to: number | null;
    total: number;
    per_page: number;
    last_page: number;
    links: PaginationLink[];
};

const props = defineProps<{
    meta: PaginationMeta;
    only?: string[];
    itemLabel?: string;
}>();

const itemNoun = props.itemLabel ?? 'bản ghi';
</script>

<template>
    <div
        class="flex flex-col items-center justify-between gap-3 border-t border-border/60 bg-muted/30 px-4 py-3 text-xs sm:flex-row dark:border-sidebar-border"
    >
        <p class="text-muted-foreground">
            Hiển thị
            <span class="font-semibold text-foreground">{{ meta.from ?? 0 }}</span>
            –
            <span class="font-semibold text-foreground">{{ meta.to ?? 0 }}</span>
            trong tổng
            <span class="font-semibold text-foreground">{{ meta.total }}</span>
            {{ itemNoun }}
        </p>

        <nav
            v-if="meta.last_page > 1"
            class="flex flex-wrap items-center gap-1"
            aria-label="Phân trang"
        >
            <template v-for="(link, i) in meta.links" :key="i">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    :only="only"
                    preserve-state
                    preserve-scroll
                    class="inline-flex h-8 min-w-8 items-center justify-center rounded-md border border-border/60 px-2 text-xs font-medium transition hover:bg-muted dark:border-sidebar-border"
                    :class="
                        link.active
                            ? 'border-primary bg-primary text-primary-foreground hover:bg-primary/90'
                            : 'text-foreground/80'
                    "
                    v-html="link.label"
                />
                <span
                    v-else
                    class="inline-flex h-8 min-w-8 cursor-not-allowed items-center justify-center rounded-md border border-border/40 px-2 text-xs text-muted-foreground/40 dark:border-sidebar-border/60"
                    v-html="link.label"
                />
            </template>
        </nav>
    </div>
</template>
