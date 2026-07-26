<script setup lang="ts">
import AppNav from '@/components/AppNav.vue';
import type { BreadcrumbItem } from '@/types';

defineProps<{
    breadcrumbs?: BreadcrumbItem[];
}>();
</script>

<template>
    <div class="app-shell" dir="rtl">
        <AppNav />
        <main class="app-main">
            <slot />
        </main>
    </div>
</template>

<style scoped>
.app-shell {
    min-height: 100vh;
    background: var(--canvas);
    color: var(--text);
    font-family: 'Segoe UI', Tahoma, sans-serif;
}
.app-main {
    max-width: 62rem;
    margin: 0 auto;
    padding: 1.6rem 1.2rem 3rem;
}
</style>
