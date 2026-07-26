/**
 * جدولة المراجعة الدورية للحفظ — تخزين محلي (localStorage) بلا خادم.
 *
 * كل «خطة مراجعة» تحدّد صفحة/مقطعاً + كل كم يوم يراجَع + وقت التذكير.
 * التنبيه يعمل بثلاث طرق متكاملة:
 *   1) لافتة داخل الموقع عند فتحه إذا حان وقت مراجعة (duePlans).
 *   2) إشعار المتصفح (Notification API) أثناء بقاء التبويب مفتوحاً.
 *   3) تصدير ملف تقويم .ics (بتكرار RRULE) لتذكير موثوق على الجوال.
 */

export interface ReviewPlan {
    id: string;
    label: string; // نص المراجعة، مثل «مراجعة صفحة ٥ — سورة البقرة»
    page: number; // الصفحة التي تُفتح عند المراجعة
    everyDays: number; // الدورية بالأيام (1 = يومياً، 7 = أسبوعياً…)
    time: string; // وقت التذكير "HH:MM" (24 ساعة)
    createdAt: number; // وقت الإنشاء (ms)
    nextDue: number; // موعد المراجعة القادم (ms)
    lastDone?: number; // آخر مراجعة أُنجزت (ms)
    doneCount?: number; // عدد مرات المراجعة المنجزة
}

const STORE_KEY = 'quran-review-plans';

/** توليد معرّف بسيط فريد للخطة. */
export function newId(): string {
    return `p${Date.now().toString(36)}${Math.floor(Math.random() * 1e6).toString(36)}`;
}

export function loadPlans(): ReviewPlan[] {
    try {
        const raw = localStorage.getItem(STORE_KEY);
        if (!raw) return [];
        const arr = JSON.parse(raw);
        return Array.isArray(arr) ? (arr as ReviewPlan[]) : [];
    } catch {
        return [];
    }
}

export function savePlans(plans: ReviewPlan[]): void {
    try {
        localStorage.setItem(STORE_KEY, JSON.stringify(plans));
    } catch {
        /* التخزين ممتلئ أو محظور — نتجاهل */
    }
}

/**
 * موعد المراجعة القادم: أقرب يوم عند `time` يقع بعد `from`.
 * إن كان وقت اليوم لم يمضِ بعد → اليوم، وإلا فبعد `everyDays`.
 */
export function computeNextDue(
    everyDays: number,
    time: string,
    from: number = Date.now(),
): number {
    const [h, m] = time.split(':').map((n) => parseInt(n, 10));
    const base = new Date(from);
    const due = new Date(
        base.getFullYear(),
        base.getMonth(),
        base.getDate(),
        h || 0,
        m || 0,
        0,
        0,
    );
    if (due.getTime() <= from) {
        due.setDate(due.getDate() + Math.max(1, everyDays));
    }
    return due.getTime();
}

/** تعليم الخطة كمُراجَعة: يقدّم nextDue بمقدار الدورية ويزيد العدّاد. */
export function markDone(
    plan: ReviewPlan,
    now: number = Date.now(),
): ReviewPlan {
    const next = new Date(now);
    next.setDate(next.getDate() + Math.max(1, plan.everyDays));
    const [h, m] = plan.time.split(':').map((n) => parseInt(n, 10));
    next.setHours(h || 0, m || 0, 0, 0);
    return {
        ...plan,
        lastDone: now,
        doneCount: (plan.doneCount ?? 0) + 1,
        nextDue: next.getTime(),
    };
}

/** تأجيل خطة بعدد من الساعات (زر «أجّل»). */
export function snooze(
    plan: ReviewPlan,
    hours: number,
    now: number = Date.now(),
): ReviewPlan {
    return { ...plan, nextDue: now + hours * 3600_000 };
}

/** الخطط التي حان موعدها (nextDue ≤ now). */
export function duePlans(
    plans: ReviewPlan[],
    now: number = Date.now(),
): ReviewPlan[] {
    return plans.filter((p) => p.nextDue <= now);
}

/** وصف مختصر لموعد المراجعة القادم بالعربية. */
export function formatDue(nextDue: number, now: number = Date.now()): string {
    const dayMs = 86_400_000;
    const startOfToday = new Date(now);
    startOfToday.setHours(0, 0, 0, 0);
    const startOfDue = new Date(nextDue);
    startOfDue.setHours(0, 0, 0, 0);
    const diffDays = Math.round(
        (startOfDue.getTime() - startOfToday.getTime()) / dayMs,
    );
    const time = new Date(nextDue).toLocaleTimeString('ar', {
        hour: '2-digit',
        minute: '2-digit',
    });
    if (nextDue <= now) return `حان الآن (${time})`;
    if (diffDays <= 0) return `اليوم ${time}`;
    if (diffDays === 1) return `غداً ${time}`;
    if (diffDays === 2) return `بعد غد ${time}`;
    return `بعد ${diffDays} أيام — ${time}`;
}

/** وصف الدورية بالعربية. */
export function everyLabel(days: number): string {
    if (days === 1) return 'يومياً';
    if (days === 2) return 'كل يومين';
    if (days === 7) return 'أسبوعياً';
    if (days === 3) return 'كل ٣ أيام';
    return `كل ${days} أيام`;
}

function pad(n: number): string {
    return String(n).padStart(2, '0');
}
/** تنسيق تاريخ محلي «عائم» لملف ICS: YYYYMMDDTHHMMSS. */
function icsLocal(ms: number): string {
    const d = new Date(ms);
    return `${d.getFullYear()}${pad(d.getMonth() + 1)}${pad(d.getDate())}T${pad(d.getHours())}${pad(d.getMinutes())}00`;
}

/** بناء محتوى ملف تقويم (.ics) بحدث متكرّر + منبّه للخطة. */
export function buildIcs(plan: ReviewPlan, origin: string = ''): string {
    const dt = icsLocal(plan.nextDue);
    const stamp = icsLocal(Date.now());
    const url = origin
        ? `${origin}/mushaf/${plan.page}`
        : `/mushaf/${plan.page}`;
    const desc = `مراجعة الحفظ — افتح: ${url}`;
    const lines = [
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'PRODID:-//QU Mushaf//Review//AR',
        'CALSCALE:GREGORIAN',
        'BEGIN:VEVENT',
        `UID:${plan.id}@qu-mushaf`,
        `DTSTAMP:${stamp}`,
        `DTSTART:${dt}`,
        `DURATION:PT15M`,
        `RRULE:FREQ=DAILY;INTERVAL=${Math.max(1, plan.everyDays)}`,
        `SUMMARY:${escapeIcs(plan.label)}`,
        `DESCRIPTION:${escapeIcs(desc)}`,
        `URL:${url}`,
        'BEGIN:VALARM',
        'ACTION:DISPLAY',
        `DESCRIPTION:${escapeIcs(plan.label)}`,
        'TRIGGER:PT0M',
        'END:VALARM',
        'END:VEVENT',
        'END:VCALENDAR',
    ];
    return lines.join('\r\n');
}

function escapeIcs(s: string): string {
    return s.replace(/([,;\\])/g, '\\$1').replace(/\n/g, '\\n');
}

/** تنزيل خطة كملف .ics. */
export function downloadIcs(plan: ReviewPlan): void {
    const origin = typeof window !== 'undefined' ? window.location.origin : '';
    const blob = new Blob([buildIcs(plan, origin)], {
        type: 'text/calendar;charset=utf-8',
    });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `review-page-${plan.page}.ics`;
    a.click();
    URL.revokeObjectURL(url);
}
