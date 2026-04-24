<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { home } from '@/routes';
import '../../../css/client.css';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div class="auth-shell">
        <div class="auth-backdrop" aria-hidden="true">
            <div class="diamond d-1"></div>
            <div class="diamond d-2"></div>
            <div class="diamond d-3"></div>
        </div>

        <main class="auth-card-wrap">
            <Link :href="home()" class="auth-brand">
                <img src="/images/logo.png" alt="SJC" class="auth-logo" />
            </Link>

            <section class="auth-card">
                <div class="auth-card-accent" aria-hidden="true"></div>
                <header class="auth-card-heading">
                    <h1 class="auth-title">{{ title }}</h1>
                    <p v-if="description" class="auth-description">{{ description }}</p>
                </header>

                <div class="auth-card-body">
                    <slot />
                </div>
            </section>

            <p class="auth-footer">
                © {{ new Date().getFullYear() }} SJC · Vàng · Bạc · Đá quý
            </p>
        </main>
    </div>
</template>

<style scoped>
.auth-shell {
    position: relative;
    min-height: 100svh;
    padding: 1.5rem 1rem;
    display: flex;
    align-items: center;
    justify-content: center;
    background:
        radial-gradient(100% 80% at 0% 0%, rgba(232, 165, 0, 0.12) 0%, transparent 55%),
        radial-gradient(90% 70% at 100% 100%, rgba(13, 79, 158, 0.12) 0%, transparent 55%),
        var(--surface-muted, #f3f7fc);
    overflow: hidden;
    font-family: 'Inter', 'Helvetica Neue', Arial, sans-serif;
    color: var(--text-body, #102a43);
}

.auth-backdrop {
    position: absolute;
    inset: 0;
    pointer-events: none;
    z-index: 0;
}

.auth-backdrop .diamond {
    position: absolute;
    width: 280px;
    height: 280px;
    border: 1.5px solid rgba(13, 79, 158, 0.12);
    transform: rotate(45deg);
    border-radius: 18px;
}

.auth-backdrop .d-1 {
    top: -120px;
    left: -100px;
    border-color: rgba(13, 79, 158, 0.14);
}

.auth-backdrop .d-2 {
    bottom: -140px;
    right: -120px;
    width: 360px;
    height: 360px;
    border-color: rgba(232, 165, 0, 0.2);
}

.auth-backdrop .d-3 {
    top: 40%;
    right: -180px;
    width: 220px;
    height: 220px;
    border-color: rgba(13, 79, 158, 0.16);
}

.auth-card-wrap {
    position: relative;
    z-index: 1;
    width: 100%;
    max-width: 26rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1.25rem;
}

.auth-brand {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    padding: 0.25rem;
    text-decoration: none;
    transition: opacity 150ms ease;
}

.auth-brand:active {
    opacity: 0.85;
}

.auth-logo {
    height: 3.5rem;
    width: auto;
    display: block;
    filter: drop-shadow(0 3px 6px rgba(13, 79, 158, 0.2));
}

.auth-card {
    position: relative;
    width: 100%;
    background: #ffffff;
    border: 1px solid var(--border, #dbe4ed);
    border-radius: 1.25rem;
    padding: 1.75rem 1.5rem 1.5rem;
    box-shadow:
        0 24px 48px -24px rgba(13, 79, 158, 0.28),
        0 4px 14px -6px rgba(13, 79, 158, 0.14);
    overflow: hidden;
}

.auth-card-accent {
    position: absolute;
    inset-inline: 0;
    top: 0;
    height: 4px;
    background: linear-gradient(
        90deg,
        var(--primary-1, #0d4f9e) 0%,
        var(--primary-1, #0d4f9e) 50%,
        var(--primary-2, #e8a500) 50%,
        var(--primary-2, #e8a500) 100%
    );
}

.auth-card-heading {
    margin-bottom: 1.25rem;
    text-align: center;
}

.auth-title {
    color: var(--primary-1, #0d4f9e);
    font-size: 1.375rem;
    font-weight: 800;
    letter-spacing: 0.02em;
    line-height: 1.2;
}

.auth-description {
    margin-top: 0.375rem;
    color: var(--text-muted, #5a6b7e);
    font-size: 0.8125rem;
    line-height: 1.5;
}

.auth-card-body {
    color: var(--text-body, #102a43);
}

.auth-footer {
    font-size: 0.6875rem;
    color: var(--text-muted, #5a6b7e);
    text-align: center;
    letter-spacing: 0.04em;
}
</style>
