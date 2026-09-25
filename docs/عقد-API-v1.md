# عقد API v1 للويب وPWA

## النطاق

يدعم العقد مسارين للمصادقة: جلسات الويب للـPWA عبر Cookie وCSRF، وBearer Token
قابل للإبطال ومقيد بجهاز للعملاء المخصصين. كلاهما يطبق عزل الحساب والمتجر، ولا
يسمح الرمز بتجاوز الصلاحيات أو حالة الاشتراك.

المسارات الحالية:

- `GET /user/api/v1/app-context`
- `GET /accountant/api/v1/app-context`
- قائمة الإشعارات وعدد غير المقروء للمالك والمحاسب.
- تعليم الإشعار كمقروء وإخفاؤه مع `Idempotency-Key`.
- `/api/v1/auth/*` و`/api/v1/me` و`/api/v1/app-config` و`/api/v1/stores` و
  `/api/v1/devices` ومركز إشعارات Bearer.

## استجابة النجاح

```json
{
  "data": {},
  "meta": {
    "api_version": "v1",
    "request_id": "..."
  }
}
```

يجوز إضافة حقول إلى `meta` مثل `next_cursor` و`per_page`. لا يعتمد العميل على
ترتيب المفاتيح، ولا يعتبر غياب حقل اختياري خطأً.

## استجابة الخطأ

```json
{
  "error": {
    "code": "VALIDATION_FAILED",
    "message": "تعذر قبول بيانات الطلب.",
    "details": {
      "fields": {}
    }
  },
  "meta": {
    "api_version": "v1",
    "request_id": "..."
  }
}
```

الأكواد المركزية الحالية:

| HTTP | code |
| --- | --- |
| 401 | `UNAUTHENTICATED` |
| 403 | `FORBIDDEN` |
| 404 | `NOT_FOUND` |
| 405 | `METHOD_NOT_ALLOWED` |
| 419 | `SESSION_EXPIRED` |
| 422 | `INVALID_CREDENTIALS` أو `CURRENT_DEVICE_REQUIRES_LOGOUT` |
| 422 | `VALIDATION_FAILED` أو `IDEMPOTENCY_ERROR` |
| 426 | `UPDATE_REQUIRED` |
| 429 | `RATE_LIMITED` |
| 500 | `INTERNAL_ERROR` |

لا يعيد `INTERNAL_ERROR` اسم Exception أو ملفه أو Stack trace. يستخدم الدعم
`request_id` للبحث في السجلات.

## Headers

كل مسار في العقد يعيد:

```http
Cache-Control: private, no-store
X-Content-Type-Options: nosniff
X-API-Version: v1
X-Request-ID: ...
```

استجابات Idempotency المعادة تضيف `X-Idempotent-Replayed: true`.

## عميل المتصفح

توفر الوحدة `resources/js/features/pwa/api-client.js`:

- `apiRequest` للطلب المتصل مع Cookie الجلسة وCSRF ومهلة محدودة.
- `createIdempotencyKey` لإنشاء مفتاح محاولة منطقية جديدة.
- `idempotentApiRequest` لإرجاع المفتاح نفسه مع Promise الطلب حتى يستطيع المستدعي
  الاحتفاظ به عند إعادة المحاولة.
- `ApiError` يحمل `code` وHTTP status وdetails و`requestId`.

لا تعيد الوحدة المحاولة تلقائيًا، ولا تكتب Mutation في Cache أو Service Worker أو
IndexedDB. إعادة المحاولة قرار للميزة المستهلكة، ويجب أن تستخدم المفتاح نفسه فقط
للطلب المنطقي نفسه.

## قواعد التطوير

1. يضاف `api.contract` لكل مجموعة API v1 جديدة.
2. تستخدم Controllers `ApiResponse::success` أو `ApiResponse::noContent`.
3. لا تنشئ Controller شكل خطأ خاصًا بها؛ تضاف الحاجة المشتركة إلى العقد المركزي.
4. أي Mutation قابلة لإعادة المحاولة تستخدم `idempotency` بعد مراجعة Transaction.
5. لا تغير بنية `data` الحالية بصورة كاسرة داخل v1؛ أنشئ إصدارًا جديدًا عند الحاجة.

مصادقة الأجهزة وأكواد `TOKEN_MISSING` و`TOKEN_INVALID` و`TOKEN_ABILITY_DENIED`
موثقة تفصيليًا في [`تشغيل-API-الأجهزة-والتطبيقات.md`](تشغيل-API-الأجهزة-والتطبيقات.md).

## جلسة الجهاز وتجديدها

يعاد مع تسجيل الدخول `refresh_token` لمرة واحدة، ويجدد عبر `POST /api/v1/auth/refresh`.
