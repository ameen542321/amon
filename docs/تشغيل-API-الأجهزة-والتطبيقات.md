# تشغيل API الأجهزة والتطبيقات

## الهدف والنطاق الأول

هذه الطبقة هي بداية Backend مشترك لتطبيق Flutter أو عميل مخصص. تستخدم Bearer
Tokens قابلة للإبطال ومقيدة بجهاز، ولا تعتمد Cookie أو CSRF بعد تسجيل الدخول.

النطاق الحالي Read-focused:

- تسجيل الدخول والخروج من الجهاز أو جميع الأجهزة.
- بيانات الحساب الحالي.
- إعدادات التطبيق وFeature flags.
- المتاجر المسموحة للحساب.
- كتالوج المنتجات والأقسام والبحث والمزامنة التزايدية.
- إدارة جلسات الأجهزة.
- مركز الإشعارات مع Idempotency للقراءة والإخفاء.

لا توجد مسارات بيع أو تحصيل أو مصروف أو شفت أو نقل أو اعتماد جرد، ولا يوجد Outbox.

## إعداد البيئة

```dotenv
MOBILE_API_TOKEN_LIFETIME_DAYS=30
MOBILE_API_REFRESH_TOKEN_LIFETIME_DAYS=90
MOBILE_API_MAX_DEVICES_PER_ACCOUNT=10
MOBILE_API_MINIMUM_APP_VERSION=1.0.0
```

ثم:

```bash
php artisan migrate --force
php artisan optimize:clear
php artisan api-tokens:cleanup --dry-run
php artisan schedule:list
npm run test:mobile-api
npm run test:mobile-catalog
```

## تسجيل الدخول

```http
POST /api/v1/auth/login
Accept: application/json
Content-Type: application/json

{
  "account_type": "user",
  "email": "owner@example.com",
  "password": "...",
  "device_uuid": "stable-installation-uuid",
  "device_name": "هاتف المتجر",
  "platform": "android",
  "app_version": "1.0.0"
}
```

القيمة `account_type` إما `user` أو `accountant`. رسالة فشل كلمة المرور عامة ولا
تكشف وجود البريد. يعاد رمزا الوصول والتجديد الصريحان مرة واحدة فقط، وتخزن قاعدة البيانات بصمتي SHA-256 فقط.
يرفض الخادم الإصدار الأدنى من `MOBILE_API_MINIMUM_APP_VERSION` بالرمز
`UPDATE_REQUIRED` وحالة `426`، مع بقاء `/app-config` متاحًا للرمز القديم حتى يقرأ
الحد الأدنى المطلوب.

## تجديد الجلسة دون إعادة كلمة المرور

```http
POST /api/v1/auth/refresh
Accept: application/json
Content-Type: application/json

{
  "refresh_token": "carled_refresh_...",
  "device_uuid": "stable-installation-uuid",
  "app_version": "1.0.0"
}
```

يبدل المسار **رمز الوصول ورمز التجديد معًا** داخل معاملة وقفل قاعدة بيانات. لا
يقبل رمز التجديد القديم بعد نجاح التبديل، ولا يقبل الرمز من `device_uuid` مختلف.
يخزن تطبيق الهاتف الرمزَين في Secure Storage ولا يرسلهما إلى Logs أو Analytics.

## استخدام الرمز

```http
Authorization: Bearer carled_...
Accept: application/json
```

يفحص كل طلب:

- انتهاء الرمز أو إبطاله.
- حالة الحساب.
- ارتباط المحاسب بالمالك والمتجر.
- حالة المالك واشتراكه.
- حالة المتجر.
- Ability المطلوبة للمسار.

أي فشل في حالة الحساب يلغي الرمز حتى لا يستمر جهاز قديم في الطلب.

## الأجهزة والإبطال

- `GET /api/v1/devices`: الأجهزة النشطة ويحدد الجهاز الحالي.
- `DELETE /api/v1/devices/{id}`: إلغاء جهاز آخر، ويتطلب Idempotency-Key.
- `POST /api/v1/auth/logout`: إلغاء الجهاز الحالي.
- `POST /api/v1/auth/logout-all`: إلغاء جميع أجهزة الحساب.
- `PUT /api/v1/push-subscription`: ربط OneSignal بالجهاز الحالي.
- `DELETE /api/v1/push-subscription`: إلغاء Push للجهاز الحالي.

إعادة تسجيل الجهاز نفسه تلغي جلسته السابقة وتنشئ رمزًا جديدًا. الحد الافتراضي عشر
جلسات نشطة؛ تلغى الأقدم عند تجاوزه. يلغي الخروج أو الإبطال البعيد رمز التجديد
واشتراك Push معًا. يحتفظ بسجلات الإبطال سبعة أيام للتشخيص، ثم يحذفها
`api-tokens:cleanup`. الجلسة ذات Access Token منتهٍ لا تحذف ما دام Refresh Token
صالحًا.

## الصلاحيات الحالية

| Ability | الاستخدام |
| --- | --- |
| `app:read` | الحساب والإعدادات والمتاجر |
| `catalog:read` | المنتجات والأقسام والبحث والمزامنة |
| `devices:manage` | عرض الأجهزة وإلغاء جهاز آخر |
| `notifications:read` | قائمة الإشعارات والعداد |
| `notifications:write` | تعليم القراءة والإخفاء |
| `push:manage` | تسجيل أو إلغاء Push للجهاز الموثق |

لا يعني امتلاك Ability تجاوز عزل المستلم أو المتجر؛ Controllers ما زالت تستخدم
استعلامات recipient-scoped وحالة الحساب الموثقة.

## الأمان والتشغيل

- لا تسجل الرمز الصريح في Logs أو Analytics أو Push payload.
- يخزن Flutter الرمز في Secure Storage، لا SharedPreferences.
- `device_uuid` معرف تثبيت عشوائي، وليس IMEI أو رقم هاتف.
- لا يرسل Push نصوصًا مالية أو مخزنية حساسة إلى شاشة القفل.
- Login محدود بخمس محاولات في الدقيقة، والمسارات المحمية محدودة بمئة وعشرين.
- أمر التنظيف مجدول يوميًا 03:00 مع منع التداخل.

## بوابة القبول قبل Flutter

1. مالك نشط يسجل الدخول ويقرأ `/me` و`/stores` والإشعارات.
2. محاسب لا يرى إلا متجره وإشعارات مستلمه.
3. إيقاف المحاسب أو المالك أو المتجر يبطل الرمز في الطلب التالي.
4. انتهاء الاشتراك يرفض الطلب.
5. إلغاء جهاز يمنع رمزه فورًا ولا يلغي جهازًا لحساب آخر.
6. إعادة Notification mutation بالمفتاح نفسه لا تكرر الأثر.
7. لا تظهر Tokens صريحة في قاعدة البيانات أو السجلات.
8. تدوير Refresh Token يبطل القيمة السابقة ولا يعمل من جهاز UUID مختلف.
9. الخروج أو الإبطال البعيد يوقف Push المرتبط بالجلسة نفسها.

## الرجوع

1. أوقف عملاء الهاتف أولًا أو ارفع الحد الأدنى للإصدار لمنع الاتصالات.
2. أزل مسارات `/api/v1` العامة أو عطّلها على Reverse Proxy.
3. احتفظ بجدول الرموز للتحليل ثم نفذ `api-tokens:cleanup`.
4. لا تسقط الجدول قبل التأكد أن لا إصدار منشور يعتمد عليه.
