# Watad Unified Engine — نسخة مُصلَّحة

`Watad_Unified_Engine_Brand_KB_driven.json` — استوردها في n8n بـ **Import from File**.
(130 عقدة ← 142 عقدة. الاسم والـ `versionId` والـ credentials زي ما هي، فالاستيراد بيحدّث نفس الـ workflow.)

---

## ⚠️ لازم تتعمل في Google Sheet قبل التشغيل

| التبويب | التغيير | ليه |
|---|---|---|
| `Leads` | إعادة تسمية العمود `sourse` → `source` | تصحيح الـ typo (إصلاح ٩). لو ما اتعملش، `GS Write Leads` و `Upsert Lead (CRM)` هيعملوا عمود جديد |
| `Content` | إضافة عمود `Drive_Photo_Link` | إصلاح ٨ |
| `Content` | إضافة عمود `Drive_Video_Link` | إصلاح ٨ |
| `Brand_KB` | التأكد إن كل براند له صف نشط | بقى المصدر الوحيد للمحتوى كمان، مش الردود بس |

### أعمدة `Brand_KB` اللي بيقرأها المحرك دلوقتي
`brand`, `brand_name`, `active`, `zernio_profile_id`, `zernio_profile_names`, `zernio_account_ids`,
`kb`, `services`, `faq`, `never_say`, `tone`, `audience`, `focus`, `anti_hype`, `visual_style`,
`hashtags`, `escalation_email`, `allow_auto_reply`

قيم `publish_status` الجديدة في `Content`: `NO_ACCOUNT`.
قيم `status` الجديدة في `Reply_Approval`: `REJECTED`.

---

## الإصلاحات العشرة

| # | المشكلة | الحل |
|---|---|---|
| ١ | مصدرا حقيقة متعارضان للبراندات | عقدتين `Read Brand_KB (Content)` و `Read Brand_KB (Design)` بقوا يغذّوا `Map Companies (Daily)` و `Resolve Design Brief`. الشيت بيغلب حقل بحقل، والـ map المكتوب في الكود بقى fallback بس. كل brief بيحمل `brand_source` عشان تعرف مين اللي جاب القيمة |
| ٢ | الكاروسيل بينشر صورة واحدة | `Compute Schedule` بقى يقرأ `Photo_Links` كله ويبني `customMedia` بكل الشرائح، بحد أقصى لكل منصة (IG/FB ١٠، LinkedIn ٩، Twitter ٤) |
| ٣ | Error كل ٥ دقايق + صفوف عالقة على `PUBLISHING` | مفيش `throw`. الصفوف اللي مالهاش حساب بتخرج بـ `publishable:false` وتروح `Mark Unpublishable` → `NO_ACCOUNT` + سبب مكتوب، فالقفل بيتفك |
| ٤ | كل الصفوف بتتجدول في نفس الدقيقة | `SPREAD_MINUTES = 45` — كل صف بياخد slot لوحده |
| ٥ | فيديو SORA بيتولّد قبل الموافقة | التوليد اتنقل لمسار النشر: `Claim` → `Prepare Publish Rows` → `Needs Video?` → SORA. مفيش فيديو غير للصف المعتمد اللي فعلاً عنده `tiktok_caption` و `video_prompt` ولسه مفيش `Video_Link` |
| ٦ | "Daily" بيشتغل كل ١٢ ساعة | بقى يومي الساعة ٨ صباحاً |
| ٧ | إيميلات hardcoded | إشعارات الموافقة بتروح على `escalation_email` بتاع البراند من `Brand_KB` |
| ٨ | رفع Drive نهاية مسدودة | `Save Drive Photo Link` و `Save Drive Video Link` بيكتبوا اللينك في `Content` |
| ٩ | `sourse` | بقى `source` في العقدتين |
| ١٠ | ردود معتمدة بتتبعت بدون فحص | `Validate Approved Replies` بيعيد نفس بوابة الأمان بتاعة `Parse Decision` (٧٠٠ حرف، بدون لينكات، بدون نص injection، وجود `account_id` وهدف صالح) قبل الـ claim. الفاشل بيروح `Mark Reply Rejected` |

---

## العقد الجديدة

**محرك المحتوى:** `Read Brand_KB (Content)` · `Save Drive Photo Link`
**Design Studio:** `Read Brand_KB (Design)`
**مسار النشر:** `Prepare Publish Rows` · `Needs Video?` · `Attach Video Link` · `Save Drive Video Link` · `Publishable?` · `Mark Unpublishable`
**مسار الردود:** `Validate Approved Replies` · `Sendable Reply?` · `Mark Reply Rejected`

---

## الاختبارات

كل عقد الكود المعدّلة اتشغّلت على بيانات وهمية (٥٠ assertion، كلها ناجحة): تغطّي وضع الـ daily والـ form،
الشيت الفاضي، الصف الموقوف (`active=no`)، الكاروسيل، الفيديو الطازج مقابل القديم، التباعد الزمني،
الصفوف غير القابلة للنشر، ورفض الردود غير الآمنة.

## ملاحظات تشغيلية

- `Notify for Approval` بقى نهاية مسار التوليد — لا يوجد أي استهلاك لـ SORA قبل الموافقة.
- قفل `PUBLISHING` هو اللي بيمنع تداخل تشغيلتين على نفس الصف أثناء توليد الفيديو (SORA بياخد دقائق والـ trigger كل ٥ دقايق).
- `Compute Schedule` بقى بيستخدم `console.log` بدل `throw` — الأسباب بتظهر في لوج التنفيذ.
