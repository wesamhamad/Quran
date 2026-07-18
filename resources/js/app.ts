import { createInertiaApp } from '@inertiajs/vue3';
import { initializeTheme } from '@/composables/useAppearance';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { initializeFlashToast } from '@/lib/flashToast';

// عنوان التبويب بأسلوب موقع جامعة القصيم الرسمي
const appName = 'جامعة القصيم | Qassim University';

createInertiaApp({
    title: (title) => (title ? `${title} | ${appName}` : `المصحف الإلكتروني | ${appName}`),
    layout: (name) => {
        switch (true) {
            case name === 'Welcome':
                return null;
            // صفحات المصحف العامة — بدون قالب لوحة التحكم
            case ['Home', 'Index', 'Search', 'Mushaf', 'Khatmah'].includes(name):
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();

// This will listen for flash toast data from the server...
initializeFlashToast();
