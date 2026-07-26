<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import AppNav from '@/components/AppNav.vue';
import Icon from '@/components/Icon.vue';
import type { ReviewPlan } from '@/lib/reviewSchedule';
import {
    computeNextDue,
    downloadIcs,
    duePlans,
    everyLabel,
    formatDue,
    loadPlans,
    markDone,
    newId,
    savePlans,
    snooze,
} from '@/lib/reviewSchedule';

interface WordT {
    code: string;
    type: string;
    verse_key: string;
    pos: number;
}
interface LineT {
    line_number: number;
    start_surah: {
        id: number;
        name_arabic: string;
        name_uthmani: string;
        bismillah_pre: boolean;
    } | null;
    words: WordT[];
}
interface SurahRef {
    id: number;
    name_arabic: string;
}
interface AudioT {
    verse_key: string;
    url: string;
    segments: number[][] | null;
}
interface ReciterT {
    id: number;
    name: string;
}
interface LangT {
    code: string;
    native: string;
    name: string;
    dir: string;
}
interface RiwT {
    slug: string;
    name: string;
    is_hafs: boolean;
    font: string;
    pages: number;
}

const props = defineProps<{
    page: number;
    prev: number | null;
    next: number | null;
    juz: number | null;
    surahs: SurahRef[];
    lines: LineT[];
    reciter: string | null;
    reciters: ReciterT[];
    reciterId: number | null;
    audio: AudioT[];
    translationLangs: LangT[];
    riwayat: RiwT[];
    // آيات هذه الصفحة التي تحمل سبب نزول / متشابهات (بترتيب القراءة)
    asbabKeys: string[];
    similarKeys: string[];
}>();

const pageFont = `p${props.page}`;
// صفحتا الفاتحة وبداية البقرة تُعرضان موسّطتين (كالمصحف) لتفادي الفراغات الكبيرة
const centeredPage = props.page <= 2;

// اسم الجزء بالحروف (كمصحف المدينة)
const JUZ_ORDINALS = [
    '',
    'الأول',
    'الثاني',
    'الثالث',
    'الرابع',
    'الخامس',
    'السادس',
    'السابع',
    'الثامن',
    'التاسع',
    'العاشر',
    'الحادي عشر',
    'الثاني عشر',
    'الثالث عشر',
    'الرابع عشر',
    'الخامس عشر',
    'السادس عشر',
    'السابع عشر',
    'الثامن عشر',
    'التاسع عشر',
    'العشرون',
    'الحادي والعشرون',
    'الثاني والعشرون',
    'الثالث والعشرون',
    'الرابع والعشرون',
    'الخامس والعشرون',
    'السادس والعشرون',
    'السابع والعشرون',
    'الثامن والعشرون',
    'التاسع والعشرون',
    'الثلاثون',
];
function juzLabel(n: number | null): string {
    if (!n) return '';
    return `الجزء ${JUZ_ORDINALS[n] ?? n}`;
}
function pageArabic(n: number): string {
    return String(n).replace(/[0-9]/g, (d) => '٠١٢٣٤٥٦٧٨٩'[+d]);
}

/* ==================================================================
   نمط المُعلّم (للأطفال وحلقات التحفيظ)
   يجمع: تكرار الآيات · دورة (اقرأ/استمع/احفظ/راجع) · مراجعة لحظية
          بالمؤقّت · جدولة وتنبيهات دورية. كل الإعدادات محليّة (بلا خادم).
   ================================================================== */
const teacherMode = ref(false);
const panelTab = ref<'cycle' | 'repeat' | 'flash' | 'schedule'>('cycle');
const revealed = ref<Set<string>>(new Set()); // آيات كُشفت يدوياً أثناء الإخفاء

/* ---------- آيات الصفحة + التسميات ---------- */
const surahNameById = computed<Record<number, string>>(() => {
    const m: Record<number, string> = {};
    for (const s of props.surahs) m[s.id] = s.name_arabic;
    return m;
});
const pageVerses = computed<string[]>(() => {
    const seen = new Set<string>();
    const out: string[] = [];
    for (const line of props.lines) {
        for (const w of line.words) {
            if (w.verse_key && !seen.has(w.verse_key)) {
                seen.add(w.verse_key);
                out.push(w.verse_key);
            }
        }
    }
    return out;
});
function verseLabel(key: string): string {
    const [s, a] = key.split(':');
    const name = surahNameById.value[+s] ?? '';
    return `${name} ${pageArabic(+a)}`.trim();
}

/* ---------- النطاق المشترك (الصفحة كاملة أو مقطع محدّد) ---------- */
type Scope = 'page' | 'range';
const scope = ref<Scope>('page');
const rangeFrom = ref<string>('');
const rangeTo = ref<string>('');
watch(
    pageVerses,
    (vs) => {
        if (!vs.length) return;
        if (!rangeFrom.value || !vs.includes(rangeFrom.value))
            rangeFrom.value = vs[0];
        if (!rangeTo.value || !vs.includes(rangeTo.value))
            rangeTo.value = vs[vs.length - 1];
    },
    { immediate: true },
);
const scopeKeys = computed<string[]>(() => {
    const vs = pageVerses.value;
    if (scope.value === 'page') return vs;
    let i = vs.indexOf(rangeFrom.value);
    let j = vs.indexOf(rangeTo.value);
    if (i < 0) i = 0;
    if (j < 0) j = vs.length - 1;
    if (i > j) [i, j] = [j, i];
    return vs.slice(i, j + 1);
});
const scopeSet = computed(() => new Set(scopeKeys.value));

/* ---------- (1) تكرار الآيات ---------- */
const INF = 0; // 0 = ما لا نهاية
const REPEAT_OPTS = [1, 3, 5, 7, 10, INF];
const repeatEach = ref(3); // كم مرة تُكرّر كل آية
const loopScope = ref(false); // تكرار المقطع كاملاً
const SCOPE_LOOP_OPTS = [INF, 2, 3, 5];
const scopeLoops = ref<number>(INF);

/* ---------- (2) الدورة المتكاملة ---------- */
const CYCLE_STEPS = [
    {
        key: 'read',
        title: 'اقرأ',
        icon: 'book-open',
        hint: 'اقرأ آيات المقطع بتمعّن عدة مرات قبل الحفظ.',
    },
    {
        key: 'listen',
        title: 'استمع',
        icon: 'headphones',
        hint: 'استمع للتلاوة مع التكرار وتابع الكلمات المضيئة.',
    },
    {
        key: 'memorize',
        title: 'احفظ',
        icon: 'eye-off',
        hint: 'الآيات مخفية — ردّدها من حفظك، واضغط أي كلمة لكشف آيتها.',
    },
    {
        key: 'review',
        title: 'راجع',
        icon: 'circle-check',
        hint: 'اختبر نفسك: اكشف للتأكّد، ثم جدوِل مراجعتها الدورية.',
    },
] as const;
const cycleActive = ref(false);
const cycleStep = ref(0);
const cycleStepKey = computed(() => CYCLE_STEPS[cycleStep.value].key);
function scrollToScope() {
    const k = scopeKeys.value[0];
    if (k) scrollToVerse(k);
}
function startCycle() {
    teacherMode.value = true;
    cycleActive.value = true;
    cycleStep.value = 0;
    revealed.value = new Set();
    stopFlash();
    manualHide.value = false;
    scrollToScope();
}
function exitCycle() {
    cycleActive.value = false;
    stop();
}
function cycleGo(step: number) {
    if (step < 0 || step >= CYCLE_STEPS.length) return;
    cycleStep.value = step;
    revealed.value = new Set();
    const key = CYCLE_STEPS[step].key;
    if (key === 'listen') playScope();
    else stop();
    scrollToScope();
}
function cycleNext() {
    cycleGo(cycleStep.value + 1);
}
function cyclePrev() {
    cycleGo(cycleStep.value - 1);
}

/* ---------- (3) المراجعة اللحظية (مؤقّت إخفاء الآيات) ---------- */
const FLASH_OPTS = [3, 5, 8, 10, 15];
const flashActive = ref(false);
const flashHidden = ref(false);
const flashSeconds = ref(5); // مدة إظهار الآيات قبل إخفائها
const flashGap = ref(4); // مهلة الاسترجاع أثناء الإخفاء (للتكرار التلقائي)
const flashAuto = ref(true); // تكرار الدورة تلقائياً
const flashCountdown = ref(0);
let flashTimer: number | null = null;
let flashTick: number | null = null;
function clearFlashTimers() {
    if (flashTimer !== null) {
        clearTimeout(flashTimer);
        flashTimer = null;
    }
    if (flashTick !== null) {
        clearInterval(flashTick);
        flashTick = null;
    }
}
function startFlashCountdown(sec: number, done: () => void) {
    clearFlashTimers();
    flashCountdown.value = sec;
    flashTick = window.setInterval(() => {
        flashCountdown.value = Math.max(0, flashCountdown.value - 1);
    }, 1000);
    flashTimer = window.setTimeout(() => {
        clearFlashTimers();
        flashCountdown.value = 0;
        done();
    }, sec * 1000);
}
function flashReveal() {
    flashHidden.value = false;
    revealed.value = new Set();
    scrollToScope();
    startFlashCountdown(flashSeconds.value, () => {
        flashHidden.value = true;
        if (flashAuto.value)
            startFlashCountdown(flashGap.value, () => flashReveal());
    });
}
function startFlash() {
    teacherMode.value = true;
    flashActive.value = true;
    cycleActive.value = false;
    manualHide.value = false;
    stop();
    flashReveal();
}
function stopFlash() {
    flashActive.value = false;
    flashHidden.value = false;
    flashCountdown.value = 0;
    clearFlashTimers();
}
function flashRevealNow() {
    // إعادة تشغيل دورة الإظهار (تعمل في الوضعين اليدوي والتلقائي)
    flashReveal();
}

/* ---------- إخفاء موحّد للآيات + الكشف بالضغط ---------- */
const manualHide = ref(false); // زر إخفاء سريع
const EMPTY_SET = new Set<string>();
const hiddenKeys = computed<Set<string>>(() => {
    if (flashActive.value && flashHidden.value) return scopeSet.value;
    if (
        cycleActive.value &&
        (cycleStepKey.value === 'memorize' || cycleStepKey.value === 'review')
    )
        return scopeSet.value;
    if (manualHide.value) return scopeSet.value;
    return EMPTY_SET;
});
const hideActive = computed(() => hiddenKeys.value.size > 0);
function isWordHidden(w: WordT): boolean {
    return (
        w.type !== 'end' &&
        hiddenKeys.value.has(w.verse_key) &&
        !revealed.value.has(w.verse_key)
    );
}
function revealVerse(key: string) {
    const s = new Set(revealed.value);
    s.has(key) ? s.delete(key) : s.add(key);
    revealed.value = s;
}
function toggleTeacher() {
    teacherMode.value = !teacherMode.value;
    if (!teacherMode.value) {
        exitCycle();
        stopFlash();
        manualHide.value = false;
    }
    persistTeacher();
}

/* ---------- (4) الجدولة والتنبيهات ---------- */
const plans = ref<ReviewPlan[]>([]);
const dueList = ref<ReviewPlan[]>([]);
const EVERY_OPTS = [1, 2, 3, 7];
const schedEveryDays = ref(1);
const schedTime = ref('17:00');
let dueTimer: number | null = null;

function checkDue() {
    dueList.value = duePlans(plans.value);
}
function maybeNotifyPermission() {
    try {
        if ('Notification' in window && Notification.permission === 'default')
            void Notification.requestPermission();
    } catch {
        /* */
    }
}
function fireDueNotifications() {
    try {
        if (
            !('Notification' in window) ||
            Notification.permission !== 'granted'
        )
            return;
        for (const p of dueList.value) {
            new Notification('حان وقت مراجعة الحفظ', {
                body: p.label,
                tag: p.id,
                icon: '/qu-icon-192.png',
            });
        }
    } catch {
        /* */
    }
}
function addPlan() {
    const ks = scopeKeys.value;
    const label =
        scope.value === 'range' && ks.length > 1
            ? `مراجعة ${verseLabel(ks[0])} — ${verseLabel(ks[ks.length - 1])}`
            : `مراجعة صفحة ${pageArabic(props.page)}${props.surahs.length ? ' — سورة ' + props.surahs[0].name_arabic : ''}`;
    const plan: ReviewPlan = {
        id: newId(),
        label,
        page: props.page,
        everyDays: schedEveryDays.value,
        time: schedTime.value,
        createdAt: Date.now(),
        nextDue: computeNextDue(schedEveryDays.value, schedTime.value),
        doneCount: 0,
    };
    const next = [...plans.value, plan];
    savePlans(next);
    plans.value = next;
    checkDue();
    maybeNotifyPermission();
}
function removePlan(id: string) {
    const next = plans.value.filter((p) => p.id !== id);
    savePlans(next);
    plans.value = next;
    checkDue();
}
function completePlan(p: ReviewPlan) {
    const upd = markDone(p);
    const next = plans.value.map((x) => (x.id === p.id ? upd : x));
    savePlans(next);
    plans.value = next;
    checkDue();
}
function snoozePlan(p: ReviewPlan) {
    const upd = snooze(p, 1);
    const next = plans.value.map((x) => (x.id === p.id ? upd : x));
    savePlans(next);
    plans.value = next;
    checkDue();
}
function openPlanPage(p: ReviewPlan) {
    if (p.page !== props.page) router.visit(`/mushaf/${p.page}`);
    else scrollToScope();
}

/* ---------- حفظ/استعادة إعدادات نمط المُعلّم ---------- */
function persistTeacher() {
    try {
        localStorage.setItem(
            'quran-teacher',
            JSON.stringify({
                on: teacherMode.value,
                repeatEach: repeatEach.value,
                loopScope: loopScope.value,
                scopeLoops: scopeLoops.value,
                flashSeconds: flashSeconds.value,
                flashGap: flashGap.value,
                flashAuto: flashAuto.value,
            }),
        );
    } catch {
        /* */
    }
}
watch(
    [repeatEach, loopScope, scopeLoops, flashSeconds, flashGap, flashAuto],
    persistTeacher,
);

/* ---------- لوحة الترجمة (يمين) — متعددة اللغات ---------- */
interface TransVerse {
    verse_key: string;
    number_in_surah: number;
    surah_name: string;
    surah_en: string;
    text: string;
}
const transLang = ref<string>('none'); // 'none' أو رمز اللغة (en, fr, ur…)
const transVerses = ref<TransVerse[]>([]);
const transLoading = ref(false);
const transHead = ref(''); // اسم الطبعة (من الاستجابة)
const transDir = ref<'ltr' | 'rtl'>('ltr');
const transMeta = computed(
    () =>
        props.translationLangs.find((l) => l.code === transLang.value) ?? null,
);

async function loadTranslations(lang: string) {
    transLoading.value = true;
    transDir.value = (transMeta.value?.dir as 'ltr' | 'rtl') ?? 'ltr';
    transHead.value = transMeta.value?.name ?? '';
    try {
        const res = await fetch(
            `/api/mushaf/${props.page}/translations?lang=${lang}`,
            {
                headers: { Accept: 'application/json' },
            },
        );
        const data = await res.json();
        transVerses.value = data.verses ?? [];
        if (data.name) transHead.value = data.name;
        if (data.dir) transDir.value = data.dir;
    } finally {
        transLoading.value = false;
    }
}
function setLang(lang: string) {
    transLang.value = lang;
    try {
        localStorage.setItem('quran-trans-lang', lang);
    } catch {
        /* */
    }
    if (lang === 'none') {
        transVerses.value = [];
        return;
    }
    loadTranslations(lang);
}
function focusVerse(key: string) {
    selectedVerse.value = key;
    scrollToVerse(key);
}

