# Watad Unified Engine

`Watad_Unified_Engine_Brand_KB_driven.json` — استوردها في n8n بـ **Import from File**.
الاسم والـ `versionId` والـ credentials زي ما هي، فالاستيراد بيحدّث نفس الـ workflow.

**130 عقدة أصلاً ← 180 عقدة.**

---

# ⚠️ الإعداد المطلوب قبل التشغيل

## ١. رابط الموافقة (لازم — من غيره أزرار الإيميل مش هتشتغل)

استبدل `https://N8N-HOST.example.com/webhook/watad-approval` بالـ production webhook URL
بتاع الـ instance في **٤ أماكن**:

| المكان | إزاي |
|---|---|
| `Build Approval Page` | ثابت `APPROVAL_BASE` في أول الكود |
| `Notify for Approval` | في الـ HTML بتاع الإيميل (زرّين) |
| `Notify for Approval1` | نفس الشيء |
| `Send Approval Email1` | نفس الشيء |

الـ path بتاع الـ webhook هو `watad-approval` — افتح عقدة `Webhook: Approval` وانسخ الـ Production URL منها.

## ٢. تبويبات جديدة

| التبويب | الأعمدة |
|---|---|
| `Performance` | `run_id`, `brand`, `zernio_post_id`, `stage`, `pulled_at`, `topic_title`, `platforms`, `impressions`, `reach`, `likes`, `comments`, `shares`, `saves`, `clicks`, `views`, `follows`, `engagement`, `raw` |
| `Events_Log_Archive` | **نفس رؤوس أعمدة `Events_Log` بالظبط** |

## ٣. أعمدة جديدة

**`Content`:**
`Drive_Photo_Link`, `Drive_Video_Link`, `compliance_status`, `compliance_notes`,
`approval_token`, `approved_at`, `perf_stage`, `perf_last_pull`, `perf_engagement`, `perf_impressions`

**`Reply_Approval`:**
`approval_token`, `approved_at`

**`Leads`:** إعادة تسمية `sourse` → `source`

## ٤. `Brand_KB` بقى مركز النظام

كل المحرّكات (المحتوى، الديزاين، الردود) بتسأل نفس الـ **Brand KB Registry** دلوقتي.
لازم كل براند يكون له صف نشط، وإلا الفلو بيقع على الـ fallback المكتوب في الكود.

الأعمدة اللي بتتقرأ: `brand`, `brand_name`, `active`, `zernio_profile_id`, `zernio_profile_names`,
`zernio_account_ids`, `kb`, `services`, `faq`, `never_say`, `tone`, `audience`, `focus`,
`anti_hype`, `visual_style`, `hashtags`, `escalation_email`, `allow_auto_reply`

`never_say` بقى له أثر حقيقي: أي مصطلح فيه بيوقف المحتوى قبل توليد الصور والفيديو.

---

# المرحلة الأولى — الإصلاحات العشرة

| # | المشكلة | الحل |
|---|---|---|
| ١ | مصدرا حقيقة متعارضان للبراندات | Brand_KB بقى المصدر الوحيد (اتوسّع في المرحلة التانية للـ Registry) |
| ٢ | الكاروسيل بينشر صورة واحدة | `Photo_Links` كله بيتبني في `customMedia`، بحد أقصى لكل منصة (IG/FB ١٠، LinkedIn ٩، Twitter ٤) |
| ٣ | Error كل ٥ دقايق + صفوف عالقة على `PUBLISHING` | مفيش `throw`؛ الصفوف بتتعلّم `NO_ACCOUNT` والقفل بيتفك |
| ٤ | كل الصفوف بتتجدول في نفس الدقيقة | `SPREAD_MINUTES = 45` |
| ٥ | فيديو SORA بيتولّد قبل الموافقة | التوليد اتنقل لمسار النشر بعد `Claim` |
| ٦ | "Daily" بيشتغل كل ١٢ ساعة | بقى يومي الساعة ٨ صباحاً |
| ٧ | إيميلات hardcoded | `escalation_email` بتاع البراند |
| ٨ | رفع Drive نهاية مسدودة | اللينكات بتتسجّل في `Content` |
| ٩ | `sourse` | بقى `source` |
| ١٠ | ردود معتمدة بتتبعت بدون فحص | `Validate Approved Replies` بيعيد بوابة الأمان قبل الإرسال |

