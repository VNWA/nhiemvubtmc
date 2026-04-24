<template>
    <div class="space-y-4">
        <div>
            <img src="/images/banner.jpg" alt="logo" class="w-full h-full object-cover">
        </div>
        <div>
            <ul class="grid grid-cols-3 gap-4">
                <li class="flex flex-col items-center justify-center gap-2 p-3">
                    <div class="size-16 bg-teal-500/80 rounded-full flex items-center justify-center p-2">

                        <img src="/images/ruttien.png" alt="product-1" class="size-8 object-cover ">
                    </div>
                    <span class="text-sm text-center font-bold">Rút tiền</span>
                </li>
                <li>
                    <Link
                        href="/sukien"
                        class="flex flex-col items-center justify-center gap-2 p-3 active:opacity-80"
                    >
                    <div class="size-16 bg-amber-100/80 rounded-full flex items-center justify-center p-2">

                        <img src="/images/sukien.png" alt="Sự kiện" class="size-10 object-cover ">
                    </div>
                    <span class="text-sm text-center font-bold">Sự kiện</span>
                    </Link>
                </li>
                <li class="flex flex-col items-center justify-center gap-2 p-3">
                    <div class="size-16 bg-green-500/80 rounded-full flex items-center justify-center p-2">

                        <img src="/images/gioithieu.png" alt="product-1" class="size-8 object-cover ">
                    </div>
                    <span class="text-sm text-center font-bold">Giới thiệu</span>
                </li>
            </ul>
        </div>
    </div>
</template>

<script lang="ts" setup>
import { Link } from '@inertiajs/vue3';
</script>

<style></style>
