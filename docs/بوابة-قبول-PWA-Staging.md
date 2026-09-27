# بوابة قبول PWA على Staging

## الهدف

تحويل قرار الإطلاق من انطباع يدوي إلى تقرير Go/No-Go قابل للحفظ والمراجعة. لا تسجل البوابة أي اختبار يدوي أو SLO كناجح تلقائيًا.

## تشغيل الخطة محليًا

```bash
npm run test:pwa:staging-plan
```

يتحقق هذا الأمر من اكتمال عقد البوابة فقط، ولا يدعي اختبار Staging.

## تشغيل البوابة على Staging

```bash
PWA_ACCEPT_ANDROID=pass \
PWA_ACCEPT_PUSH=pass \
PWA_ACCEPT_OUTBOX=pass \
PWA_ACCEPT_ACCOUNT_ISOLATION=pass \
PWA_ACCEPT_ROLLBACK=pass \
PWA_SLO_P50_P95_P99=pass \
PWA_SLO_HTTP_4XX_5XX_RATE=pass \
PWA_SLO_QUEUE_LAG_AND_FAILED_JOBS=pass \
PWA_SLO_PUSH_DELIVERY_RATE=pass \
PWA_SLO_OUTBOX_CONFLICT_AND_FAILURE_RATE=pass \
PWA_SLO_INITIAL_BUNDLE_AND_PWA_OPEN_TIME=pass \
npm run test:pwa:staging -- https://staging.example.com storage/app/pwa-acceptance/run.md
```

لا تضبط متغيرًا على `pass` إلا بعد إرفاق دليل فعلي في سجل الاختبار الخارجي: الجهاز والمتصفح والوقت والحساب التجريبي والنتيجة أو رابط لوحة القياس.

## البوابات الآلية

- Build الإنتاج.
- عقد PWA ومرحلة Service Worker.
- Outbox وضوابط التعطيل وDeep Links والإشعارات.
- فحص ملفات النشر عبر HTTPS.
- اختبار Chrome headless لتفعيل Worker والـCache وOffline shell ومنع Mutation دون شبكة.

يتوقف التسلسل عند أول فشل، وتصبح الخطوات التالية `BLOCKED` بدل إعطائها نجاحًا وهميًا.

## الأدلة اليدوية

- تثبيت وتحديث على Android فعلي.
- Push حقيقي والنقر على Deep Link لكل دور.
- انقطاع وعودة اتصال لمسودة الجرد دون تكرار.
- تبديل الحساب دون تسرب بيانات محلية.
- تجربة Kill Switches وخطة الرجوع.

## القرار

- `GO`: كل الفحوص الآلية والأدلة اليدوية وأدلة SLO هي `PASS`.
- `NO-GO`: أي `FAIL` أو `PENDING` أو `BLOCKED`.

التقرير الافتراضي يكتب في `storage/app/pwa-acceptance/latest.md` ولا يُعد ملفًا مصدرًا للالتزام. تحفظ نسخة معتمدة خارج مجلد التشغيل عند توقيع قرار الإطلاق.

## قياس خط أساس الأداء

تشغل البوابة القياس تلقائيًا قبل فحص ملفات النشر والمتصفح. ويمكن تشغيله منفردًا:

```bash
PWA_PERFORMANCE_SAMPLES=20 \
npm run test:pwa:performance -- https://staging.example.com storage/app/pwa-acceptance/performance.json
```

ينتج القياس:

- p50 وp95 وp99 لزيارة الصفحة العامة دون Cache.
- نسبة أخطاء العينات وحالات HTTP.
- الحجم الإجمالي لحزم JavaScript وCSS المكتشفة من HTML المنشور.
- صحة Manifest وService Worker وOffline shell وأصل SVG.
- العينات الخام ووقت القياس والمنهجية داخل JSON.

نجاح جمع خط الأساس لا يعني نجاح SLO. تبقى أدلة SLO في تقرير القبول `PENDING` حتى يراجع الفريق القياسات ويعتمد حدودًا رقمية ثم يرفق دليل اجتيازها.