/* ---------- وضع «القراءة بالتجويد» + مفتاح الألوان ---------- */
interface TajweedVerse {
    verse_key: string;
    number_in_surah: number;
    html: string;
    first_in_surah: boolean;
    surah_name: string | null;
    bismillah_pre: boolean;
}
// تسمية عربية + لون لكل قاعدة (مطابق لنظام ألوان مصاحف التجويد المعتمدة)
const TAJWEED_RULES: { label: string; color: string }[] = [
    { label: 'مدّ لازم (٦ حركات)', color: '#000EBC' },
    { label: 'مدّ واجب متّصل', color: '#2144C1' },
    { label: 'مدّ جائز منفصل', color: '#4050FF' },
    { label: 'مدّ طبيعي (حركتان)', color: '#537FFF' },
    { label: 'قلقلة', color: '#DD0008' },
    { label: 'غُنّة', color: '#FF7E1E' },
    { label: 'إخفاء', color: '#9400A8' },
    { label: 'إدغام بغُنّة', color: '#169200' },
    { label: 'إقلاب', color: '#26BFFD' },
    { label: 'إدغام تام / حرف لا يُلفظ', color: '#AAAAAA' },
];
const tajweedMode = ref(false);
const tajweedVerses = ref<TajweedVerse[]>([]);
const tajweedLoading = ref(false);
const tajweedLegendOpen = ref(true);

async function loadTajweed() {
    tajweedLoading.value = true;
    try {
        const res = await fetch(`/api/mushaf/${props.page}/tajweed`, {
            headers: { Accept: 'application/json' },
        });
        const data = await res.json();
        tajweedVerses.value = data.verses ?? [];
    } finally {
        tajweedLoading.value = false;
    }
}
function toggleTajweedMode() {
    tajweedMode.value = !tajweedMode.value;
    try {
        localStorage.setItem(
            'quran-tajweed-mode',
            tajweedMode.value ? '1' : '0',
        );
    } catch {
        /* */
    }
    if (tajweedMode.value && !tajweedVerses.value.length) loadTajweed();
}
function toggleVerseTajweed() {
    showVerseTajweed.value = !showVerseTajweed.value;
    try {
        localStorage.setItem(
            'quran-verse-tajweed',
            showVerseTajweed.value ? '1' : '0',
        );
    } catch {
        /* */
    }
}

/* ---------- الانتقال إلى آية متشابهة (نفس الصفحة أو صفحة أخرى) ---------- */
function goToVerse(key: string, page: number) {
    if (page === props.page) {
        openVerse(key);
        scrollToVerse(key);
    } else {
        router.visit(
            `/mushaf/${page}?v=${key}${props.reciterId ? `&reciter=${props.reciterId}` : ''}`,
        );
    }
}

/* ---------- قائمة الإعدادات (القارئ + الترجمة + حجم النص) ---------- */
const settingsOpen = ref(false);
function onLangSelect(e: Event) {
    setLang((e.target as HTMLSelectElement).value);
}

/* ---------- دليل علامات الوقف والابتداء (مصحف المدينة) ---------- */
const waqfGuideOpen = ref(false);
const WAQF_SIGNS: { sign: string; name: string; desc: string }[] = [
    {
        sign: 'مـ',
        name: 'الوقف اللازم',
        desc: 'يجب الوقف، لأنّ وصله قد يُوهم معنًى غير مُراد.',
    },
    {
        sign: 'لا',
        name: 'الوقف الممنوع',
        desc: 'لا يُوقف عليه؛ فإن اضطُررت للوقف فارجع وابدأ بما قبله.',
    },
    {
        sign: 'ج',
        name: 'الوقف الجائز',
        desc: 'يجوز الوقف والوصل بدرجةٍ متساوية.',
    },
    {
        sign: 'صلى',
        name: 'الوصل أَولى',
        desc: 'يجوز الوقف، والوصل (الاستمرار في القراءة) أَولى.',
    },
    { sign: 'قلى', name: 'الوقف أَولى', desc: 'يجوز الوصل، والوقف أَولى.' },
    {
        sign: '∴ … ∴',
        name: 'تعانُق الوقف',
        desc: 'موضعان متلازمان: إذا وقفت على أحدهما فلا تقف على الآخر.',
    },
    {
        sign: 'س',
        name: 'السكتة',
        desc: 'وقفةٌ يسيرة بلا تنفّس، ثم تُتابَع القراءة.',
    },
];

/* ---------- وضع الرواية (غير حفص) — نصّ يونيكود بخط KFGQPC الرسمي ---------- */
interface RiwVerse {
    sura_no: number;
    aya_no: number;
    text: string;
    is_start: boolean;
    sura_name: string | null;
    bismillah: boolean;
}
interface RiwData {
    name: string;
    font: string;
    page: number;
    pages: number;
    prev: number | null;
    next: number | null;
    jozz: number | null;
    verses: RiwVerse[];
}
const riwayah = ref('hafs');
const isRiwayah = computed(() => riwayah.value !== 'hafs');
const riwData = ref<RiwData | null>(null);
const riwLoading = ref(false);
const riwPage = ref(1);