---

# المرحلة التانية — الإضافات

## ١. كل حاجة بتعدّي على «العقل» (Brand KB Registry)

قبل: محرك الردود بس هو اللي بيسأل الـ Registry. المحتوى والديزاين كانوا بيقروا الشيت بنفسهم.
دلوقتي التلاتة بيمرّوا على نفس الـ sub-workflow.

```
Zernio: List Profiles ┐
Analyze Reference Image ├→ Extract Brand Targets → Brand KB Registry (Content) → Read Recent Topics → Map Companies (Daily)
Has Image? (no) ───────┘

Design Studio (Form) → Design Target → Brand KB Registry (Design) → Resolve Design Brief
```

الـ Registry بيرمي error لو مفيش صف مطابق، فالعقد اتظبطت على `onError: continueRegularOutput`
عشان براند لسه مش مضاف في الشيت يفضل يشتغل من الـ fallback بدل ما الرن كله يموت.
كل brief بيحمل `brand_source` (`registry:profile_id` مثلاً، أو `fallback_code`) عشان تعرف مين اللي جاب القيمة.

## ٢. بوابة امتثال للعلامة قبل أي تكلفة ميديا

```
Parse Content JSON → AI Brand Compliance → Apply Compliance Verdict → Append to Content tab → Compliance OK?
                                                                                                ├ PASS → Presign Image → … → Notify for Approval
                                                                                                └ BLOCKED → Notify Compliance Block
```

نفس فلسفة محرك الردود: **النموذج بيبلّغ، الكود بيقرر**.

`AI Brand Compliance` (claude-sonnet-4-5, temp 0) بياخد الـ KB كمصدر الحقيقة الوحيد ويبلّغ ٤ إشارات:
`unsupported_claims` · `forbidden_content` · `off_brand` · `wrong_language` + `evidence` (نص المخالفة حرفياً).

`Apply Compliance Verdict` بيقرر:
- **فحص حتمي أولاً** (مش بيتلغى بالنموذج): مصطلحات `never_say`، تجاوز ٢٨٠ حرف، موضوع فاضي، ٣ منصات فاضية أو أكتر.
- **بعدين إشارات النموذج**: `unsupported_claims` / `forbidden_content` / `wrong_language` → BLOCKED.
- `off_brand` لوحده **ملاحظة للبني آدم مش حظر** — النبرة حكم تقديري.
- مخرجات النموذج غير المقروءة **مش بتحظر** لوحدها، بتتسجّل كملاحظة.

المحظور مش بيستهلك gpt-image ولا SORA إطلاقاً. صفوف الديزاين بتعدّي على نفس فحص `never_say` الحتمي.

## ٣. ذاكرة مواضيع

`Read Recent Topics` بيقرا تبويب `Content`، و `Map Companies` بياخد آخر ٢٥ عنوان **لنفس البراند**
(بدون تكرار) ويحطهم في الـ prompt كـ «متكررش دول».

## ٤. سحب الأداء من Zernio

```
Trigger: Analytics Pull (كل ٦ ساعات) → Read Scheduled Content → Plan Analytics Pull
  → Zernio: Post Analytics → Parse Analytics → Analytics OK? → Save Performance → Update Content Perf
```

لقطتين لكل بوست: بعد ~٢٤ ساعة و ~٧٢ ساعة من `scheduled_for` (بتوقيت الرياض).
`perf_stage` على الصف هو المؤشّر، فمفيش سحب مكرر.

