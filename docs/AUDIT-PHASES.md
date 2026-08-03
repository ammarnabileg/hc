# 🧭 خطّة الأوديت — مراحل التنفيذ

> **المرجع الحاكم:** `دستور اساسي.md` (v5.1). المراحل **مشتقّة من بنية الدستور نفسه**
> لا من تقسيمٍ مخترَع: كلّ مرحلة تقابل نطاقًا متّصلًا من أقسامه.
>
> **شكل التوثيق:** القاعدة **2.12** — لكلّ مجلّد `_STATUS.md` فيه *الغرض · المُنجَز ·
> المتبقّي · الجاري الآن · التبعيّات · آخر تحديث*. والأقسام التلقائيّة يكتبها
> `php artisan docs:status` من الكود، أمّا **المتبقّي** و**الجاري الآن** فبيد المدقّق.
> فالأوديت **يُسجَّل في مكانه المنصوص عليه** ولا يُستحدَث ملفٌّ جديد.
>
> **قاعدة الإبلاغ (الدستور):** لا يُكتَب **إنجازٌ إلّا بدليل تشغيل**. و«الحزمة خضراء»
> ليست دليلًا على المطابقة.

---

## المراحل الاثنتا عشرة

| # | المرحلة | أقسام الدستور | السطور | المجلّدات المملوكة |
|---|---|---|---|---|
| 1 | الأساس وقواعد البناء | 2 كاملًا | 41–614 | `Services/{Setup,Ui,Ux,Images,Onboarding,Security,Notifications}` · `Controllers/{Setup,Auth,Onboarding,Ui}` · `views/{setup,onboarding,partials,components,auth}` · `resources/{css,js}` |
| 2 | المحتوى والتعلّم والاختبارات | 3 · 4 · 5 · 6 | 615–735 | `Services/Learning` · `Controllers/Trainee` · `views/{learning,exams}` |
| 3 | التلعيب والتحديات | 7 · 7.1–7.6 · 15 | 736–847 · 3254–3385 | `Services/{Gamification,Gamification/Wars,Referral,Engagement}` · `views/{challenges,achievements,referral,ambassadors}` |
| 4 | الشهادات والسيرة والبروفايل والشكاوى | 8 · 9 · 9.1 · 10 · 11 | 848–1019 | `Services/Certificates` · `views/{cv,profile,attestations,complaints,certificates}` |
| 5 | الأدوار والصلاحيّات | 12.0 · 12.1 · 12.2 | 1020–2306 | `Support/Access` · `Http/Middleware` · `database/data` · `views/admin/roles` |
| 6 | شاشات لوحة الإدارة | 12.3–12.14 | 2307–2643 | `Controllers/Admin` · `Services/{Admin,Admin/*}` · `views/admin/**` (عدا `roles` و`screens24`) |
| 7 | السايد بار والبحث والتعليمات والفعاليّات وداشبورد المستخدم | 13 · 13.1–13.3 · 14 | 2644–2733 · 3229–3253 | `Services/{Home,Dashboard,Events}` · `views/{dashboard,events,announcements,search,help}` |
| 8 | رحلة المتطوّع والتوظيف | 13.4 | 2734–3228 | `Services/Volunteer/{People,Profile,Retention,Org}` · `Controllers/Volunteer` · `views/volunteer/**` (عدا مجلّدات المرحلة 11) |
| 9 | التجارة: التسعير والمتجر والبندل والمحفظة والمكتبة | 16 · 17 · 18 · 19 · 20 | 3386–3576 | `Services/{Store,Wallet,Library}` · `views/{store,wallet,library}` |
| 10 | النموّ والتوسّع والاكتساب | 21 · 21.1–21.3 · 22 | 3577–3713 | `Services/{Growth,Ads}` · `Controllers/Growth` · `views/{growth,public}` |
| 11 | دورة العمل الموحّدة | 23 كاملًا | 3714–4359 | `Services/Volunteer/{Goals,Tasks,Escalation,Contributions,Objections,Meetings}` · `views/volunteer/{goals,tasks,escalations,meetings}` |
| 12 | دليل شكل الصفحات | 24 كاملًا | 4360–5889 | `Controllers/AdminScreens` · `Services/AdminScreens` · `views/admin/screens24` |

**ما هو خارج المراحل عمدًا:** القسم **1** (الهويّة — منهج عمل لا ميزة تُبنى) و**25**
(سجلّ القرارات — أرشيف). و**22** «نقاط مؤجّلة» تُدقَّق داخل المرحلة 10 بوصفها قائمة
مؤجَّلات يجب أن تبقى مؤجَّلةً بإقرارٍ لا بسهو.

---

## قواعد الأوديت (مُلزِمة لكلّ مرحلة)

1. **اقرأ نطاقك من الدستور كاملًا** — بالسطور المذكورة، لا بالعناوين.
2. **الإثبات بالتشغيل لا بالقراءة:** طلب HTTP · استعلام قاعدة · تشغيل أمر · حمولة
   مزوَّرة. وقراءة الكود وحدها **لا تُثبِت** أنّ القاعدة مطبَّقة.
3. **ما لم يُثبَت فهو «متبقٍّ»** — لا يُكتَب مُنجَزًا لأنّه «يبدو موجودًا».
4. **حدّث `⬜ المتبقّي` و`🔄 الجاري الآن`** في `_STATUS.md` لكلّ مجلّد تملكه — بالصدق،
   وبحذف ما صار قديمًا.
5. **لا تلمس مجلّدًا لا تملكه**، ولا الأقسام التلقائيّة بين `تلقائيّ:بداية/نهاية`.
6. **لا إصلاح أثناء الأوديت** — الأوديت جردٌ لا بناء. ما وجدته يُسجَّل ويُرفَع.

---

## 🕒 آخر تحديث
- 2026-08-03 — Claude.