async function loadRiwayah(page: number) {
    if (!isRiwayah.value) return;
    riwLoading.value = true;
    try {
        const res = await fetch(
            `/api/mushaf/${page}/riwayah?r=${riwayah.value}`,
            {
                headers: { Accept: 'application/json' },
            },
        );
        const d = await res.json();
        riwData.value = d;
        riwPage.value = d.page;
    } finally {
        riwLoading.value = false;
    }
}
function setRiwayah(slug: string) {
    riwayah.value = slug;
    try {
        localStorage.setItem('quran-riwayah', slug);
    } catch {
        /* */
    }
    if (slug === 'hafs') {
        riwData.value = null;
        return;
    }
    stop(); // الصوت والمزايا التفاعلية خاصة بحفص
    exitCycle();
    stopFlash();
    manualHide.value = false;
    loadRiwayah(riwPage.value || 1);
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
function onRiwSelect(e: Event) {
    setRiwayah((e.target as HTMLSelectElement).value);
}
function riwGo(target: number | null) {
    if (!target) return;
    loadRiwayah(target);
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
function juzArabicOrdinal(n: number | null): string {
    return n ? juzLabel(n) : '';
}
// إعادة تحميل الترجمة عند تغيّر الصفحة (Inertia يعيد استخدام نفس المكوّن)
watch(
    () => props.page,
    () => {
        if (transLang.value !== 'none') loadTranslations(transLang.value);
        if (tajweedMode.value) loadTajweed();
        maybeAutoplay();
    },
);

/* ---------- التلاوة المتواصلة عبر الصفحات ---------- */
// إن وصلنا لهذه الصفحة نتيجة إكمال تلقائي، ابدأ تلاوتها فوراً
function maybeAutoplay() {
    try {
        if (sessionStorage.getItem('quran-autoplay') === '1') {
            sessionStorage.removeItem('quran-autoplay');
            if (props.audio.length) startAt(playBounds().start);
        }
    } catch {
        /* */
    }
}

/* ---------- حجم النص (لكبار السن) ---------- */
const MIN_SCALE = 0.9,
    MAX_SCALE = 1.8;
const fontScale = ref(1);
function persistScale() {
    try {
        localStorage.setItem('quran-font-scale', String(fontScale.value));
    } catch {
        /* */
    }
}
function zoomIn() {
    fontScale.value = Math.min(
        MAX_SCALE,
        Math.round((fontScale.value + 0.1) * 10) / 10,
    );
    persistScale();
}
function zoomOut() {
    fontScale.value = Math.max(
        MIN_SCALE,
        Math.round((fontScale.value - 0.1) * 10) / 10,
    );
    persistScale();
}

/* ---------- التنقّل ---------- */
function go(target: number | null) {
    if (target)
        router.visit(
            `/mushaf/${target}${props.reciterId ? `?reciter=${props.reciterId}` : ''}`,
        );
}
// تنقّل موحّد يراعي وضع الرواية (لكل رواية ترقيم صفحاتها)
function navPrev() {
    if (isRiwayah.value) riwGo(riwData.value?.prev ?? null);
    else go(props.prev);
}
function navNext() {
    if (isRiwayah.value) riwGo(riwData.value?.next ?? null);
    else go(props.next);
}
function changeReciter(e: Event) {
    const id = (e.target as HTMLSelectElement).value;
    stop();
    router.visit(`/mushaf/${props.page}?reciter=${id}`, {
        preserveScroll: true,
    });
}

/* ---------- التمرير باللمس (الجوال) ---------- */
let touchX = 0,
    touchY = 0;
function onTouchStart(e: TouchEvent) {
    touchX = e.changedTouches[0].clientX;
    touchY = e.changedTouches[0].clientY;
}
function onTouchEnd(e: TouchEvent) {
    const dx = e.changedTouches[0].clientX - touchX;
    const dy = e.changedTouches[0].clientY - touchY;
    // تمرير أفقي واضح فقط (لا يتعارض مع التمرير العمودي أو الضغط على كلمة)
    if (Math.abs(dx) < 55 || Math.abs(dx) < Math.abs(dy) * 1.4) return;
    if (dx > 0)
        navNext(); // سحب لليمين → الصفحة التالية
    else navPrev(); // سحب لليسار → الصفحة السابقة
}
function onKey(e: KeyboardEvent) {
    if ((e.target as HTMLElement)?.tagName === 'INPUT') return;
    if (e.key === 'ArrowRight') navPrev();
    if (e.key === 'ArrowLeft') navNext();
    if (e.key === ' ' && !isRiwayah.value) {
        e.preventDefault();
        toggle();
    }
}

/* ---------- الصوت + التظليل ---------- */
const playing = ref(false);
const activeVerse = ref<string | null>(null);
const activeWord = ref<number | null>(null); // رقم الكلمة الجاري تلاوتها (توقيت)
const curRepeat = ref(1); // تكرار الآية الحالي (لعرضه)
const curLoop = ref(1); // دورة المقطع الحالية (لعرضها)
let audioEl: HTMLAudioElement | null = null; // العنصر الجاري تشغيله
let preloadEl: HTMLAudioElement | null = null; // العنصر التالي مُحمَّل مسبقاً
let currentSegments: number[][] | null = null; // مقاطع توقيت الآية الحالية
let idx = 0;

// فهرس الآية في مصفوفة الصوت
function audioIndexOf(key: string): number {
    return props.audio.findIndex((a) => a.verse_key === key);
}
// حدود التشغيل بدلالة فهارس الصوت وفق النطاق (في نمط المعلّم مع نطاق محدّد)
function playBounds(): { start: number; end: number } {
    if (teacherMode.value && scope.value === 'range') {
        let start = -1,
            end = -1;
        for (const k of scopeKeys.value) {
            const i = audioIndexOf(k);
            if (i >= 0) {
                if (start < 0) start = i;
                end = i;
            }
        }
        if (start >= 0) return { start, end };
    }
    return { start: 0, end: props.audio.length - 1 };
}
// إعادة تشغيل الآية الحالية من بدايتها بلا إعادة تحميل (للتكرار)
function replayCurrent() {
    if (audioEl) {
        activeWord.value = null;
        audioEl.currentTime = 0;
        void audioEl.play();
        playing.value = true;
    } else playAt(idx);
}
function gotoNextPage() {
    if (props.next) {
        try {
            sessionStorage.setItem('quran-autoplay', '1');
        } catch {
            /* */
        }
        router.visit(
            `/mushaf/${props.next}${props.reciterId ? `?reciter=${props.reciterId}` : ''}`,
        );
    } else stop();
}
// ماذا بعد انتهاء تلاوة الآية؟ (تكرار / انتقال / دوران المقطع / صفحة تالية)
function advance() {
    if (!teacherMode.value) {
        playAt(idx + 1);
        return;
    }
    if (repeatEach.value === INF) {
        replayCurrent();
        return;
    }
    if (curRepeat.value < repeatEach.value) {
        curRepeat.value++;
        replayCurrent();
        return;
    }
    curRepeat.value = 1;
    const { start, end } = playBounds();
    if (idx < end) {
        playAt(idx + 1);
        return;
    }
    // بلغنا نهاية المقطع
    if (loopScope.value) {
        if (scopeLoops.value === INF || curLoop.value < scopeLoops.value) {
            curLoop.value++;
            playAt(start);
            return;
        }
        stop();
        return;
    }
    if (scope.value === 'range') {
        stop();
        return;
    }
    gotoNextPage(); // الصفحة كاملة بلا تكرار → إكمال تلقائي
}
// بدء تشغيل من فهرس مع تصفير عدّادات التكرار (نقاط الدخول الخارجية)
function startAt(i: number) {
    curRepeat.value = 1;
    curLoop.value = 1;
    playAt(i);
}
// تشغيل المقطع الحالي من بدايته
function playScope() {
    teacherMode.value = true;
    startAt(playBounds().start);
}

function onEnded() {
    advance();
}
// تظليل الكلمة حسب توقيت التلاوة
function onTimeUpdate() {
    if (!audioEl || !currentSegments) return;
    const t = audioEl.currentTime * 1000;
    for (const seg of currentSegments) {
        // seg = [i, wordNo, startMs, endMs]
        if (t >= seg[2] && t < seg[3]) {
            activeWord.value = seg[1];
            return;
        }
    }
}

// أنشئ عنصر صوت مُحمَّلاً مسبقاً (يبدأ جلب الملف فوراً)
function makeEl(url: string): HTMLAudioElement {
    const a = new Audio();
    a.preload = 'auto';
    a.src = url;
    a.load();
    return a;
}
function absUrl(u: string): string {
    return new URL(u, window.location.href).href;
}

function playAt(i: number) {
    if (i < 0 || i >= props.audio.length) {
        // انتهت آيات الصفحة — تابع تلقائياً للصفحة التالية إن وُجدت
        if (i >= props.audio.length && props.next) {
            try {
                sessionStorage.setItem('quran-autoplay', '1');
            } catch {
                /* */
            }
            router.visit(
                `/mushaf/${props.next}${props.reciterId ? `?reciter=${props.reciterId}` : ''}`,
            );
            return;
        }
        stop();
        return;
    }
    idx = i;
    const item = props.audio[i];
    activeVerse.value = item.verse_key;
    activeWord.value = null;
    currentSegments = item.segments ?? null;

    // استخدم العنصر المُحمَّل مسبقاً إن طابق (تلاوة متواصلة بلا فجوة)
    let a: HTMLAudioElement;
    if (preloadEl && preloadEl.src === absUrl(item.url)) {
        a = preloadEl;
        preloadEl = null;
    } else {
        a = makeEl(item.url);
    }

    // بدّل العنصر الحالي
    if (audioEl && audioEl !== a) {
        audioEl.pause();
        audioEl.removeEventListener('ended', onEnded);
        audioEl.removeEventListener('timeupdate', onTimeUpdate);
    }
    audioEl = a;
    a.removeEventListener('ended', onEnded);
    a.removeEventListener('timeupdate', onTimeUpdate);
    a.addEventListener('ended', onEnded);
    a.addEventListener('timeupdate', onTimeUpdate);
    void a.play();
    playing.value = true;
    scrollToVerse(item.verse_key);

    // جهّز الآية التالية مسبقاً أثناء تشغيل الحالية
    const nx = props.audio[i + 1];
    preloadEl = nx ? makeEl(nx.url) : null;
}
function toggle() {
    if (!props.audio.length) return;
    if (playing.value) {
        audioEl?.pause();
        playing.value = false;
    } else if (activeVerse.value && audioEl) {
        void audioEl.play();
        playing.value = true;
    } else startAt(playBounds().start);
}
function stop() {
    audioEl?.pause();
    playing.value = false;
    activeVerse.value = null;
    activeWord.value = null;
    currentSegments = null;
    curRepeat.value = 1;
    curLoop.value = 1;
}
function scrollToVerse(key: string) {
    document
        .querySelector(`[data-verse="${key}"]`)
        ?.scrollIntoView({ block: 'center', behavior: 'smooth' });
}

/* ---------- لوحة التفسير ---------- */
const drawerOpen = ref(false);
const loadingVerse = ref(false);
const selectedVerse = ref<string | null>(null);
interface SimilarVerse {
    verse_key: string;
    surah: string;
    number_in_surah: number;
    text: string;
    page: number;
    span: number;
    show_context: boolean;
}
interface VerseData {
    verse_key: string;
    number_in_surah: number;
    text_uthmani: string;
    text_tajweed: string | null;
    surah: { id: number; name: string };
    asbab: { name: string; author: string; text: string } | null;
    word_meanings: { name: string; text: string } | null;
    tafsirs: { name: string; text: string }[];
    translations: { name: string; language: string; text: string }[];
    similar: SimilarVerse[];
}
const verseData = ref<VerseData | null>(null);
const activeTafsir = ref(0); // فهرس التفسير المعروض في القائمة
const showTranslation = ref(false);
const showMeanings = ref(true); // «معاني الكلمات» مفتوح افتراضياً
const showVerseTajweed = ref(false); // تلوين تجويد الآية داخل اللوحة
const showSimilar = ref(true); // «آيات متشابهة» مفتوح افتراضياً
const showAsbab = ref(true); // «أسباب النزول» مفتوح افتراضياً

// روابط سريعة (أعلى الشاشة): تفتح القسم للآية المحددة إن كانت تحمله،
// وإلا لأول آية في الصفحة تحمله، ثم تُبرزه بوميض.
async function jumpToSection(which: 'asbab' | 'similar') {
    const list = which === 'asbab' ? props.asbabKeys : props.similarKeys;
    if (!list.length) return;

    const target =
        selectedVerse.value && list.includes(selectedVerse.value)
            ? selectedVerse.value
            : list[0];

    if (!drawerOpen.value || selectedVerse.value !== target) {
        await openVerse(target);
    }

    if (which === 'asbab') showAsbab.value = true;
    else showSimilar.value = true;
    await nextTick();

    const el = document.querySelector(`.drawer .${which}`);
    if (el) {
        el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        el.classList.remove('flash');
        // إعادة تشغيل وميض تنبيهي بسيط ليلحظه المستخدم
        void (el as HTMLElement).offsetWidth;
        el.classList.add('flash');
    }
}

async function openVerse(key: string) {
    selectedVerse.value = key;
    drawerOpen.value = true;
    loadingVerse.value = true;
    verseData.value = null;
    activeTafsir.value = 0;
    try {
        const res = await fetch(`/api/verse/${key}`, {
            headers: { Accept: 'application/json' },
        });
        verseData.value = await res.json();
    } finally {
        loadingVerse.value = false;
    }
}
function closeDrawer() {
    drawerOpen.value = false;
    selectedVerse.value = null;
}
function onWordClick(w: WordT) {
    // أثناء إخفاء الآيات: الضغط يكشف الآية؛ وإلا يفتح التفسير
    if (hiddenKeys.value.has(w.verse_key)) revealVerse(w.verse_key);
    else if (hideActive.value) {
        /* آية مكشوفة داخل وضع الإخفاء — لا نفتح التفسير */
    } else openVerse(w.verse_key);
}
function playVerseFromDrawer(key: string) {
    const i = props.audio.findIndex((a) => a.verse_key === key);
    if (i >= 0) startAt(i);
}

/* ---------- مشاركة كصورة ---------- */
let logoImg: HTMLImageElement | null = null;
function loadLogo(): Promise<HTMLImageElement | null> {
    if (logoImg) return Promise.resolve(logoImg);
    return new Promise((resolve) => {
        const img = new Image();
        img.onload = () => {
            logoImg = img;
            resolve(img);
        };
        img.onerror = () => resolve(null);
        img.src = '/storage/Images/logo/qu-logo-v4.webp';
    });
}
function toArabicNum(n: number): string {
    return String(n).replace(/[0-9]/g, (d) => '٠١٢٣٤٥٦٧٨٩'[+d]);
}
function stripHtml(html: string): string {
    const d = document.createElement('div');
    d.innerHTML = html.replace(/<br\s*\/?>/gi, ' ');
    return (d.textContent || '').replace(/\s+/g, ' ').trim();
}
function roundRect(
    ctx: CanvasRenderingContext2D,
    x: number,
    y: number,
    w: number,
    h: number,
    r: number,
) {
    ctx.beginPath();
    ctx.moveTo(x + r, y);
    ctx.arcTo(x + w, y, x + w, y + h, r);
    ctx.arcTo(x + w, y + h, x, y + h, r);
    ctx.arcTo(x, y + h, x, y, r);
    ctx.arcTo(x, y, x + w, y, r);
    ctx.closePath();
}
function wrapArabic(
    ctx: CanvasRenderingContext2D,
    text: string,
    maxWidth: number,
): string[] {
    const words = text.split(' ');
    const lines: string[] = [];
    let cur = '';
    for (const w of words) {
        const test = cur ? `${cur} ${w}` : w;
        if (ctx.measureText(test).width > maxWidth && cur) {
            lines.push(cur);
            cur = w;
        } else cur = test;
    }
    if (cur) lines.push(cur);
    return lines;
}
function drawFrame(ctx: CanvasRenderingContext2D, W: number, H: number) {
    const g = ctx.createLinearGradient(0, 0, W, H);
    g.addColorStop(0, '#25935f');
    g.addColorStop(1, '#0e3a27');
    ctx.fillStyle = g;
    ctx.fillRect(0, 0, W, H);
    roundRect(ctx, 70, 70, W - 140, H - 140, 40);
    ctx.fillStyle = '#fdfbf4';
    ctx.fill();
    ctx.strokeStyle = '#c6a15a';
    ctx.lineWidth = 3;
    ctx.stroke();
}
async function drawLogo(
    ctx: CanvasRenderingContext2D,
    W: number,
    topY: number,
): Promise<number> {
    const logo = await loadLogo();
    if (!logo) return topY;
    const h = 88,
        w = h * (logo.width / logo.height);
    ctx.drawImage(logo, (W - w) / 2, topY, w, h);
    return topY + h;
}
async function exportCanvas(c: HTMLCanvasElement, name: string, title: string) {
    return new Promise<void>((resolve) => {
        c.toBlob(async (blob) => {
            if (!blob) {
                resolve();
                return;
            }
            const file = new File([blob], name, { type: 'image/png' });
            const nav = navigator as any;
            if (nav.canShare && nav.canShare({ files: [file] })) {
                try {
                    await nav.share({ files: [file], title });
                    resolve();
                    return;
                } catch {
                    /* */
                }
            }
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = name;
            a.click();
            URL.revokeObjectURL(url);
            resolve();
        }, 'image/png');
    });
}

// صورة الآية (رسم عثماني + رقم الآية + الشعار)
async function shareAyahImage() {
    const v = verseData.value;
    if (!v) return;
    try {
        await document.fonts.load('64px "Amiri Quran"');
        await document.fonts.ready;
    } catch {
        /* */
    }

    const W = 1080,
        H = 1080,
        m = 70;
    const c = document.createElement('canvas');
    c.width = W;
    c.height = H;
    const ctx = c.getContext('2d');
    if (!ctx) return;

    drawFrame(ctx, W, H);
    const afterLogo = await drawLogo(ctx, W, m + 55);
    ctx.direction = 'rtl';
    ctx.textAlign = 'center';

    ctx.fillStyle = '#25935f';
    ctx.font = 'bold 46px "Amiri Quran", serif';
    const ySurah = afterLogo + 78;
    ctx.fillText(`سورة ${v.surah.name}`, W / 2, ySurah);

    // نص الآية + رمز نهاية الآية بالرقم
    ctx.fillStyle = '#1b1b1b';
    const fs = 64;
    ctx.font = `${fs}px "Amiri Quran", serif`;
    const text = `${v.text_uthmani} ﴿${toArabicNum(v.number_in_surah)}﴾`;
    const lines = wrapArabic(ctx, text, W - 2 * m - 130);
    const lh = fs * 1.95;
    const top = ySurah + 40,
        bottom = H - m - 110;
    let y = (top + bottom) / 2 - (lines.length * lh) / 2 + lh / 2;
    for (const ln of lines) {
        ctx.fillText(ln, W / 2, y);
        y += lh;
    }

    ctx.fillStyle = '#25935f';
    ctx.font = '600 30px "Segoe UI", Tahoma, sans-serif';
    ctx.fillText('القرآن الكريم · جامعة القصيم', W / 2, H - m - 52);

    await exportCanvas(
        c,
        `ayah-${v.verse_key.replace(':', '-')}.png`,
        `آية ${v.verse_key}`,
    );
}

// صورة التفسير (الآية + نص التفسير المختار)
function canvasToBlob(c: HTMLCanvasElement): Promise<Blob | null> {
    return new Promise((res) => c.toBlob((b) => res(b), 'image/png'));
}
async function shareFiles(files: File[], title: string) {
    const nav = navigator as any;
    if (nav.canShare && nav.canShare({ files })) {
        try {
            await nav.share({ files, title });
            return;
        } catch {
            /* ألغى/فشل — نُنزّل */
        }
    }
    for (const f of files) {
        const url = URL.createObjectURL(f);
        const a = document.createElement('a');
        a.href = url;
        a.download = f.name;
        a.click();
        URL.revokeObjectURL(url);
        await new Promise((r) => setTimeout(r, 350));
    }
}

async function shareTafsirImage() {
    const v = verseData.value;
    if (!v || !v.tafsirs.length) return;
    const t = v.tafsirs[activeTafsir.value] || v.tafsirs[0];
    try {
        await document.fonts.load('48px "Amiri Quran"');
        await document.fonts.ready;
    } catch {
        /* */
    }

    const W = 1080,
        m = 70,
        pad = m + 55;
    const ayahFs = 50,
        ayLH = ayahFs * 1.9;
    const tafFs = 33,
        tfLH = tafFs * 1.75;
    const measure = document.createElement('canvas').getContext('2d')!;
    measure.direction = 'rtl';

    measure.font = `${ayahFs}px "Amiri Quran", serif`;
    const ayahText = `${v.text_uthmani} ﴿${toArabicNum(v.number_in_surah)}﴾`;
    const ayahLines = wrapArabic(measure, ayahText, W - 2 * pad);

    measure.font = `${tafFs}px "Segoe UI", Tahoma, sans-serif`;
    const allTaf = wrapArabic(measure, stripHtml(t.text), W - 2 * pad);

    // تقسيم التفسير إلى أجزاء (~26 سطراً لكل صورة) مع «يتبع»
    const perPart = 26;
    const parts: string[][] = [];
    for (let i = 0; i < allTaf.length; i += perPart)
        parts.push(allTaf.slice(i, i + perPart));
    if (parts.length === 0) parts.push([]);
    const total = parts.length;

    async function buildPart(
        partLines: string[],
        idx: number,
    ): Promise<File | null> {
        const isFirst = idx === 0;
        const isLast = idx === total - 1;

        // ارتفاع مطابق للرسم
        let yy = m + 45 + 88 + 62; // شعار + ترويسة
        yy += isFirst ? 95 + ayahLines.length * ayLH + 18 + 52 + 54 : 66 + 40;
        yy += partLines.length * tfLH;
        if (!isLast) yy += 56; // سطر «يتبع»
        const H = Math.round(yy + 60 + 40 + m);

        const c = document.createElement('canvas');
        c.width = W;
        c.height = H;
        const ctx = c.getContext('2d');
        if (!ctx) return null;

        drawFrame(ctx, W, H);
        const afterLogo = await drawLogo(ctx, W, m + 45);
        ctx.direction = 'rtl';
        ctx.textAlign = 'center';

        ctx.fillStyle = '#25935f';
        ctx.font = 'bold 40px "Amiri Quran", serif';
        let y = afterLogo + 62;
        const suffix =
            total > 1 ? ` (${toArabicNum(idx + 1)}/${toArabicNum(total)})` : '';
        ctx.fillText(
            `سورة ${v!.surah.name} · الآية ${v!.number_in_surah}${suffix}`,
            W / 2,
            y,
        );

        if (isFirst) {
            ctx.fillStyle = '#1b1b1b';
            ctx.font = `${ayahFs}px "Amiri Quran", serif`;
            y += 95;
            for (const ln of ayahLines) {
                ctx.fillText(ln, W / 2, y);
                y += ayLH;
            }
            y += 18;
            ctx.strokeStyle = '#e2d5ac';
            ctx.lineWidth = 2;
            ctx.beginPath();
            ctx.moveTo(pad, y);
            ctx.lineTo(W - pad, y);
            ctx.stroke();
            y += 52;
            ctx.fillStyle = '#8a6d2f';
            ctx.font = 'bold 33px "Segoe UI", Tahoma, sans-serif';
            ctx.fillText(`التفسير — ${t.name}`, W / 2, y);
            y += 54;
        } else {
            y += 66;
            ctx.fillStyle = '#8a6d2f';
            ctx.font = 'bold 32px "Segoe UI", Tahoma, sans-serif';
            ctx.fillText(`تتمة التفسير — ${t.name}`, W / 2, y);
            y += 40;
        }

        ctx.textAlign = 'right';
        ctx.fillStyle = '#2c3a29';
        ctx.font = `${tafFs}px "Segoe UI", Tahoma, sans-serif`;
        for (const ln of partLines) {
            ctx.fillText(ln, W - pad, y);
            y += tfLH;
        }

        if (!isLast) {
            ctx.textAlign = 'left';
            ctx.fillStyle = '#8a6d2f';
            ctx.font = 'bold 30px "Segoe UI", Tahoma, sans-serif';
            ctx.fillText('يتبع ⟵', pad, y + 32);
        }

        ctx.textAlign = 'center';
        ctx.fillStyle = '#25935f';
        ctx.font = '600 28px "Segoe UI", Tahoma, sans-serif';
        ctx.fillText('القرآن الكريم · جامعة القصيم', W / 2, H - m - 40);

        const blob = await canvasToBlob(c);
        if (!blob) return null;
        const key = v!.verse_key.replace(':', '-');
        const name =
            total > 1 ? `tafsir-${key}-${idx + 1}.png` : `tafsir-${key}.png`;
        return new File([blob], name, { type: 'image/png' });
    }

    const files: File[] = [];
    for (let i = 0; i < total; i++) {
        const f = await buildPart(parts[i], i);
        if (f) files.push(f);
    }
    await shareFiles(files, `تفسير ${v.verse_key}`);
}

onMounted(() => {
    window.addEventListener('keydown', onKey);
    // حفظ آخر صفحة توقّف عندها القارئ تلقائياً
    try {
        localStorage.setItem('quran-last-page', String(props.page));
        if (props.reciterId)
            localStorage.setItem('quran-last-reciter', String(props.reciterId));
    } catch {
        /* */
    }
    // استعادة لغة الترجمة المختارة سابقاً (أي رمز لغة متوفّر)
    try {
        const saved = localStorage.getItem('quran-trans-lang');
        if (
            saved &&
            saved !== 'none' &&
            props.translationLangs.some((l) => l.code === saved)
        )
            setLang(saved);
    } catch {
        /* */
    }
    // استعادة حجم النص المختار
    try {
        const s = parseFloat(localStorage.getItem('quran-font-scale') || '1');
        if (s >= MIN_SCALE && s <= MAX_SCALE) fontScale.value = s;
    } catch {
        /* */
    }
    // استعادة الرواية المختارة (وضع الرواية)
    try {
        const rw = localStorage.getItem('quran-riwayah');
        if (rw && rw !== 'hafs' && props.riwayat.some((r) => r.slug === rw)) {
            riwayah.value = rw;
            loadRiwayah(1);
        }
    } catch {
        /* */
    }
    // استعادة وضع التجويد + تلوين تجويد الآية
    try {
        if (localStorage.getItem('quran-tajweed-mode') === '1') {
            tajweedMode.value = true;
            loadTajweed();
        }
        showVerseTajweed.value =
            localStorage.getItem('quran-verse-tajweed') === '1';
    } catch {
        /* */
    }
    // عند القدوم من قفزة «آية متشابهة» (?v=سورة:آية) افتح تلك الآية تلقائياً
    try {
        const v = new URLSearchParams(window.location.search).get('v');
        if (v && /^\d+:\d+$/.test(v)) {
            openVerse(v);
            setTimeout(() => scrollToVerse(v), 300);
        }
    } catch {
        /* */
    }
    // استعادة إعدادات نمط المُعلّم
    try {
        const t = JSON.parse(localStorage.getItem('quran-teacher') || '{}');
        if (typeof t.repeatEach === 'number') repeatEach.value = t.repeatEach;
        if (typeof t.loopScope === 'boolean') loopScope.value = t.loopScope;
        if (typeof t.scopeLoops === 'number') scopeLoops.value = t.scopeLoops;
        if (typeof t.flashSeconds === 'number')
            flashSeconds.value = t.flashSeconds;
        if (typeof t.flashGap === 'number') flashGap.value = t.flashGap;
        if (typeof t.flashAuto === 'boolean') flashAuto.value = t.flashAuto;
    } catch {
        /* */
    }
    // تحميل خطط المراجعة والتحقق من المستحقّة الآن (لافتة + إشعار المتصفح)
    plans.value = loadPlans();
    checkDue();
    fireDueNotifications();
    // فحص دوري أثناء بقاء التبويب مفتوحاً (كل دقيقة)
    dueTimer = window.setInterval(() => {
        const before = dueList.value.length;
        checkDue();
        if (dueList.value.length > before) fireDueNotifications();
    }, 60_000);
    // إن جئنا نتيجة إكمال تلقائي للتلاوة، ابدأ فوراً
    maybeAutoplay();
});
onUnmounted(() => {
    window.removeEventListener('keydown', onKey);
    audioEl?.pause();
    clearFlashTimers();
    if (dueTimer !== null) clearInterval(dueTimer);
});
</script>

<template>
    <Head :title="`المصحف — صفحة ${page}`">
        <!-- تحميل مسبق لخط صفحة QCF ليظهر النص العثماني بشكله النهائي مباشرة -->
        <link
            rel="preload"
            as="font"
            type="font/woff2"
            crossorigin="anonymous"
            :href="`/fonts/qcf/v2/p${page}.woff2`"
        />
    </Head>

    <div class="mushaf-screen" dir="rtl">
        <AppNav active="mushaf" />

        <!-- لافتة تذكير المراجعة (تظهر عند حلول موعد خطة) -->
        <div v-for="p in dueList" :key="p.id" class="due-banner">
            <span class="due-ico"><Icon name="bell" :size="18" /></span>
            <span class="due-text"
                >حان وقت <b>{{ p.label }}</b></span
            >
            <div class="due-actions">
                <button class="due-btn primary" @click="openPlanPage(p)">
                    <Icon name="book-open" :size="14" /> افتح الصفحة
                </button>
                <button class="due-btn" @click="completePlan(p)">
                    <Icon name="check" :size="14" /> تمّت
                </button>
                <button class="due-btn ghost" @click="snoozePlan(p)">
                    <Icon name="clock" :size="14" /> أجّل ساعة
                </button>
            </div>
        </div>

        <!-- شريط أدوات مبسّط: نمط المُعلّم + الإعدادات -->
        <header class="topbar">
            <button
                v-if="!isRiwayah"
                class="tb-btn teacher-btn"
                :class="{ on: teacherMode }"
                @click="toggleTeacher"
            >
                <Icon name="graduation-cap" :size="18" />
                <span>نمط المُعلّم</span>
                <Icon v-if="teacherMode" name="check" :size="14" />
            </button>

            <button
                v-if="!isRiwayah"
                class="tb-btn tajweed-btn"
                :class="{ on: tajweedMode }"
                @click="toggleTajweedMode"
                aria-label="القراءة بالتجويد"
            >
                <Icon name="sparkles" :size="18" />
                <span class="tb-label">التجويد</span>
                <Icon v-if="tajweedMode" name="check" :size="14" />
            </button>

            <!-- روابط سريعة: تظهر إذا كانت هذه الصفحة تحوي آيات بسبب نزول/متشابهات -->
            <button
                v-if="asbabKeys.length"
                class="tb-btn jump-btn"
                @click="jumpToSection('asbab')"
                aria-label="سبب النزول"
            >
                <Icon name="book" :size="18" />
                <span class="tb-label">سبب النزول</span>
            </button>
            <button
                v-if="similarKeys.length"
                class="tb-btn jump-btn"
                @click="jumpToSection('similar')"
                aria-label="متشابهات"
            >
                <Icon name="repeat" :size="18" />
                <span class="tb-label"
                    >متشابهات
                    <span class="jump-count">{{
                        similarKeys.length
                    }}</span></span
                >
            </button>

            <span v-if="isRiwayah" class="riw-badge">
                <Icon name="book-open" :size="16" /> رواية
                {{ riwData?.name || '' }}
            </span>

            <div class="settings-wrap">
                <button
                    class="tb-btn"
                    :class="{ on: settingsOpen, active: transLang !== 'none' }"
                    @click="settingsOpen = !settingsOpen"
                    aria-label="الإعدادات"
                >
                    <Icon name="settings" :size="18" />
                    <span class="tb-label">الإعدادات</span>
                </button>
                <transition name="pop">
                    <div v-if="settingsOpen" class="settings-pop" dir="rtl">
                        <div v-if="riwayat.length > 1" class="sp-field">
                            <span class="sp-label"
                                ><Icon name="book-open" :size="15" />
                                الرواية</span
                            >
                            <div class="sp-select">
                                <select :value="riwayah" @change="onRiwSelect">
                                    <option
                                        v-for="r in riwayat"
                                        :key="r.slug"
                                        :value="r.slug"
                                    >
                                        {{ r.name }}
                                    </option>
                                </select>
                                <Icon
                                    name="chevron-down"
                                    :size="15"
                                    class="sp-caret"
                                />
                            </div>
                        </div>
                        <div v-if="!isRiwayah" class="sp-field">
                            <span class="sp-label"
                                ><Icon name="mic" :size="15" /> القارئ</span
                            >
                            <div class="sp-select">
                                <select
                                    :value="reciterId ?? undefined"
                                    @change="changeReciter"
                                >
                                    <option
                                        v-for="r in reciters"
                                        :key="r.id"
                                        :value="r.id"
                                    >
                                        {{ r.name }}
                                    </option>
                                </select>
                                <Icon
                                    name="chevron-down"
                                    :size="15"
                                    class="sp-caret"
                                />
                            </div>
                        </div>
                        <div v-if="!isRiwayah" class="sp-field">
                            <span class="sp-label"
                                ><Icon name="languages" :size="15" />
                                الترجمة</span
                            >
                            <div class="sp-select">
                                <select
                                    :value="transLang"
                                    @change="onLangSelect"
                                >
                                    <option value="none">
                                        بدون ترجمة (عربي فقط)
                                    </option>
                                    <option
                                        v-for="l in translationLangs"
                                        :key="l.code"
                                        :value="l.code"
                                    >
                                        {{ l.native }}
                                    </option>
                                </select>
                                <Icon
                                    name="chevron-down"
                                    :size="15"
                                    class="sp-caret"
                                />
                            </div>
                        </div>
                        <div class="sp-field">
                            <span class="sp-label"
                                ><Icon name="type" :size="15" /> حجم النص</span
                            >
                            <div class="sp-zoom">
                                <button
                                    @click="zoomOut"
                                    :disabled="fontScale <= MIN_SCALE"
                                    aria-label="تصغير"
                                >
                                    <Icon name="minus" :size="16" />
                                </button>
                                <span>{{ Math.round(fontScale * 100) }}%</span>
                                <button
                                    @click="zoomIn"
                                    :disabled="fontScale >= MAX_SCALE"
                                    aria-label="تكبير"
                                >
                                    <Icon name="plus" :size="16" />
                                </button>
                            </div>
                        </div>

                        <button
                            class="sp-guide-btn"
                            @click="
                                waqfGuideOpen = true;
                                settingsOpen = false;
                            "
                        >
                            <Icon name="book-open" :size="15" /> دليل علامات
                            الوقف والابتداء
                        </button>
                    </div>
                </transition>
                <div
                    v-if="settingsOpen"
                    class="sp-backdrop"
                    @click="settingsOpen = false"
                ></div>
            </div>
        </header>
        <p v-if="hideActive" class="memo-hint">
            <Icon name="eye-off" :size="15" /> الآيات مخفية — استرجعها من حفظك،
            ثم اضغط أي كلمة لكشف آيتها.
        </p>

        <!-- صفحة المصحف -->
        <main
            class="page-wrap"
            :class="{
                'with-panel': transLang !== 'none',
                'teacher-pad': teacherMode,
            }"
            @touchstart.passive="onTouchStart"
            @touchend.passive="onTouchEnd"
        >
            <button
                class="nav next"
                :class="{ hidden: transLang !== 'none' }"
                :disabled="isRiwayah ? !riwData?.next : !next"
                @click="navNext"
                aria-label="التالية"
            >
                <Icon name="chevron-left" :size="24" />
            </button>

            <!-- وضع الرواية (غير حفص) -->
            <div
                v-if="isRiwayah"
                class="page-column riwayah-column"
                :style="{ '--quran-scale': fontScale }"
            >
                <div class="page-topbar">
                    <span class="pt-juz" v-if="riwData?.jozz">{{
                        juzLabel(riwData.jozz)
                    }}</span>
                    <span class="pt-surah">{{ riwData?.name }}</span>
                </div>
                <div
                    class="mushaf-page riwayah-page"
                    :style="{ fontFamily: riwData?.font }"
                >
                    <div v-if="riwLoading" class="riw-loading">…</div>
                    <template v-else>
                        <template
                            v-for="(v, i) in riwData?.verses ?? []"
                            :key="i"
                        >
                            <div v-if="v.is_start" class="surah-banner">
                                <div class="surah-name">
                                    سورة {{ v.sura_name }}
                                </div>
                                <div v-if="v.bismillah" class="basmalah">﷽</div>
                            </div>
                            <span class="riw-aya">{{ v.text }} </span>
                        </template>
                    </template>
                </div>
                <div class="page-badge">{{ pageArabic(riwPage) }}</div>
                <p class="riw-note">
                    <Icon name="book-open" :size="13" /> رواية
                    {{ riwData?.name }} — نصّ وخط رسميّان من مجمع الملك فهد
                    (KFGQPC). المزايا التفاعلية (الصوت، التفسير، نمط المُعلّم)
                    متاحة في رواية حفص.
                </p>
            </div>

            <div
                v-else
                class="page-column"
                :style="{ '--quran-scale': fontScale }"
            >
                <!-- الجزء (يمين) واسم السورة (يسار) فوق الإطار -->
                <div class="page-topbar">
                    <span class="pt-juz" v-if="juz">{{ juzLabel(juz) }}</span>
                    <span class="pt-surah">{{
                        surahs.map((s) => 'سورة ' + s.name_arabic).join(' · ')
                    }}</span>
                </div>

                <div v-if="!tajweedMode" class="mushaf-page">
                    <template v-for="line in lines" :key="line.line_number">
                        <div v-if="line.start_surah" class="surah-banner">
                            <div class="surah-name">
                                {{ line.start_surah.name_uthmani }}
                            </div>
                            <div
                                v-if="line.start_surah.bismillah_pre"
                                class="basmalah"
                            >
                                ﷽
                            </div>
                        </div>

                        <div
                            class="qline"
                            :class="{ centered: centeredPage }"
                            :style="{ fontFamily: pageFont }"
                        >
                            <span
                                v-for="(w, i) in line.words"
                                :key="i"
                                class="word"
                                :class="{
                                    end: w.type === 'end',
                                    playing: w.verse_key === activeVerse,
                                    wordlit:
                                        w.verse_key === activeVerse &&
                                        w.pos === activeWord,
                                    selected: w.verse_key === selectedVerse,
                                    blurred: isWordHidden(w),
                                    inscope:
                                        teacherMode &&
                                        scope === 'range' &&
                                        scopeSet.has(w.verse_key),
                                }"
                                :data-verse="w.verse_key"
                                @click="onWordClick(w)"
                                >{{ w.code }}</span
                            >
                        </div>
                    </template>
                </div>

                <!-- وضع «القراءة بالتجويد»: نص عثماني مُلوَّن بقواعد التجويد -->
                <div v-else class="mushaf-page tajweed-page tajweed-colored">
                    <div v-if="tajweedLoading" class="tajweed-loading">
                        …جارٍ تحميل نص التجويد
                    </div>
                    <template v-else>
                        <p class="tajweed-flow">
                            <template
                                v-for="v in tajweedVerses"
                                :key="v.verse_key"
                            >
                                <span
                                    v-if="v.first_in_surah"
                                    class="tj-surah-break"
                                >
                                    <span class="tj-surah-name">{{
                                        v.surah_name
                                    }}</span>
                                    <span
                                        v-if="v.bismillah_pre"
                                        class="tj-basmalah"
                                        >﷽</span
                                    >
                                </span>
                                <span
                                    class="tj-ayah"
                                    :class="{
                                        selected: v.verse_key === selectedVerse,
                                    }"
                                    :data-verse="v.verse_key"
                                    v-html="v.html"
                                    @click="openVerse(v.verse_key)"
                                ></span>
                            </template>
                        </p>
                        <!-- مفتاح ألوان التجويد -->
                        <div class="tajweed-legend">
                            <button
                                class="tj-legend-toggle"
                                @click="tajweedLegendOpen = !tajweedLegendOpen"
                            >
                                <Icon name="sparkles" :size="14" />
                                <span>مفتاح ألوان التجويد</span>
                                <span class="chev"
                                    ><Icon
                                        :name="
                                            tajweedLegendOpen
                                                ? 'chevron-down'
                                                : 'chevron-left'
                                        "
                                        :size="15"
                                /></span>
                            </button>
                            <div
                                v-show="tajweedLegendOpen"
                                class="tj-legend-grid"
                            >
                                <span
                                    v-for="r in TAJWEED_RULES"
                                    :key="r.label"
                                    class="tj-legend-item"
                                >
                                    <span
                                        class="tj-swatch"
                                        :style="{ background: r.color }"
                                    ></span>
                                    {{ r.label }}
                                </span>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- رقم الصفحة بزخرفة تحت الإطار (كمصحف المدينة) -->
                <div class="page-badge">{{ pageArabic(page) }}</div>
            </div>

            <button
                class="nav prev"
                :class="{ hidden: transLang !== 'none' }"
                :disabled="isRiwayah ? !riwData?.prev : !prev"
                @click="navPrev"
                aria-label="السابقة"
            >
                <Icon name="chevron-right" :size="24" />
            </button>
        </main>

        <!-- لوحة الترجمة على اليمين — متعددة اللغات (RTL/LTR تلقائياً) -->
        <aside v-if="transLang !== 'none'" class="trans-panel" :dir="transDir">
            <header class="tp-head">
                <span class="tp-title">{{ transHead }}</span>
                <button
                    class="tp-close"
                    @click="setLang('none')"
                    aria-label="إغلاق الترجمة"
                >
                    <Icon name="x" :size="16" />
                </button>
            </header>
            <div v-if="transLoading" class="tp-loading">…</div>
            <div v-else class="tp-list">
                <template v-for="(v, i) in transVerses" :key="v.verse_key">
                    <div
                        v-if="
                            i === 0 ||
                            v.surah_name !== transVerses[i - 1].surah_name
                        "
                        class="tp-surah"
                    >
                        {{ v.surah_en }} · {{ v.surah_name }}
                    </div>
                    <div
                        class="tp-verse"
                        :class="{
                            active: v.verse_key === activeVerse,
                            selected: v.verse_key === selectedVerse,
                        }"
                        @click="focusVerse(v.verse_key)"
                    >
                        <span class="tp-num">{{ v.number_in_surah }}</span>
                        <p
                            class="tp-text"
                            :style="{
                                textAlign:
                                    transDir === 'rtl' ? 'right' : 'left',
                            }"
                        >
                            {{ v.text }}
                        </p>
                    </div>
                </template>
                <p v-if="!transVerses.length" class="tp-loading">
                    لا تتوفّر ترجمة.
                </p>
            </div>
        </aside>
        <div
            v-if="transLang !== 'none'"
            class="tp-backdrop"
            @click="setLang('none')"
        ></div>

        <!-- شارة العدّ التنازلي أثناء ظهور الآيات (المراجعة اللحظية) -->
        <div v-if="flashActive && !flashHidden" class="flash-badge">
            <Icon name="eye" :size="16" /> يظهر لـ {{ flashCountdown }} ث
        </div>
        <!-- بطاقة المراجعة اللحظية أثناء الإخفاء -->
        <div v-if="flashActive && flashHidden" class="flash-overlay">
            <div class="flash-card">
                <div class="flash-ring">
                    {{ flashAuto ? flashCountdown : '?' }}
                </div>
                <p class="flash-hint">
                    <Icon name="sparkles" :size="18" /> استرجِع الآيات من ذاكرتك
                </p>
                <div class="flash-actions">
                    <button class="btn primary" @click="flashRevealNow">
                        <Icon name="eye" :size="16" /> أظهر الآيات
                    </button>
                    <button class="btn ghost" @click="stopFlash">
                        <Icon name="stop" :size="15" /> إيقاف
                    </button>
                </div>
            </div>
        </div>

        <!-- لوحة نمط المُعلّم — رصيف سفلي فوق المشغّل -->
        <section v-if="teacherMode" class="teacher-panel" dir="rtl">
            <header class="tpx-head">
                <span class="tpx-title"
                    ><Icon name="graduation-cap" :size="17" /> نمط
                    المُعلّم</span
                >
                <div class="scope-picker" role="group" aria-label="النطاق">
                    <button
                        class="seg"
                        :class="{ on: scope === 'page' }"
                        @click="scope = 'page'"
                    >
                        الصفحة كاملة
                    </button>
                    <button
                        class="seg"
                        :class="{ on: scope === 'range' }"
                        @click="scope = 'range'"
                    >
                        مقطع محدّد
                    </button>
                </div>
                <button
                    class="tpx-close"
                    @click="toggleTeacher"
                    aria-label="إغلاق نمط المُعلّم"
                >
                    <Icon name="x" :size="16" />
                </button>
            </header>

            <!-- محدّد المقطع -->
            <div v-if="scope === 'range'" class="range-row">
                <label class="range-field"
                    >من
                    <select v-model="rangeFrom">
                        <option v-for="k in pageVerses" :key="k" :value="k">
                            {{ verseLabel(k) }}
                        </option>
                    </select>
                </label>
                <label class="range-field"
                    >إلى
                    <select v-model="rangeTo">
                        <option v-for="k in pageVerses" :key="k" :value="k">
                            {{ verseLabel(k) }}
                        </option>
                    </select>
                </label>
                <span class="range-count">{{ scopeKeys.length }} آية</span>
            </div>

            <nav class="tpx-tabs">
                <button
                    :class="{ on: panelTab === 'cycle' }"
                    @click="panelTab = 'cycle'"
                >
                    الدورة
                </button>
                <button
                    :class="{ on: panelTab === 'repeat' }"
                    @click="panelTab = 'repeat'"
                >
                    التكرار
                </button>
                <button
                    :class="{ on: panelTab === 'flash' }"
                    @click="panelTab = 'flash'"
                >
                    مراجعة لحظية
                </button>
                <button
                    :class="{ on: panelTab === 'schedule' }"
                    @click="panelTab = 'schedule'"
                >
                    الجدولة
                </button>
            </nav>

            <div class="tpx-body">
                <!-- (2) الدورة المتكاملة -->
                <div v-show="panelTab === 'cycle'" class="tab">
                    <div class="stepper">
                        <template v-for="(s, i) in CYCLE_STEPS" :key="s.key">
                            <button
                                class="step"
                                :class="{
                                    on: cycleActive && cycleStep === i,
                                    done: cycleActive && cycleStep > i,
                                }"
                                @click="cycleActive ? cycleGo(i) : startCycle()"
                            >
                                <span class="step-ico"
                                    ><Icon :name="s.icon" :size="20"
                                /></span>
                                <span class="step-title">{{ s.title }}</span>
                            </button>
                            <span
                                v-if="i < CYCLE_STEPS.length - 1"
                                class="step-arrow"
                                ><Icon name="chevron-left" :size="14"
                            /></span>
                        </template>
                    </div>
                    <p class="step-hint">{{ CYCLE_STEPS[cycleStep].hint }}</p>
                    <div v-if="cycleActive" class="row-actions">
                        <button
                            class="btn ghost"
                            :disabled="cycleStep === 0"
                            @click="cyclePrev"
                        >
                            السابق
                        </button>
                        <button
                            v-if="cycleStepKey === 'listen'"
                            class="btn"
                            @click="playScope"
                        >
                            <Icon name="play" :size="15" /> أعد الاستماع
                        </button>
                        <button
                            v-if="cycleStepKey === 'review'"
                            class="btn"
                            @click="panelTab = 'schedule'"
                        >
                            <Icon name="calendar-plus" :size="15" /> جدوِل
                            المراجعة
                        </button>
                        <button
                            v-if="cycleStep < CYCLE_STEPS.length - 1"
                            class="btn primary"
                            @click="cycleNext"
                        >
                            التالي <Icon name="chevron-left" :size="15" />
                        </button>
                        <button v-else class="btn primary" @click="exitCycle">
                            <Icon name="check" :size="15" /> إنهاء
                        </button>
                    </div>
                    <div v-else class="row-actions">
                        <button class="btn primary big" @click="startCycle">
                            <Icon name="play" :size="16" /> ابدأ الدورة على
                            {{ scope === 'range' ? 'المقطع' : 'الصفحة' }}
                        </button>
                    </div>
                </div>

                <!-- (1) التكرار -->
                <div v-show="panelTab === 'repeat'" class="tab">
                    <div class="field">
                        <span class="field-label">كرّر كل آية</span>
                        <div class="opts">
                            <button
                                v-for="n in REPEAT_OPTS"
                                :key="n"
                                class="opt"
                                :class="{ on: repeatEach === n }"
                                @click="repeatEach = n"
                            >
                                {{ n === INF ? '∞' : n }}
                            </button>
                        </div>
                    </div>
                    <label class="switch-row">
                        <input type="checkbox" v-model="loopScope" />
                        <span>كرّر المقطع كاملاً</span>
                    </label>
                    <div v-if="loopScope" class="field">
                        <span class="field-label">عدد دورات المقطع</span>
                        <div class="opts">
                            <button
                                v-for="n in SCOPE_LOOP_OPTS"
                                :key="n"
                                class="opt"
                                :class="{ on: scopeLoops === n }"
                                @click="scopeLoops = n"
                            >
                                {{ n === INF ? '∞' : n }}
                            </button>
                        </div>
                    </div>
                    <div v-if="playing && activeVerse" class="repeat-status">
                        يتلو {{ verseLabel(activeVerse) }} — التكرار
                        {{ curRepeat
                        }}<span v-if="repeatEach !== INF"
                            >/{{ repeatEach }}</span
                        >
                        <span v-if="loopScope">
                            · الدورة {{ curLoop
                            }}<span v-if="scopeLoops !== INF"
                                >/{{ scopeLoops }}</span
                            ></span
                        >
                    </div>
                    <div class="row-actions">
                        <button class="btn primary big" @click="playScope">
                            <Icon name="play" :size="16" /> شغّل
                            {{ scope === 'range' ? 'المقطع' : 'الصفحة' }}
                            بالتكرار
                        </button>
                        <button v-if="playing" class="btn ghost" @click="stop">
                            <Icon name="stop" :size="15" /> إيقاف
                        </button>
                    </div>
                </div>

                <!-- (4) المراجعة اللحظية -->
                <div v-show="panelTab === 'flash'" class="tab">
                    <div class="field">
                        <span class="field-label"
                            >مدة إظهار الآيات (ثانية)</span
                        >
                        <div class="opts">
                            <button
                                v-for="n in FLASH_OPTS"
                                :key="n"
                                class="opt"
                                :class="{ on: flashSeconds === n }"
                                @click="flashSeconds = n"
                            >
                                {{ n }}
                            </button>
                        </div>
                    </div>
                    <label class="switch-row">
                        <input type="checkbox" v-model="flashAuto" />
                        <span>تكرار تلقائي (إظهار ← إخفاء ← إظهار)</span>
                    </label>
                    <div v-if="flashAuto" class="field">
                        <span class="field-label"
                            >مهلة الاسترجاع قبل الإظهار (ثانية)</span
                        >
                        <div class="opts">
                            <button
                                v-for="n in FLASH_OPTS"
                                :key="n"
                                class="opt"
                                :class="{ on: flashGap === n }"
                                @click="flashGap = n"
                            >
                                {{ n }}
                            </button>
                        </div>
                    </div>
                    <p class="tab-note">
                        تظهر الآيات للمدة المحدّدة ثم تُخفى تلقائياً لتختبر
                        حفظك.
                    </p>
                    <div class="row-actions">
                        <button
                            v-if="!flashActive"
                            class="btn primary big"
                            @click="startFlash"
                        >
                            <Icon name="timer" :size="16" /> ابدأ المراجعة
                            اللحظية
                        </button>
                        <button
                            v-else
                            class="btn danger big"
                            @click="stopFlash"
                        >
                            <Icon name="stop" :size="15" /> إيقاف المراجعة
                        </button>
                    </div>
                </div>

                <!-- (3) الجدولة والتنبيهات -->
                <div v-show="panelTab === 'schedule'" class="tab">
                    <div class="field">
                        <span class="field-label">كرّر المراجعة</span>
                        <div class="opts">
                            <button
                                v-for="d in EVERY_OPTS"
                                :key="d"
                                class="opt"
                                :class="{ on: schedEveryDays === d }"
                                @click="schedEveryDays = d"
                            >
                                {{ everyLabel(d) }}
                            </button>
                        </div>
                    </div>
                    <label class="time-row">
                        <span>وقت التذكير</span>
                        <input type="time" v-model="schedTime" />
                    </label>
                    <div class="row-actions">
                        <button class="btn primary big" @click="addPlan">
                            <Icon name="calendar-plus" :size="16" /> جدوِل
                            مراجعة
                            {{ scope === 'range' ? 'المقطع' : 'الصفحة' }}
                        </button>
                    </div>
                    <p class="tab-note">
                        يظهر التذكير عند فتح الموقع، مع إشعار المتصفح إن سمحت
                        به. للتذكير الموثوق على الجوّال حمّل ملف التقويم (.ics).
                    </p>

                    <div v-if="plans.length" class="plans">
                        <div class="plans-title">
                            خطط المراجعة ({{ plans.length }})
                        </div>
                        <div
                            v-for="p in plans"
                            :key="p.id"
                            class="plan"
                            :class="{ due: dueList.includes(p) }"
                        >
                            <div class="plan-main">
                                <div class="plan-label">{{ p.label }}</div>
                                <div class="plan-meta">
                                    {{ everyLabel(p.everyDays) }} · القادمة:
                                    {{ formatDue(p.nextDue)
                                    }}<span v-if="p.doneCount">
                                        · تمّت {{ p.doneCount }} مرة</span
                                    >
                                </div>
                            </div>
                            <div class="plan-actions">
                                <button
                                    class="icon-btn"
                                    title="حمّل للتقويم (.ics)"
                                    @click="downloadIcs(p)"
                                >
                                    <Icon name="calendar" :size="16" />
                                </button>
                                <button
                                    class="icon-btn ok"
                                    title="تمّت المراجعة"
                                    @click="completePlan(p)"
                                >
                                    <Icon name="check" :size="16" />
                                </button>
                                <button
                                    class="icon-btn del"
                                    title="حذف الخطة"
                                    @click="removePlan(p.id)"
                                >
                                    <Icon name="trash" :size="15" />
                                </button>
                            </div>
                        </div>
                    </div>
                    <p v-else class="sched-empty">لا توجد خطط مراجعة بعد.</p>
                </div>
            </div>
        </section>

        <!-- مشغّل الصوت — رصيف عائم (رواية حفص فقط) -->
        <footer v-if="!isRiwayah" class="player">
            <div class="dock">
                <button
                    class="ico"
                    :disabled="!prev"
                    @click="go(prev)"
                    aria-label="السابقة"
                >
                    <Icon name="chevron-right" :size="20" />
                </button>
                <span class="page-num">{{ page }}</span>
                <button
                    class="ico"
                    :disabled="!next"
                    @click="go(next)"
                    aria-label="التالية"
                >
                    <Icon name="chevron-left" :size="20" />
                </button>

                <div class="divider"></div>

                <button
                    class="play"
                    @click="toggle"
                    :disabled="!audio.length"
                    :aria-label="playing ? 'إيقاف مؤقّت' : 'تشغيل'"
                >
                    <Icon :name="playing ? 'pause' : 'play'" :size="18" />
                </button>
                <button
                    class="ico stop"
                    @click="stop"
                    v-if="activeVerse"
                    aria-label="إيقاف"
                >
                    <Icon name="stop" :size="15" />
                </button>
                <div class="pinfo">
                    <b v-if="reciter"
                        ><Icon name="mic" :size="13" /> {{ reciter }}</b
                    >
                    <small v-if="activeVerse"
                        >يتلو الآية {{ activeVerse }}</small
                    >
                    <small v-else>اضغط للاستماع لتلاوة الصفحة</small>
                </div>
            </div>
        </footer>

        <!-- لوحة التفسير/الترجمة -->
        <transition name="slide">
            <aside v-if="drawerOpen" class="drawer">
                <button class="close" @click="closeDrawer" aria-label="إغلاق">
                    <Icon name="x" :size="17" />
                </button>
                <div v-if="loadingVerse" class="loading">…جارٍ التحميل</div>
                <div v-else-if="verseData" class="vcontent">
                    <div class="vhead">
                        <span class="vbadge"
                            >{{ verseData.surah.name }} :
                            {{ verseData.number_in_surah }}</span
                        >
                        <div class="vactions">
                            <button
                                class="mini-btn"
                                @click="
                                    () =>
                                        playVerseFromDrawer(
                                            verseData!.verse_key,
                                        )
                                "
                            >
                                <Icon name="play" :size="14" /> استماع
                            </button>
                            <button class="mini-btn" @click="shareAyahImage">
                                <Icon name="image" :size="14" /> صورة
                            </button>
                        </div>
                    </div>
                    <div class="vtext-block">
                        <p v-if="!showVerseTajweed" class="vtext">
                            {{ verseData.text_uthmani }}
                        </p>
                        <p
                            v-else
                            class="vtext tajweed-colored"
                            v-html="
                                verseData.text_tajweed || verseData.text_uthmani
                            "
                        ></p>
                        <button
                            v-if="verseData.text_tajweed"
                            class="tj-toggle"
                            :class="{ on: showVerseTajweed }"
                            @click="toggleVerseTajweed"
                        >
                            <Icon name="sparkles" :size="14" />
                            {{
                                showVerseTajweed
                                    ? 'إخفاء التجويد'
                                    : 'تلوين التجويد'
                            }}
                        </button>
                        <div
                            v-if="showVerseTajweed"
                            class="tj-legend-grid compact"
                        >
                            <span
                                v-for="r in TAJWEED_RULES"
                                :key="r.label"
                                class="tj-legend-item"
                            >
                                <span
                                    class="tj-swatch"
                                    :style="{ background: r.color }"
                                ></span>
                                {{ r.label }}
                            </span>
                        </div>
                    </div>

                    <!-- أسباب النزول (صحيحة، موثّقة حديثياً) -->
                    <section v-if="verseData.asbab" class="asbab">
                        <button
                            class="section-toggle"
                            @click="showAsbab = !showAsbab"
                        >
                            <span
                                ><Icon name="book" :size="15" /> سبب
                                النزول</span
                            >
                            <span class="chev"
                                ><Icon
                                    :name="
                                        showAsbab
                                            ? 'chevron-down'
                                            : 'chevron-left'
                                    "
                                    :size="16"
                            /></span>
                        </button>
                        <div
                            v-show="showAsbab"
                            class="asbab-body"
                            v-html="verseData.asbab.text"
                        ></div>
                        <em v-show="showAsbab" class="src"
                            >— {{ verseData.asbab.author }}</em
                        >
                    </section>

                    <!-- المتشابهات اللفظية — للحفظ ونمط المعلّم -->
                    <section
                        v-if="verseData.similar && verseData.similar.length"
                        class="similar"
                    >
                        <button
                            class="section-toggle"
                            @click="showSimilar = !showSimilar"
                        >
                            <span
                                ><Icon name="repeat" :size="15" /> آيات متشابهة
                                <span class="sim-count">{{
                                    verseData.similar.length
                                }}</span></span
                            >
                            <span class="chev"
                                ><Icon
                                    :name="
                                        showSimilar
                                            ? 'chevron-down'
                                            : 'chevron-left'
                                    "
                                    :size="16"
                            /></span>
                        </button>
                        <ul v-show="showSimilar" class="sim-list">
                            <li
                                v-for="s in verseData.similar"
                                :key="s.verse_key"
                                class="sim-item"
                                @click="goToVerse(s.verse_key, s.page)"
                            >
                                <div class="sim-top">
                                    <span class="sim-key"
                                        >{{ s.surah }} : {{ s.number_in_surah
                                        }}<span
                                            v-if="s.span > 1"
                                            class="sim-span"
                                            >\u200F (+{{ s.span - 1 }})</span
                                        ></span
                                    >
                                    <Icon
                                        name="arrow-left"
                                        :size="14"
                                        class="sim-go"
                                    />
                                </div>
                                <p class="sim-text">{{ s.text }}</p>
                            </li>
                        </ul>
                        <em v-show="showSimilar" class="src"
                            >— مواضع يلتبس فيها الحفظ (متشابهات لفظية)</em
                        >
                    </section>

                    <section v-if="verseData.word_meanings" class="meanings">
                        <button
                            class="section-toggle"
                            @click="showMeanings = !showMeanings"
                        >
                            <span
                                ><Icon name="book-open" :size="15" /> معاني
                                الكلمات</span
                            >
                            <span class="chev"
                                ><Icon
                                    :name="
                                        showMeanings
                                            ? 'chevron-down'
                                            : 'chevron-left'
                                    "
                                    :size="16"
                            /></span>
                        </button>
                        <div
                            v-show="showMeanings"
                            class="meanings-body"
                            v-html="verseData.word_meanings.text"
                        ></div>
                        <em v-show="showMeanings" class="src"
                            >— {{ verseData.word_meanings.name }}</em
                        >
                    </section>

                    <template v-if="verseData.translations.length">
                        <button
                            class="section-toggle"
                            @click="showTranslation = !showTranslation"
                        >
                            <span
                                ><Icon name="languages" :size="15" /> ترجمة
                                المعاني</span
                            >
                            <span class="chev"
                                ><Icon
                                    :name="
                                        showTranslation
                                            ? 'chevron-down'
                                            : 'chevron-left'
                                    "
                                    :size="16"
                            /></span>
                        </button>
                        <p
                            v-show="showTranslation"
                            v-for="(t, i) in verseData.translations"
                            :key="i"
                            class="trans"
                        >
                            {{ t.text }}<em class="src">— {{ t.name }}</em>
                        </p>
                    </template>

                    <template v-if="verseData.tafsirs.length">
                        <div class="tafsir-head">
                            <h4>التفسير</h4>
                            <div class="thead-actions">
                                <select
                                    v-if="verseData.tafsirs.length > 1"
                                    v-model="activeTafsir"
                                    class="tafsir-select"
                                >
                                    <option
                                        v-for="(t, i) in verseData.tafsirs"
                                        :key="i"
                                        :value="i"
                                    >
                                        {{ t.name }}
                                    </option>
                                </select>
                                <button
                                    class="mini-btn"
                                    @click="shareTafsirImage"
                                    title="مشاركة التفسير كصورة"
                                >
                                    <Icon name="image" :size="14" /> صورة
                                </button>
                            </div>
                        </div>
                        <div
                            class="tafsir"
                            v-html="verseData.tafsirs[activeTafsir]?.text"
                        ></div>
                    </template>
                </div>
            </aside>
        </transition>
        <div v-if="drawerOpen" class="backdrop" @click="closeDrawer"></div>

        <!-- دليل علامات الوقف والابتداء (من مصحف المدينة — مجمع الملك فهد) -->
        <transition name="slide">
            <aside v-if="waqfGuideOpen" class="drawer waqf-guide" dir="rtl">
                <button class="close" @click="waqfGuideOpen = false">
                    <Icon name="x" :size="18" />
                </button>
                <div class="vcontent">
                    <h3 class="waqf-title">
                        <Icon name="book-open" :size="18" /> علامات الوقف
                        والابتداء
                    </h3>
                    <p class="waqf-intro">
                        العلامات التي تُرشد القارئ إلى مواضع الوقف والوصل في
                        مصحف المدينة (رواية حفص).
                    </p>
                    <ul class="waqf-list">
                        <li
                            v-for="w in WAQF_SIGNS"
                            :key="w.name"
                            class="waqf-item"
                        >
                            <span class="waqf-sign">{{ w.sign }}</span>
                            <div class="waqf-body">
                                <span class="waqf-name">{{ w.name }}</span>
                                <span class="waqf-desc">{{ w.desc }}</span>
                            </div>
                        </li>
                    </ul>
                    <em class="src"
                        >— وفق اصطلاح مصحف المدينة، مجمع الملك فهد لطباعة المصحف
                        الشريف</em
                    >
                </div>
            </aside>
        </transition>
        <div
            v-if="waqfGuideOpen"
            class="backdrop"
            @click="waqfGuideOpen = false"
        ></div>
    </div>
