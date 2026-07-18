<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useAppearance } from '@/composables/useAppearance';

defineProps<{ active?: string }>();

const { appearance, updateAppearance } = useAppearance();

function toggleTheme() {
    updateAppearance(appearance.value === 'dark' ? 'light' : 'dark');
}
</script>

<template>
    <nav class="appnav" dir="rtl">
        <Link href="/" class="brand">
            <img src="/storage/Images/logo/qu-logo-v4.webp" alt="جامعة القصيم" class="logo" />
            <span class="brand-text">
                <strong>المصحف الإلكتروني</strong>
                <small>جامعة القصيم</small>
            </span>
        </Link>

        <div class="right">
            <div class="links">
                <Link href="/surahs" :class="{ on: active === 'surahs' }">فهرس السور</Link>
                <Link href="/mushaf/1" :class="{ on: active === 'mushaf' }">المصحف</Link>
                <Link href="/khatmah" :class="{ on: active === 'khatmah' }">ختمة</Link>
                <Link href="/search" :class="{ on: active === 'search' }">بحث</Link>
            </div>
            <button class="theme" @click="toggleTheme" aria-label="تبديل الوضع الليلي">
                <!-- شمس (للتبديل إلى النهاري) -->
                <svg v-if="appearance === 'dark'" class="ticon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="4" />
                    <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41" />
                </svg>
                <!-- قمر (للتبديل إلى الليلي) -->
                <svg v-else class="ticon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" />
                </svg>
            </button>
        </div>
    </nav>
</template>

<style scoped>
.appnav {
    position: sticky; top: 0; z-index: 50;
    display: flex; align-items: center; justify-content: space-between; gap: 1rem;
    padding: 0.7rem 1.4rem;
    background: var(--glass);
    backdrop-filter: saturate(180%) blur(16px);
    -webkit-backdrop-filter: saturate(180%) blur(16px);
    border-bottom: 1px solid var(--glass-brd);
    font-family: 'Segoe UI', Tahoma, sans-serif;
    color: var(--text);
}
.brand { display: flex; align-items: center; gap: 0.65rem; text-decoration: none; color: inherit; min-width: 0; }
.logo { height: 54px; width: auto; max-width: 150px; object-fit: contain; flex: none; }
.brand-text { display: flex; flex-direction: column; line-height: 1.15; }
.brand-text strong { font-size: 1rem; font-weight: 700; letter-spacing: -0.01em; }
.brand-text small { font-size: 0.7rem; color: var(--text-muted); }

.right { display: flex; align-items: center; gap: 0.5rem; }
.links { display: flex; gap: 0.15rem; }
.links a {
    color: var(--text-muted); text-decoration: none;
    padding: 0.45rem 0.95rem; border-radius: 999px; font-size: 0.9rem; font-weight: 500;
    white-space: nowrap; transition: all 0.15s;
}
.links a:hover { color: var(--text); background: var(--surface-2); }
.links a.on { background: var(--brand-soft); color: var(--brand); font-weight: 600; }

.theme {
    width: 38px; height: 38px; border-radius: 12px; border: 1px solid var(--border);
    background: var(--surface); color: var(--text); cursor: pointer;
    display: grid; place-items: center; transition: background 0.15s, color 0.15s;
}
.theme:hover { background: var(--surface-2); color: var(--brand); }
.ticon { width: 19px; height: 19px; }

@media (max-width: 560px) {
    .appnav { padding: 0.55rem 0.8rem; gap: 0.5rem; }
    .logo { height: 42px; max-width: 110px; }
    .brand-text strong { font-size: 0.85rem; }
    .brand-text small { font-size: 0.62rem; }
    .links a { padding: 0.4rem 0.6rem; font-size: 0.8rem; }
    .theme { width: 34px; height: 34px; }
}
@media (max-width: 400px) {
    .brand-text { display: none; }
    .links a { padding: 0.35rem 0.5rem; font-size: 0.75rem; }
}
</style>
