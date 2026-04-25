<script lang="ts" setup>
import { Link } from '@inertiajs/vue3';
import { Swiper, SwiperSlide } from 'swiper/vue';
import { Autoplay, Pagination } from 'swiper/modules';

import 'swiper/css';
import 'swiper/css/pagination';

type Shortcut = {
    label: string;
    href: string;
    image: string;
    tone: 'primary' | 'gold' | 'sky';
};

const banners: string[] = [
    '/images/banner/banner1.png',
    '/images/banner/banner2.jpg',
    '/images/banner/banner4.jpg',

];

const shortcuts: Shortcut[] = [
    {
        label: 'Rút tiền',
        href: '/rut-tien',
        image: '/images/ruttien.png',
        tone: 'gold',
    },
    {
        label: 'Sự kiện',
        href: '/sukien',
        image: '/images/sukien.png',
        tone: 'primary',
    },
    {
        label: 'Giới thiệu',
        href: '/gioi-thieu',
        image: '/images/gioithieu.png',
        tone: 'sky',
    },
];
</script>

<template>
    <div class="space-y-4 pb-24">
        <div class="overflow-hidden border border-stone-100 shadow-sm">
            <Swiper :modules="[Autoplay, Pagination]" :loop="banners.length > 1"
                :autoplay="{ delay: 4000, disableOnInteraction: false }" :pagination="{ clickable: true }"
                :slides-per-view="1" :space-between="0" class="home-banner-swiper">
                <SwiperSlide v-for="(src, i) in banners" :key="i">
                    <img :src="src" :alt="`Banner ${i + 1}`" class="h-full w-full " />
                </SwiperSlide>
            </Swiper>
        </div>

        <div class="px-1">
            <ul class="grid grid-cols-3 gap-3">
                <li v-for="item in shortcuts" :key="item.label">
                    <Link :href="item.href"
                        class="group flex flex-col items-center justify-center gap-2 rounded-xl p-3 transition active:scale-[0.98] active:opacity-80">
                        <span class="shortcut-icon" :data-tone="item.tone">
                            <img :src="item.image" :alt="item.label" class="size-9 object-contain" />
                        </span>
                        <span class="shortcut-label">{{ item.label }}</span>
                    </Link>
                </li>
            </ul>
        </div>
    </div>
</template>

<style scoped>
.home-banner-swiper {
    width: 100%;
    aspect-ratio: 16 / 7;
}

.home-banner-swiper :deep(.swiper-pagination-bullet) {
    background: #ffffff;
    opacity: 0.55;
}

.home-banner-swiper :deep(.swiper-pagination-bullet-active) {
    background: var(--primary-2, #e8a500);
    opacity: 1;
}

.shortcut-icon {
    display: inline-flex;
    height: 4rem;
    width: 4rem;
    align-items: center;
    justify-content: center;
    border-radius: 9999px;
    border: 1.5px solid transparent;
    box-shadow: 0 6px 16px -10px rgba(13, 79, 158, 0.35);
    transition: transform 150ms ease, box-shadow 150ms ease, filter 150ms ease;
}

.group:hover .shortcut-icon {
    transform: translateY(-2px);
    filter: brightness(1.04);
}

.shortcut-icon[data-tone='primary'] {
    background: linear-gradient(135deg, #0d4f9e 0%, #1565c0 100%);
    border-color: rgba(232, 165, 0, 0.55);
    box-shadow:
        inset 0 -2px 0 rgba(232, 165, 0, 0.6),
        0 8px 18px -10px rgba(13, 79, 158, 0.5);
}

.shortcut-icon[data-tone='gold'] {
    background: linear-gradient(135deg, #fbc43a 0%, #e8a500 100%);
    border-color: rgba(13, 79, 158, 0.45);
    box-shadow:
        inset 0 -2px 0 rgba(13, 79, 158, 0.35),
        0 8px 18px -10px rgba(232, 165, 0, 0.5);
}

.shortcut-icon[data-tone='sky'] {
    background: linear-gradient(135deg, #e3efff 0%, #bddcff 100%);
    border-color: rgba(13, 79, 158, 0.3);
    box-shadow:
        inset 0 -2px 0 rgba(13, 79, 158, 0.18),
        0 8px 18px -10px rgba(13, 79, 158, 0.25);
}

.shortcut-label {
    font-size: 0.8125rem;
    font-weight: 700;
    color: var(--primary-1, #0d4f9e);
    text-align: center;
    letter-spacing: 0.01em;
}
</style>