</template>

<style scoped>
/* خط مصحف المدينة الرسمي (KFGQPC Uthmanic Hafs) — للرسم العثماني في عرض التجويد */
@font-face {
    font-family: 'Uthmanic Hafs';
    src: url('/fonts/hafs/UthmanicHafs.woff2') format('woff2');
    font-display: swap;
}
.mushaf-screen {
    min-height: 100vh;
    background: var(--canvas);
    display: flex;
    flex-direction: column;
    color: var(--text);
}

/* شريط أدوات مبسّط */
.topbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.6rem;
    padding: 0.7rem 1.2rem 0.2rem;
    max-width: 760px;
    margin: 0 auto;
    width: 100%;
}
.tb-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.86rem;
    font-weight: 600;
    color: var(--text-muted);
    background: var(--surface);
    border: 1px solid var(--border);
    padding: 0.5rem 0.95rem;
    border-radius: 999px;
    cursor: pointer;
    font-family: inherit;
    transition:
        color 0.15s,
        background 0.15s,
        border-color 0.15s;
}
.tb-btn:hover {
    color: var(--text);
    border-color: var(--brand-200);
}
.tb-btn.on,
.tb-btn.active {
    background: var(--brand-soft);
    color: var(--brand);
    border-color: var(--brand-200);
}
.teacher-btn.on {
    box-shadow: 0 0 0 2px var(--brand-200);
}
/* روابط سريعة سياقية (سبب النزول / متشابهات) — مميّزة لتلفت انتباه المستخدم */
.jump-btn {
    background: var(--brand-soft);
    color: var(--brand);
    border-color: var(--brand-200);
}
.jump-btn:hover {
    color: var(--brand);
    background: color-mix(in srgb, var(--brand) 16%, var(--surface));
    border-color: var(--brand-200);
}
.jump-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 1.15rem;
    height: 1.15rem;
    padding: 0 0.28rem;
    margin-inline-start: 0.3rem;
    font-size: 0.7rem;
    font-weight: 700;
    line-height: 1;
    color: #fff;
    background: var(--brand);
    border-radius: 999px;
}
/* وميض تنبيهي عند القفز إلى القسم داخل اللوحة */
.drawer .flash {
    animation: sectionFlash 1.1s ease;
}
@keyframes sectionFlash {
    0% {
        box-shadow: 0 0 0 0 var(--brand-soft);
    }
    30% {
        box-shadow: 0 0 0 4px var(--brand-soft);
    }
    100% {
        box-shadow: 0 0 0 0 transparent;
    }
}
.memo-hint {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    text-align: center;
    margin: 0.4rem 1rem 0;
    font-size: 0.82rem;
    color: var(--brand);
}

