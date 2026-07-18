<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import AppNav from '@/components/AppNav.vue';

const props = defineProps<{ totalPages: number }>();

const days = ref(30);
const pagesRead = ref(0);
const lastPage = ref<number | null>(null);

const KEY = 'khatmah-plan';
onMounted(() => {
    try {
        const s = JSON.parse(localStorage.getItem(KEY) || '{}');
        if (s.days) days.value = s.days;
        if (s.pagesRead) pagesRead.value = s.pagesRead;
        const lp = Number(localStorage.getItem('quran-last-page'));
        if (lp > 0) lastPage.value = lp;
    } catch { /* تجاهل */ }
});
function resume() { router.visit(`/mushaf/${lastPage.value}`); }
function save() {
    localStorage.setItem(KEY, JSON.stringify({ days: days.value, pagesRead: pagesRead.value }));
}

const dailyPages = computed(() => Math.ceil(props.totalPages / days.value));
const todayStart = computed(() => Math.min(props.totalPages, pagesRead.value + 1));
const todayEnd = computed(() => Math.min(props.totalPages, pagesRead.value + dailyPages.value));
const percent = computed(() => Math.round((pagesRead.value / props.totalPages) * 100));
const daysLeft = computed(() => Math.ceil((props.totalPages - pagesRead.value) / dailyPages.value));
const done = computed(() => pagesRead.value >= props.totalPages);

function setDays(n: number) { days.value = n; save(); }
function startToday() { router.visit(`/mushaf/${todayStart.value}`); }
function completeToday() { pagesRead.value = todayEnd.value; save(); }
function undo() { pagesRead.value = Math.max(0, pagesRead.value - dailyPages.value); save(); }
function reset() { pagesRead.value = 0; save(); }
</script>

<template>
    <Head title="خطة الختمة" />
    <div class="khatmah" dir="rtl">
        <AppNav active="khatmah" />

        <div class="wrap">
            <h1>خطة الختمة</h1>
            <p class="lead">اختر مدة الختمة وتابع وردك اليومي — يُحفظ تقدّمك تلقائياً على جهازك.</p>

            <!-- اختيار الخطة -->
            <div class="plans">
                <button v-for="n in [15, 30, 60]" :key="n" class="plan" :class="{ on: days === n }" @click="setDays(n)">
                    {{ n }} يوماً
                </button>
                <label class="plan custom">
                    <input type="number" min="1" max="365" :value="days" @input="setDays(Math.max(1, +($event.target as HTMLInputElement).value || 1))" />
                    <span>مخصّص</span>
                </label>
            </div>

            <!-- التقدّم -->
            <div class="progress-card">
                <div class="ring" :style="{ background: `conic-gradient(var(--brand) ${percent * 3.6}deg, var(--surface-2) 0deg)` }">
                    <div class="ring-in"><b>{{ percent }}%</b><span>مكتمل</span></div>
                </div>
                <div class="pstats">
                    <div><b>{{ pagesRead }}</b><span>صفحة مقروءة</span></div>
                    <div><b>{{ totalPages - pagesRead }}</b><span>صفحة متبقية</span></div>
                    <div><b>{{ done ? 0 : daysLeft }}</b><span>يوماً متبقياً</span></div>
                    <div><b>{{ dailyPages }}</b><span>صفحة / يوم</span></div>
                </div>
            </div>

            <!-- ورد اليوم -->
            <div v-if="!done" class="today">
                <div class="tinfo">
                    <span class="tlabel">وردك اليوم</span>
                    <b>الصفحات {{ todayStart }} — {{ todayEnd }}</b>
                </div>
                <div class="tactions">
                    <button class="btn primary" @click="startToday">ابدأ الورد</button>
                    <button v-if="lastPage" class="btn" @click="resume">تابع من صفحة {{ lastPage }}</button>
                    <button class="btn" @click="completeToday">أنهيت وردي ✓</button>
                </div>
            </div>
            <div v-else class="done-card">
                🎉 تمّت الختمة — تقبّل الله. <button class="btn" @click="reset">ابدأ ختمة جديدة</button>
            </div>

            <div class="footer-actions">
                <button v-if="pagesRead > 0 && !done" class="link" @click="undo">تراجع عن آخر ورد</button>
                <button v-if="pagesRead > 0" class="link danger" @click="reset">إعادة تعيين</button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.khatmah { min-height: 100vh; background: var(--canvas); color: var(--text); font-family: 'Segoe UI', Tahoma, sans-serif; }