`GET /api/v1/analytics?postId=…` — **HTTP 202 معناه Zernio لسه بيزامن**، فالصف بيحتفظ بمرحلته
ويتعاد في الدورة الجاية بدل ما يتسجّل صفر.

> الحقول (`impressions`, `reach`, `likes`, `comments`, `shares`, `saves`, `clicks`, `views`, `follows`, `engagement`)
> مأخوذة من توثيق Zernio العام. `Parse Analytics` بيقرا بشكل دفاعي ويجرّب أكتر من شكل استجابة،
> بس **راجع أول سحب فعلي** وتأكد إن الأسماء مطابقة.

## ٥. تدوير `Events_Log`

```
Trigger: Log Rotation (٣ فجراً أول كل شهر) → Read Events_Log (All) → Plan Log Rotation
  → Archive Old Events → Delete Archived Events
```

الصفوف بتتضاف بترتيب زمني، يعني القديم كتلة متصلة في الأعلى.
`Plan Log Rotation` بيمشي من فوق ويقف عند **أول** صف مش قديم كفاية — عشان الحذف بالـ index
ما ياخدش صفوف تانية معاه بالغلط. الاحتفاظ ٩٠ يوم، وبحد أقصى ٢٠٠٠ صف في المرة.
الأرشفة بتحصل **قبل** الحذف، والحذف بيشتغل بس لو الأرشفة نجحت.

## ٦. موافقة بضغطة واحدة

```
Webhook: Approval (GET) → Parse Approval Request → Needs Confirm?
   ├ صفحة تأكيد
   └ Is Content Approval? → Read {Content|Reply} Row → Decide → OK? → Write Decision → Build Approval Page → Respond
```

`?entity=content|reply&run_id=…&token=…&action=approve|reject[&confirm=1]`

ثلاث طبقات حماية:
1. **الضغطة الأولى بتعرض صفحة تأكيد بس** — فاحصات البريد والـ prefetchers بتفتح أول لينك، ولازم ما تغيّرش أي حالة.
2. **token لكل صف** (٢٨ حرف عشوائي) بيتولّد وقت إنشاء الصف ومتخزّن في `approval_token`.
3. **الصف لازم يكون لسه معلّق** — صف اتوافق عليه أو اتنشر مش ممكن يترجع.

بيغطّي المحتوى والديزاين والردود بنفس الـ webhook.

---

# الاختبارات

كل عقد الكود المعدّلة والجديدة اتشغّلت على بيانات وهمية — **١١٩ assertion، كلها ناجحة**
(`test_nodes.js` + `test_v2.js` في مجلد الـ scratchpad).

بتغطّي: وضع daily/form، فشل الـ Registry والرجوع للـ fallback، ذاكرة المواضيع،
حظر `never_say` الحتمي، `off_brand` كملاحظة مش حظر، مراحل سحب الأداء و HTTP 202،
تدوير اللوج بكتلة متصلة، رفض التوكن الخاطئ ومنع إعادة الموافقة، هروب HTML في صفحة الموافقة،
الكاروسيل، التباعد الزمني، والصفوف غير القابلة للنشر.

---

# ملاحظات تشغيلية

- `settings.errorWorkflow` لسه **فاضي**. لو ضبطته على workflow صغير للتنبيهات، أي فشل غير متوقّع هيوصلك.
- قفل `PUBLISHING` هو اللي بيمنع تداخل تشغيلتين أثناء توليد الفيديو (SORA بياخد دقائق والـ trigger كل ٥ دقايق).
- `Read Recent Topics` و `Read Events_Log (All)` بيقروا التبويب كله. التدوير بيتكفّل بـ `Events_Log`؛
  لو `Content` كبر أوي، حوّله لنفس نمط الأرشفة.
- الأداء بيتخزّن بس ولسه مش بيرجع للـ prompt. لو عايز الحلقة تقفل، ضيف عمود `perf_engagement`
  جنب كل عنوان في `recent_topics` جوه `Map Companies (Daily)` — سطر واحد.