/* قائمة الإعدادات المنبثقة */
.settings-wrap {
    position: relative;
}
.settings-pop {
    position: absolute;
    top: calc(100% + 0.5rem);
    left: 0;
    z-index: 46;
    width: min(300px, 86vw);
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 16px;
    box-shadow: var(--shadow-lg);
    padding: 0.9rem;
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
}
.sp-backdrop {
    position: fixed;
    inset: 0;
    z-index: 44;
}
.sp-field {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}
.sp-label {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.82rem;
    color: var(--text-muted);
}
.sp-label :deep(.lc-icon) {
    color: var(--brand);
}
.sp-select {
    position: relative;
}
.sp-select select {
    width: 100%;
    font-family: inherit;
    font-size: 0.88rem;
    font-weight: 600;
    color: var(--text);
    background: var(--surface-2);
    border: 1px solid var(--border);
    border-radius: 11px;
    padding: 0.55rem 2rem 0.55rem 0.7rem;
    cursor: pointer;
    -webkit-appearance: none;
    appearance: none;
}
.sp-select select:focus {
    outline: none;
    border-color: var(--brand-200);
}
.sp-caret {
    position: absolute;
    left: 0.6rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted);
    pointer-events: none;
}
.sp-zoom {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    background: var(--surface-2);
    border: 1px solid var(--border);
    border-radius: 11px;
    padding: 0.3rem 0.5rem;
}
.sp-zoom button {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    border: 1px solid var(--brand-200);
    background: var(--brand-soft);
    color: var(--brand);
    cursor: pointer;
    display: grid;
    place-items: center;
}
.sp-zoom button:disabled {
    opacity: 0.35;
    cursor: default;
}
.sp-zoom span {
    font-size: 0.85rem;
    font-weight: 700;
    color: var(--text);
    min-width: 3ch;
    text-align: center;
}
/* زرّ دليل علامات الوقف داخل قائمة الإعدادات */
.sp-guide-btn {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    width: 100%;
    margin-top: 0.2rem;
    padding: 0.6rem 0.8rem;
    border: 1px solid var(--border);
    border-radius: 12px;
    background: var(--surface-2);
    color: var(--text);
    font-family: inherit;
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
}
.sp-guide-btn:hover {
    border-color: var(--brand);
    color: var(--brand);
}
/* نافذة دليل علامات الوقف */
.waqf-title {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    margin: 0 0 0.3rem;
    font-size: 1.15rem;
    color: var(--text);
}
.waqf-intro {
    margin: 0 0 1rem;
    color: var(--text-muted);
    font-size: 0.85rem;
    line-height: 1.8;
}
.waqf-list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
}
.waqf-item {
    display: flex;
    align-items: flex-start;
    gap: 0.9rem;
    padding: 0.75rem 0.85rem;
    border: 1px solid var(--border);
    border-inline-start: 3px solid var(--brand);
    border-radius: 12px;
    background: var(--surface-2);
}
.waqf-sign {
    flex-shrink: 0;
    min-width: 2.6rem;
    text-align: center;
    font-family: 'Uthmanic Hafs', 'Amiri Quran', serif;
    font-size: 1.5rem;
    line-height: 1.4;
    color: var(--brand);
}
.waqf-body {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}
.waqf-name {
    font-weight: 700;
    color: var(--text);
    font-size: 0.95rem;
}
.waqf-desc {
    color: var(--text-muted);
    font-size: 0.83rem;
    line-height: 1.75;
}
.pop-enter-active,
.pop-leave-active {
    transition:
        opacity 0.15s,
        transform 0.15s;
}
.pop-enter-from,
.pop-leave-to {
    opacity: 0;
    transform: translateY(-6px);
}
.word.blurred {
    color: transparent !important;
    text-shadow: 0 0 12px var(--paper-ink);
    user-select: none;
}
.word.blurred.end {
    color: var(--brand-600) !important;
    text-shadow: none;
}