.wrap { max-width: 720px; margin: 0 auto; padding: 2rem 1.2rem 3rem; }
h1 { font-size: 1.7rem; font-weight: 800; margin: 0 0 0.4rem; letter-spacing: -0.01em; }
.lead { color: var(--text-muted); margin: 0 0 1.6rem; }

.plans { display: flex; gap: 0.6rem; flex-wrap: wrap; margin-bottom: 1.6rem; }
.plan {
    padding: 0.7rem 1.3rem; border-radius: 14px; border: 1px solid var(--border);
    background: var(--surface); color: var(--text); font-family: inherit; font-size: 0.95rem; font-weight: 600; cursor: pointer;
    transition: all 0.15s;
}
.plan:hover { border-color: var(--brand-200); }
.plan.on { background: var(--brand-soft); color: var(--brand); border-color: var(--brand-200); }
.plan.custom { display: flex; align-items: center; gap: 0.4rem; }
.plan.custom input { width: 48px; border: none; background: transparent; color: inherit; font: inherit; text-align: center; outline: none; }

.progress-card {
    display: flex; align-items: center; gap: 1.8rem; flex-wrap: wrap;
    background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius);
    padding: 1.5rem; box-shadow: var(--shadow-sm); margin-bottom: 1.2rem;
}
.ring { width: 120px; height: 120px; border-radius: 50%; display: grid; place-items: center; flex: none; }
.ring-in { width: 92px; height: 92px; border-radius: 50%; background: var(--surface); display: grid; place-items: center; text-align: center; }
.ring-in b { font-size: 1.5rem; font-weight: 800; color: var(--brand); }
.ring-in span { font-size: 0.72rem; color: var(--text-muted); }
.pstats { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem 1.6rem; flex: 1; }
.pstats > div { display: flex; flex-direction: column; }
.pstats b { font-size: 1.3rem; font-weight: 800; }
.pstats span { font-size: 0.78rem; color: var(--text-muted); }

.today {
    display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap;
    background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 1.4rem; box-shadow: var(--shadow-sm);
}
.tlabel { font-size: 0.8rem; color: var(--text-muted); display: block; }
.tinfo b { font-size: 1.25rem; }
.tactions { display: flex; gap: 0.6rem; }
.btn { padding: 0.65rem 1.3rem; border-radius: 12px; border: 1px solid var(--border); background: var(--surface); color: var(--text); font-family: inherit; font-weight: 600; cursor: pointer; transition: transform 0.12s; }
.btn:hover { transform: translateY(-1px); }
.btn.primary { background: var(--brand); color: #fff; border-color: var(--brand); }
.done-card { background: var(--brand-soft); color: var(--brand); border-radius: var(--radius); padding: 1.6rem; text-align: center; font-size: 1.1rem; font-weight: 700; }
.done-card .btn { margin-top: 0.8rem; }
.footer-actions { display: flex; gap: 1rem; justify-content: center; margin-top: 1.4rem; }
.link { background: none; border: none; color: var(--text-muted); cursor: pointer; font-family: inherit; font-size: 0.85rem; }
.link:hover { color: var(--text); }
.link.danger:hover { color: #c0392b; }

@media (max-width: 520px) {
    .progress-card { justify-content: center; }
    .today { flex-direction: column; align-items: stretch; }
    .tactions { justify-content: stretch; }
    .tactions .btn { flex: 1; }
}
</style>
