import { router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import type { FlashToast } from '@/types/ui';

export function initializeFlashToast(): void {
    router.on('flash', (event) => {
        const flash = (event as CustomEvent).detail?.flash as
            | { toast?: FlashToast; success?: string; error?: string }
            | undefined;

        if (!flash) {
            return;
        }

        if (flash.toast) {
            toast[flash.toast.type](flash.toast.message);
        }

        if (typeof flash.success === 'string' && flash.success !== '') {
            toast.success(flash.success);
        }

        if (typeof flash.error === 'string' && flash.error !== '') {
            toast.error(flash.error);
        }
    });
}