.page-wrap {
    flex: 1;
    display: flex;
    align-items: flex-start;
    justify-content: center;
    padding: 1rem 1rem 7rem;
}

/* ورقة المصحف — برواز من صورة زخرفية (public/freame.jpeg) */
.mushaf-page {
    container-type: inline-size;
    position: relative;
    width: min(720px, 94vw);
    background: var(--paper);
    border: clamp(26px, 6vw, 40px) solid transparent;
    border-image: url('../../images/freame-green.jpg') 66 round;
    padding: clamp(1rem, 3vw, 1.8rem) clamp(0.3rem, 1.2vw, 0.7rem)
        clamp(1rem, 3vw, 1.6rem);
    box-shadow: var(--shadow-md);
}
/* عمود الصفحة: الجزء واسم السورة فوق الإطار ثم الصفحة */
/* عرض الصفحة يكبر مع حجم النص — خط QCF يعتمد على عرض الحاوية (cqw) فيتناسب تلقائياً */
.page-column {
    display: flex;
    flex-direction: column;
    width: min(calc(720px * var(--quran-scale, 1)), 96vw);
    transition: width 0.18s ease;
}
.page-column .mushaf-page {
    width: 100%;
}
.page-topbar {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    gap: 0.5rem;
    padding: 0 clamp(0.6rem, 3vw, 1.6rem) 0.4rem;
    font-family: 'Amiri Quran', 'Traditional Arabic', serif;
}
.pt-surah {
    font-size: clamp(1rem, 3.4vw, 1.35rem);
    font-weight: 700;
    color: var(--brand-700);
}
.pt-juz {
    font-size: clamp(0.9rem, 3vw, 1.2rem);
    font-weight: 700;
    color: var(--brand-700);
}
/* رقم الصفحة بزخرفة أسفل الإطار */
.page-badge {
    width: fit-content;
    margin: 0.9rem auto 0;
    font-family: 'Segoe UI', Tahoma, sans-serif;
    font-weight: 700;
    font-size: 0.85rem;
    color: var(--brand-700);
    background: var(--paper);
    padding: 0.3rem 1.5rem;
    border: 1.5px solid var(--brand-600);
    border-radius: 999px;
    box-shadow:
        inset 0 0 0 2.5px var(--paper),
        inset 0 0 0 3.5px var(--brand-200);
}

.qline {
    /* محاذاة QCF الطبيعية: خط المصحف مصمّم ليملأ السطر بمسافاته الخاصة —
       توسيط ليكون الهامش يميناً ويساراً متساوياً تماماً */
    display: flex;
    justify-content: center;
    align-items: center;
    direction: rtl;
    /* خط QCF مصمّم ليملأ عرض الحاوية تماماً عند 6cqw — فلا يُضرب في معامل التكبير
       (وإلا تجاوز السطر عرض الإطار). التكبير يكبّر الإطار نفسه فيكبر النص تبعاً للحاوية. */
    font-size: clamp(0.85rem, 6cqw, calc(2.4rem * var(--quran-scale, 1)));
    line-height: 2;
    color: var(--paper-ink);
    white-space: nowrap;
}
/* صفحات موسّطة (الفاتحة/بداية البقرة) */
.qline.centered {
    justify-content: center;
}
.word {
    cursor: pointer;
    transition:
        background 0.15s,
        color 0.15s;
    border-radius: 8px;
    padding: 0 0.04em;
}
.word:hover {
    background: var(--brand-soft);
}
.word.end {
    color: var(--brand-600);
}
.word.playing {
    background: var(--brand-soft);
}
.word.wordlit {
    background: var(--brand);
    color: #fff;
}
.word.selected {
    background: var(--brand-soft);
    box-shadow: 0 0 0 1.5px var(--brand-200);
}

/* لافتة السورة — خطوط رفيعة أنيقة */
.surah-banner {
    text-align: center;
    margin: 1.2rem 0 1.9rem;
}
/* لافتة اسم السورة بإطار زخرفي (public/surah-banner.png) */
.surah-name {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: min(90%, 560px);
    aspect-ratio: 2107 / 245;
    margin: 0 auto;
    background: url('../../images/surah-banner.png') center / 100% 100%
        no-repeat;
    font-family: 'Amiri Quran', 'Traditional Arabic', serif;
    font-size: clamp(1.05rem, 4.2cqw, calc(1.7rem * var(--quran-scale, 1)));
    font-weight: 700;
    color: var(--brand-700);
    padding-bottom: 0.42em;
    line-height: 1;
}
/* مسافة أكبر تحت البسملة قبل أول آية */
.basmalah {
    margin-bottom: 0.6rem;
}
.basmalah {
    font-family: 'Amiri Quran', serif;
    font-size: clamp(1.4rem, 7.6cqw, calc(2.5rem * var(--quran-scale, 1)));
    line-height: 1;
    margin-top: 0.4rem;
    color: var(--paper-ink);
    white-space: nowrap;
}

/* أزرار التنقّل الجانبية — دائرية شبحية عائمة */
.nav {
    position: fixed;
    top: 50%;
    transform: translateY(-50%);
    z-index: 15;
    width: 46px;
    height: 46px;
    border-radius: 50%;
    background: var(--surface);
    color: var(--text-muted);
    border: 1px solid var(--border);
    font-size: 1.4rem;
    cursor: pointer;
    box-shadow: var(--shadow-sm);
    display: grid;
    place-items: center;
    transition:
        color 0.15s,
        border-color 0.15s,
        transform 0.15s;
}
.nav.next {
    left: max(1rem, calc(50% - 410px));
}
.nav.prev {
    right: max(1rem, calc(50% - 410px));
}
/* منع انعكاس رموز الأسهم في السياق العربي */
.nav,
.dock .ico {
    direction: ltr;
}
.nav:hover:not(:disabled) {
    color: var(--brand);
    border-color: var(--brand-200);
    transform: translateY(-50%) scale(1.08);
}
.nav:disabled {
    opacity: 0.3;
    cursor: default;
}

/* رصيف المشغّل العائم */
.player {
    position: fixed;
    bottom: 1.1rem;
    left: 0;
    right: 0;
    z-index: 20;
    display: flex;
    justify-content: center;
    pointer-events: none;
    padding: 0 1rem;
}
.dock {
    pointer-events: auto;
    display: flex;
    align-items: center;
    gap: 0.45rem;
    background: var(--glass);
    backdrop-filter: saturate(180%) blur(18px);
    -webkit-backdrop-filter: saturate(180%) blur(18px);
    border: 1px solid var(--glass-brd);
    border-radius: 999px;
    padding: 0.45rem 0.65rem;
    box-shadow: var(--shadow-lg);
    max-width: min(580px, 96vw);
}
.dock .ico {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    border: none;
    background: transparent;
    color: var(--text-muted);
    font-size: 1.2rem;
    cursor: pointer;
    display: grid;
    place-items: center;
    transition:
        background 0.15s,
        color 0.15s;
}
.dock .ico:hover:not(:disabled) {
    background: var(--surface-2);
    color: var(--text);
}
.dock .ico:disabled {
    opacity: 0.3;
    cursor: default;
}
.dock .ico.stop {
    font-size: 0.9rem;
}
.page-num {
    min-width: 2.2rem;
    text-align: center;
    font-weight: 700;
    font-size: 0.9rem;
    color: var(--text);
}
.divider {
    width: 1px;
    height: 26px;
    background: var(--border);
    margin: 0 0.2rem;
}
.play {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    border: none;
    cursor: pointer;
    background: var(--brand);
    color: #fff;
    font-size: 0.95rem;
    display: grid;
    place-items: center;
    box-shadow: 0 4px 14px rgba(37, 147, 95, 0.35);
    transition: transform 0.12s;
}
.play:hover:not(:disabled) {
    transform: scale(1.07);
}
.play:disabled {
    opacity: 0.5;
    cursor: default;
}
.pinfo {
    display: flex;
    flex-direction: column;
    line-height: 1.2;
    padding: 0 0.5rem;
    min-width: 0;
}
.pinfo b {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.85rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.pinfo b :deep(.lc-icon) {
    color: var(--brand);
}
.pinfo small {
    font-size: 0.72rem;
    color: var(--text-muted);
    white-space: nowrap;
}
.reciter-sel {
    font-family: inherit;
    font-size: 0.85rem;
    font-weight: 700;
    color: var(--text);
    background: transparent;
    border: none;
    cursor: pointer;
    padding: 0;
    margin: 0;
    max-width: 40vw;
    outline: none;
    -webkit-appearance: none;
    appearance: none;
}
.reciter-sel option {
    color: #000;
}

/* اللوحة الجانبية */
.drawer {
    position: fixed;
    top: 0;
    bottom: 0;
    left: 0;
    z-index: 60;
    width: min(460px, 94vw);
    background: var(--surface);
    box-shadow: var(--shadow-lg);
    padding: 1.5rem;
    overflow-y: auto;
    color: var(--text);
    font-family: 'Segoe UI', Tahoma, sans-serif;
}
.backdrop {
    position: fixed;
    inset: 0;
    background: rgba(10, 20, 15, 0.42);
    backdrop-filter: blur(2px);
    z-index: 55;
}
.close {
    position: sticky;
    top: 0;
    float: left;
    border: 1px solid var(--border);
    background: var(--surface);
    color: var(--text-muted);
    border-radius: 50%;
    width: 34px;
    height: 34px;
    cursor: pointer;
}
.vhead {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.8rem;
}
.vbadge {
    background: var(--brand-soft);
    color: var(--brand);
    padding: 0.3rem 0.9rem;
    border-radius: 999px;
    font-size: 0.85rem;
    font-weight: 600;
}
.vactions {
    display: flex;
    gap: 0.4rem;
}
.mini-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    border: 1px solid var(--brand-200);
    color: var(--brand);
    background: transparent;
    border-radius: 999px;
    padding: 0.3rem 0.8rem;
    cursor: pointer;
    font-family: inherit;
    font-size: 0.82rem;
    white-space: nowrap;
}
.mini-btn:hover {
    background: var(--brand-soft);
}
.vtext {
    font-family: 'Amiri Quran', serif;
    font-size: 1.7rem;
    line-height: 2.2;
    color: var(--paper-ink);
    background: var(--surface-2);
    padding: 1rem 1.2rem;
    border-radius: 16px;
}
.section-toggle {
    display: flex;
    justify-content: space-between;
    align-items: center;
    width: 100%;
    margin-top: 1.1rem;
    padding: 0.75rem 1rem;
    border: 1px solid var(--border);
    background: var(--surface-2);
    color: var(--text);
    border-radius: 12px;
    font-size: 0.95rem;
    font-weight: 600;
    cursor: pointer;
    font-family: inherit;
}
.section-toggle .chev {
    color: var(--brand);
}
.trans {
    line-height: 1.8;
    color: var(--text);
    margin: 0.6rem 0.2rem;
}
.trans .src {
    display: block;
    font-style: normal;
    font-size: 0.75rem;
    color: var(--text-muted);
    margin-top: 0.2rem;
}
/* قسم «معاني الكلمات» (غريب القرآن) — بارز بلون العلامة */
.meanings {
    margin-top: 1.1rem;
}
.meanings .section-toggle {
    margin-top: 0;
    border-color: color-mix(in srgb, var(--brand) 45%, var(--border));
    background: color-mix(in srgb, var(--brand) 8%, var(--surface-2));
}
/* أسباب النزول — لون كهرماني مميّز عن بقية الأقسام */
.asbab {
    margin-top: 1.1rem;
}
.asbab .section-toggle {
    margin-top: 0;
    border-color: color-mix(in srgb, #b8860b 45%, var(--border));
    background: color-mix(in srgb, #b8860b 8%, var(--surface-2));
}
.asbab .section-toggle .chev,
.asbab .section-toggle :deep(svg:first-child) {
    color: #b8860b;
}
.asbab-body {
    line-height: 2.05;
    font-size: 1.02rem;
    color: var(--text);
    padding: 0.9rem 1.05rem;
    margin-top: 0.4rem;
    border-radius: 12px;
    border: 1px solid color-mix(in srgb, #b8860b 30%, var(--border));
    border-inline-start: 3px solid #b8860b;
    background: color-mix(in srgb, #b8860b 6%, var(--surface));
}
.asbab .src {
    display: block;
    font-style: normal;
    font-size: 0.72rem;
    color: var(--text-muted);
    margin: 0.35rem 0.2rem 0;
}
.meanings-body {
    line-height: 2;
    font-size: 1.02rem;
    color: var(--text);
    padding: 0.85rem 1rem;
    margin-top: 0.4rem;
    border-radius: 12px;
    border: 1px solid color-mix(in srgb, var(--brand) 30%, var(--border));
    border-inline-start: 3px solid var(--brand);
    background: color-mix(in srgb, var(--brand) 6%, var(--surface));
}
.meanings-body :deep(b),
.meanings-body :deep(.green) {
    color: var(--brand);
    font-weight: 700;
}
.meanings-body :deep(sup) {
    display: none;
}
.meanings .src {
    display: block;
    font-style: normal;
    font-size: 0.72rem;
    color: var(--text-muted);
    margin: 0.35rem 0.2rem 0;
}

/* ============ التجويد: ألوان القواعد (نظام مصاحف التجويد) ============ */
/* النص المُعلَّم يُعرض على ورق فاتح ليقرأ اللون في الوضعين الفاتح والداكن */
.tajweed-colored {
    color: #1a1a1a;
    background: #fdfbf3;
}
.tajweed-colored :deep(tajweed) {
    display: inline;
}
/* حروف لا تُلفَظ / إدغام تام (رمادي) */
.tajweed-colored :deep(tajweed.ham_wasl),
.tajweed-colored :deep(tajweed.laam_shamsiyah),
.tajweed-colored :deep(tajweed.slnt),
.tajweed-colored :deep(tajweed.idgham_wo_ghunnah),
.tajweed-colored :deep(tajweed.idgham_mutajanisayn),
.tajweed-colored :deep(tajweed.idgham_mutaqaribayn) {
    color: #aaaaaa;
}
/* المدود (تدرّج أزرق) */
.tajweed-colored :deep(tajweed.madda_normal) {
    color: #537fff;
}
.tajweed-colored :deep(tajweed.madda_permissible) {
    color: #4050ff;
}
.tajweed-colored :deep(tajweed.madda_obligatory) {
    color: #2144c1;
}
.tajweed-colored :deep(tajweed.madda_necessary) {
    color: #000ebc;
}
/* قلقلة */
.tajweed-colored :deep(tajweed.qalaqah) {
    color: #dd0008;
}
/* غُنّة */
.tajweed-colored :deep(tajweed.ghunnah) {
    color: #ff7e1e;
}
/* إخفاء */
.tajweed-colored :deep(tajweed.ikhafa),
.tajweed-colored :deep(tajweed.ikhafa_shafawi) {
    color: #9400a8;
}
/* إدغام بغُنّة */
.tajweed-colored :deep(tajweed.idgham_ghunnah),
.tajweed-colored :deep(tajweed.idgham_shafawi) {
    color: #169200;
}
/* إقلاب */
.tajweed-colored :deep(tajweed.iqlab) {
    color: #26bffd;
}
/* رقم نهاية الآية داخل النص المُعلَّم */
.tajweed-colored :deep(span.end) {
    color: #b8860b;
    font-weight: 700;
    padding: 0 0.35rem;
    font-size: 0.85em;
}

/* زرّ تلوين التجويد داخل اللوحة */
.vtext-block {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}
.vtext.tajweed-colored {
    font-family: 'Uthmanic Hafs', 'Amiri Quran', serif;
    font-size: 1.7rem;
    line-height: 2.4;
    padding: 1rem 1.2rem;
    border-radius: 16px;
}
.tj-toggle {
    align-self: flex-start;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.4rem 0.8rem;
    border: 1px solid var(--border);
    background: var(--surface-2);
    color: var(--text);
    border-radius: 999px;
    font-size: 0.8rem;
    font-weight: 600;
    cursor: pointer;
    font-family: inherit;
}
.tj-toggle.on {
    border-color: color-mix(in srgb, var(--brand) 55%, var(--border));
    background: color-mix(in srgb, var(--brand) 12%, var(--surface-2));
    color: var(--brand);
}

/* مفتاح ألوان التجويد */
.tj-legend-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(9rem, 1fr));
    gap: 0.3rem 0.6rem;
    padding: 0.6rem 0.2rem 0;
}
.tj-legend-grid.compact {
    font-size: 0.72rem;
    padding: 0.3rem 0.6rem;
    background: var(--surface-2);
    border-radius: 10px;
}
.tj-legend-item {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.74rem;
    color: var(--text-muted);
}
.tj-swatch {
    width: 0.8rem;
    height: 0.8rem;
    border-radius: 3px;
    flex-shrink: 0;
    border: 1px solid rgba(0, 0, 0, 0.15);
}

/* ============ وضع «القراءة بالتجويد» للصفحة ============ */
/* وضع الرواية — نصّ يونيكود بخط KFGQPC الرسمي */
.riwayah-page {
    padding: 1.5rem 1.2rem;
    direction: rtl;
    text-align: justify;
    text-align-last: center;
}
.riw-loading {
    text-align: center;
    color: var(--text-muted);
    padding: 3rem 1rem;
}
.riw-aya {
    font-size: calc(1.7rem * var(--quran-scale, 1));
    line-height: 2.75;
    color: var(--paper-ink);
}
.riw-note {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    margin: 1rem 0 0;
    font-size: 0.74rem;
    line-height: 1.6;
    color: var(--text-muted);
    text-align: center;
    font-family: 'Segoe UI', Tahoma, sans-serif;
}
.riw-note :deep(.lc-icon) {
    color: var(--brand);
    flex: none;
}
.riw-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.85rem;
    font-weight: 700;
    color: var(--brand-700);
    background: var(--brand-soft);
    border: 1px solid var(--brand-200);
    border-radius: 999px;
    padding: 0.5rem 0.95rem;
    font-family: 'Segoe UI', Tahoma, sans-serif;
}
.riw-badge :deep(.lc-icon) {
    color: var(--brand);
}

.tajweed-page {
    padding: 1.4rem 1.2rem;
}
.tajweed-loading {
    text-align: center;
    color: var(--text-muted);
    padding: 3rem 1rem;
}
.tajweed-flow {
    font-family: 'Uthmanic Hafs', 'Amiri Quran', serif;
    font-size: calc(1.75rem * var(--quran-scale, 1));
    line-height: 2.9;
    text-align: justify;
    text-align-last: center;
    direction: rtl;
    margin: 0;
}
.tj-ayah {
    cursor: pointer;
}
.tj-ayah:hover {
    background: rgba(184, 134, 11, 0.12);
    border-radius: 4px;
}
.tj-ayah.selected {
    background: rgba(184, 134, 11, 0.18);
    border-radius: 4px;
}
.tj-surah-break {
    display: block;
    text-align: center;
    margin: 1.2rem 0 0.6rem;
}
.tj-surah-name {
    display: block;
    font-family: 'Uthmanic Hafs', 'Amiri Quran', serif;
    font-size: 1.5rem;
    font-weight: 700;
    color: #6b4e12;
    border-block: 2px solid #d8b34a;
    padding: 0.35rem 0;
}
.tj-basmalah {
    display: block;
    font-family: 'Uthmanic Hafs', 'Amiri Quran', serif;
    font-size: 1.9rem;
    color: #1a1a1a;
    margin-top: 0.4rem;
}
.tajweed-legend {
    margin-top: 1.4rem;
    padding-top: 0.9rem;
    border-top: 1px dashed var(--border);
}
.tj-legend-toggle {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    background: none;
    border: none;
    color: var(--text);
    font-family: inherit;
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    padding: 0;
}
.tj-legend-toggle .chev {
    color: var(--brand);
}

/* ============ المتشابهات اللفظية ============ */
.similar {
    margin-top: 1.1rem;
}
.sim-count {
    display: inline-block;
    min-width: 1.3rem;
    text-align: center;
    padding: 0 0.35rem;
    margin-inline-start: 0.3rem;
    font-size: 0.72rem;
    border-radius: 999px;
    background: var(--brand);
    color: #fff;
}
.sim-list {
    list-style: none;
    margin: 0.5rem 0 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}
.sim-item {
    padding: 0.7rem 0.85rem;
    border: 1px solid var(--border);
    border-inline-start: 3px solid var(--brand);
    border-radius: 12px;
    background: var(--surface-2);
    cursor: pointer;
    transition: background 0.15s;
}
.sim-item:hover {
    background: color-mix(in srgb, var(--brand) 8%, var(--surface-2));
}
.sim-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.3rem;
}
.sim-key {
    font-size: 0.82rem;
    font-weight: 700;
    color: var(--brand);
}
.sim-span {
    font-size: 0.72rem;
    color: var(--text-muted);
    font-weight: 500;
}
.sim-go {
    color: var(--text-muted);
}
.sim-text {
    margin: 0;
    font-family: 'Uthmanic Hafs', 'Amiri Quran', serif;
    font-size: 1.15rem;
    line-height: 1.9;
    color: var(--paper-ink);
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.tafsir-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.5rem;
    margin: 1.3rem 0 0.7rem;
}
.tafsir-head h4 {
    margin: 0;
    color: var(--text);
    font-size: 1.05rem;
}
.thead-actions {
    display: flex;
    align-items: center;
    gap: 0.4rem;
}
.tafsir-select {
    font-family: inherit;
    font-size: 0.85rem;
    padding: 0.4rem 0.7rem;
    border: 1px solid var(--border);
    border-radius: 10px;
    background: var(--surface);
    color: var(--text);
    cursor: pointer;
    max-width: 60%;
}
.tafsir {
    line-height: 2;
    font-size: 1rem;
    color: var(--text);
}
.tafsir :deep(.green),
.tafsir :deep(b) {
    color: var(--brand);
    font-weight: 700;
}
.tafsir :deep(sup) {
    display: none;
}
.loading {
    padding: 2rem;
    text-align: center;
    color: var(--text-muted);
}

.slide-enter-active,
.slide-leave-active {
    transition: transform 0.28s cubic-bezier(0.4, 0, 0.2, 1);
}
.slide-enter-from,
.slide-leave-to {
    transform: translateX(-100%);
}

/* لوحة الترجمة على اليمين */
.trans-panel {
    position: fixed;
    top: 0;
    bottom: 0;
    right: 0;
    z-index: 60;
    width: min(430px, 92vw);
    display: flex;
    flex-direction: column;
    background: var(--surface);
    border-left: 1px solid var(--border);
    box-shadow: var(--shadow-lg);
    font-family: 'Segoe UI', Tahoma, sans-serif;
}
/* على الشاشات الواسعة: تبدأ اللوحة تحت الشريط العلوي ليبقى ظاهراً (عرض جنباً إلى جنب) */
@media (min-width: 900px) {
    .trans-panel {
        top: 78px;
        z-index: 40;
    }
}
.tp-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    padding: 0.85rem 1rem;
    border-bottom: 1px solid var(--border);
    background: var(--surface-2);
    color: var(--text);
}
.tp-title {
    font-size: 0.82rem;
    font-weight: 700;
}
.tp-close {
    flex: none;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    border: 1px solid var(--border);
    background: var(--surface);
    color: var(--text-muted);
    cursor: pointer;
}
.tp-loading {
    padding: 2rem;
    text-align: center;
    color: var(--text-muted);
}
.tp-list {
    flex: 1;
    overflow-y: auto;
    padding: 0.4rem 0.5rem 6rem;
}
.tp-surah {
    position: sticky;
    top: 0;
    z-index: 1;
    background: var(--surface);
    color: var(--brand);
    font-weight: 700;
    font-size: 0.88rem;
    padding: 0.7rem 0.6rem 0.5rem;
    margin-bottom: 0.2rem;
    border-bottom: 1px solid var(--border);
}
.tp-verse {
    display: flex;
    align-items: flex-start;
    gap: 0.6rem;
    padding: 0.7rem 0.6rem;
    border-radius: 10px;
    cursor: pointer;
    transition: background 0.15s;
}
.tp-verse:hover {
    background: var(--surface-2);
}
.tp-verse.selected {
    background: var(--brand-soft);
}
.tp-verse.active {
    background: var(--brand-soft);
    box-shadow: inset 0 0 0 1.5px var(--brand-200);
}
.tp-num {
    flex: none;
    min-width: 1.6rem;
    height: 1.6rem;
    margin-top: 0.1rem;
    display: grid;
    place-items: center;
    border-radius: 50%;
    background: var(--brand-soft);
    color: var(--brand);
    font-size: 0.72rem;
    font-weight: 700;
}
.tp-text {
    margin: 0;
    line-height: 1.7;
    font-size: 0.95rem;
    color: var(--text);
    text-align: left;
}

.nav.hidden {
    display: none;
}
/* عرض جنبًا إلى جنب على الشاشات الواسعة: تُزاح الصفحة يسارًا لتظهر بجانب اللوحة */
.tp-backdrop {
    display: none;
}
@media (min-width: 900px) {
    .page-wrap.with-panel {
        padding-right: min(430px, 40vw);
    }
}
@media (max-width: 899px) {
    .tp-backdrop {
        display: block;
        position: fixed;
        inset: 0;
        z-index: 35;
        background: rgba(10, 20, 15, 0.42);
        backdrop-filter: blur(2px);
    }
}

/* ==================== نمط المُعلّم ==================== */
.teacher-chip.on {
    box-shadow: 0 0 0 2px var(--brand-200);
}
.word.inscope {
    box-shadow: inset 0 -2px 0 var(--brand-300);
    border-radius: 6px;
}

/* لافتة تذكير المراجعة */
.due-banner {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    flex-wrap: wrap;
    margin: 0.6rem auto 0;
    max-width: min(760px, 94vw);
    background: var(--brand-soft);
    border: 1px solid var(--brand-200);
    border-radius: 14px;
    padding: 0.6rem 0.9rem;
    color: var(--text);
    font-family: 'Segoe UI', Tahoma, sans-serif;
}
.due-ico {
    font-size: 1.2rem;
}
.due-text {
    flex: 1;
    min-width: 12ch;
    font-size: 0.9rem;
}
.due-text b {
    color: var(--brand-700);
}
.due-actions {
    display: flex;
    gap: 0.4rem;
    flex-wrap: wrap;
}
.due-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    border: 1px solid var(--border);
    background: var(--surface);
    color: var(--text);
    border-radius: 999px;
    padding: 0.32rem 0.85rem;
    font-size: 0.82rem;
    cursor: pointer;
    font-family: inherit;
}
.due-btn.primary {
    background: var(--brand);
    color: #fff;
    border-color: var(--brand);
    font-weight: 600;
}
.due-btn.ghost {
    color: var(--text-muted);
}
.due-btn:hover {
    filter: brightness(0.97);
}

/* اللوحة السفلية */
.teacher-panel {
    position: fixed;
    left: 0;
    right: 0;
    bottom: 5.2rem;
    z-index: 45;
    width: min(640px, 96vw);
    margin: 0 auto;
    display: flex;
    flex-direction: column;
    max-height: min(56vh, 470px);
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 20px;
    box-shadow: var(--shadow-lg);
    color: var(--text);
    font-family: 'Segoe UI', Tahoma, sans-serif;
    overflow: hidden;
}
.tpx-head {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.6rem 0.85rem;
    border-bottom: 1px solid var(--border);
    background: var(--surface-2);
}
.tpx-title {
    font-weight: 700;
    font-size: 0.92rem;
    white-space: nowrap;
}
.scope-picker {
    display: inline-flex;
    gap: 0.25rem;
    margin-inline-start: auto;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 999px;
    padding: 0.15rem;
}
.seg {
    border: none;
    background: transparent;
    color: var(--text-muted);
    border-radius: 999px;
    padding: 0.28rem 0.7rem;
    font-size: 0.8rem;
    cursor: pointer;
    font-family: inherit;
    white-space: nowrap;
}
.seg.on {
    background: var(--brand);
    color: #fff;
    font-weight: 600;
}
.tpx-close {
    flex: none;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    border: 1px solid var(--border);
    background: var(--surface);
    color: var(--text-muted);
    cursor: pointer;
}
.tpx-close:hover {
    color: var(--text);
}

.range-row {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    flex-wrap: wrap;
    padding: 0.6rem 0.85rem 0;
}
.range-field {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.82rem;
    color: var(--text-muted);
}
.range-field select {
    font-family: inherit;
    font-size: 0.82rem;
    padding: 0.3rem 0.5rem;
    border: 1px solid var(--border);
    border-radius: 10px;
    background: var(--surface);
    color: var(--text);
    max-width: 42vw;
}
.range-count {
    font-size: 0.78rem;
    color: var(--brand-700);
    font-weight: 700;
}

.tpx-tabs {
    display: flex;
    gap: 0.2rem;
    padding: 0.6rem 0.6rem 0;
}
.tpx-tabs button {
    flex: 1;
    border: none;
    background: transparent;
    color: var(--text-muted);
    border-radius: 10px 10px 0 0;
    padding: 0.5rem 0.4rem;
    font-size: 0.82rem;
    cursor: pointer;
    font-family: inherit;
    border-bottom: 2px solid transparent;
}
.tpx-tabs button.on {
    color: var(--brand);
    font-weight: 700;
    border-bottom-color: var(--brand);
    background: var(--brand-soft);
}

.tpx-body {
    flex: 1;
    overflow-y: auto;
    padding: 0.85rem;
}
.tab {
    display: flex;
    flex-direction: column;
    gap: 0.8rem;
}

/* الدورة */
.stepper {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.2rem;
    flex-wrap: wrap;
}
.step {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.15rem;
    min-width: 62px;
    border: 1px solid var(--border);
    background: var(--surface-2);
    color: var(--text-muted);
    border-radius: 12px;
    padding: 0.5rem 0.4rem;
    cursor: pointer;
    font-family: inherit;
    transition: all 0.15s;
}
.step-ico {
    font-size: 1.15rem;
}
.step-title {
    font-size: 0.78rem;
    font-weight: 600;
}
.step.on {
    background: var(--brand);
    color: #fff;
    border-color: var(--brand);
}
.step.done {
    background: var(--brand-soft);
    color: var(--brand);
    border-color: var(--brand-200);
}
.step-arrow {
    color: var(--text-muted);
    direction: ltr;
}
.step-hint {
    text-align: center;
    font-size: 0.86rem;
    color: var(--text);
    background: var(--surface-2);
    border-radius: 12px;
    padding: 0.7rem 0.9rem;
    margin: 0;
    line-height: 1.6;
}

/* الحقول والخيارات */
.field {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}
.field-label {
    font-size: 0.82rem;
    color: var(--text-muted);
}
.opts {
    display: flex;
    gap: 0.35rem;
    flex-wrap: wrap;
}
.opt {
    min-width: 42px;
    border: 1px solid var(--border);
    background: var(--surface);
    color: var(--text);
    border-radius: 10px;
    padding: 0.4rem 0.7rem;
    font-size: 0.88rem;
    font-weight: 600;
    cursor: pointer;
    font-family: inherit;
}
.opt.on {
    background: var(--brand);
    color: #fff;
    border-color: var(--brand);
}
.switch-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.88rem;
    color: var(--text);
    cursor: pointer;
}
.switch-row input {
    width: 18px;
    height: 18px;
    accent-color: var(--brand);
}
.repeat-status {
    font-size: 0.84rem;
    color: var(--brand-700);
    background: var(--brand-soft);
    border-radius: 10px;
    padding: 0.5rem 0.7rem;
    text-align: center;
}
.tab-note {
    font-size: 0.78rem;
    color: var(--text-muted);
    line-height: 1.6;
    margin: 0;
}

.time-row {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    font-size: 0.86rem;
    color: var(--text);
}
.time-row input {
    font-family: inherit;
    font-size: 0.9rem;
    padding: 0.35rem 0.6rem;
    border: 1px solid var(--border);
    border-radius: 10px;
    background: var(--surface);
    color: var(--text);
}

/* الأزرار */
.row-actions {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    border: 1px solid var(--border);
    background: var(--surface);
    color: var(--text);
    border-radius: 12px;
    padding: 0.55rem 1rem;
    font-size: 0.88rem;
    font-weight: 600;
    cursor: pointer;
    font-family: inherit;
}
.btn:disabled {
    opacity: 0.4;
    cursor: default;
}
.btn.primary {
    background: var(--brand);
    color: #fff;
    border-color: var(--brand);
}
.btn.ghost {
    background: transparent;
    color: var(--text-muted);
}
.btn.danger {
    background: #c0392b;
    color: #fff;
    border-color: #c0392b;
}
.btn.big {
    flex: 1;
    padding: 0.7rem 1rem;
}
.btn:hover:not(:disabled) {
    filter: brightness(0.97);
}

/* خطط الجدولة */
.plans {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    margin-top: 0.3rem;
}
.plans-title {
    font-size: 0.82rem;
    font-weight: 700;
    color: var(--text-muted);
}
.plan {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 0.55rem 0.7rem;
    background: var(--surface-2);
}
.plan.due {
    border-color: var(--brand);
    box-shadow: 0 0 0 1px var(--brand-200);
}
.plan-main {
    flex: 1;
    min-width: 0;
}
.plan-label {
    font-size: 0.86rem;
    font-weight: 600;
    color: var(--text);
}
.plan-meta {
    font-size: 0.75rem;
    color: var(--text-muted);
    margin-top: 0.15rem;
}
.plan-actions {
    display: flex;
    gap: 0.25rem;
    flex: none;
}
.icon-btn {
    width: 32px;
    height: 32px;
    border: 1px solid var(--border);
    background: var(--surface);
    border-radius: 9px;
    cursor: pointer;
    font-size: 0.9rem;
    display: grid;
    place-items: center;
}
.icon-btn.ok {
    color: var(--brand);
}
.icon-btn.del {
    color: #c0392b;
}
.icon-btn:hover {
    background: var(--surface-2);
}
.sched-empty {
    font-size: 0.82rem;
    color: var(--text-muted);
    text-align: center;
    padding: 0.6rem;
}

/* المراجعة اللحظية — الشارة والبطاقة */
.flash-badge {
    position: fixed;
    top: 5.2rem;
    left: 50%;
    transform: translateX(-50%);
    z-index: 30;
    background: var(--brand);
    color: #fff;
    border-radius: 999px;
    padding: 0.35rem 0.95rem;
    font-size: 0.85rem;
    font-weight: 700;
    box-shadow: var(--shadow-md);
    font-family: 'Segoe UI', Tahoma, sans-serif;
}
.flash-overlay {
    position: fixed;
    inset: 0;
    z-index: 50;
    display: grid;
    place-items: center;
    pointer-events: none;
}
.flash-card {
    pointer-events: auto;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.8rem;
    background: var(--glass);
    backdrop-filter: saturate(180%) blur(18px);
    -webkit-backdrop-filter: saturate(180%) blur(18px);
    border: 1px solid var(--glass-brd);
    border-radius: 22px;
    padding: 1.4rem 1.8rem;
    box-shadow: var(--shadow-lg);
    font-family: 'Segoe UI', Tahoma, sans-serif;
}
.flash-ring {
    width: 74px;
    height: 74px;
    border-radius: 50%;
    display: grid;
    place-items: center;
    border: 4px solid var(--brand);
    color: var(--brand);
    font-size: 1.8rem;
    font-weight: 800;
}
.flash-hint {
    margin: 0;
    font-size: 1rem;
    font-weight: 600;
    color: var(--text);
}
.flash-actions {
    display: flex;
    gap: 0.5rem;
}

.page-wrap.teacher-pad {
    padding-bottom: min(60vh, 30rem);
}

/* الجوال */
@media (max-width: 640px) {
    .topbar {
        padding: 0.5rem 0.9rem;
    }
    .chip {
        font-size: 0.72rem;
        padding: 0.2rem 0.6rem;
    }
    .page-wrap {
        padding: 0.9rem 0.5rem 6rem;
    }
    .teacher-panel {
        bottom: 4.6rem;
        border-radius: 18px 18px 0 0;
        width: 100vw;
        max-height: 62vh;
    }
    .page-wrap.teacher-pad {
        padding-bottom: 66vh;
    }
    .tpx-head {
        flex-wrap: wrap;
    }
    .scope-picker {
        margin-inline-start: 0;
    }
    .range-field select {
        max-width: 38vw;
    }
    .nav {
        display: none;
    }
    /* إطار أنحف على الجوال */
    .page-column {
        width: 97vw;
    }
    .mushaf-page {
        border-width: 20px;
        padding: 0.7rem 0.7rem 0.9rem;
    }
    .page-topbar {
        padding: 0 0.6rem 0.3rem;
    }
    .pt-surah {
        font-size: 0.95rem;
    }
    .pt-juz {
        font-size: 0.85rem;
    }
    .surah-name {
        font-size: 1.2rem;
    }
    .basmalah {
        font-size: 1.1rem;
    }
    .dock {
        gap: 0.3rem;
        padding: 0.4rem 0.5rem;
    }
    .pinfo {
        max-width: 40vw;
    }
    .drawer {
        width: 100vw;
        padding: 1.1rem;
    }
    .vtext {
        font-size: 1.45rem;
    }
}
</style>
