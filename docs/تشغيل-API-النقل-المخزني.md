# تشغيل API النقل المخزني

توفر هذه المرحلة قراءة عمليات النقل لتطبيق الهاتف وFlutter دون إرسال أو قبول أو رفض أو إلغاء أو تغيير للمخزون.

## المسارات

- `GET /api/v1/store-transfers?store_id=1`: الوارد والصادر.
- `GET /api/v1/store-transfers/summary?store_id=1`: ملخص الاتجاهات والحالات.
- `GET /api/v1/store-transfers/{id}?store_id=1`: التفاصيل والبنود والمطابقة.

تتطلب المسارات Bearer Token وصلاحية `store-transfers:read`. تدعم القائمة `direction` و`status` و`from` و`to` و`limit` و`cursor`.

يعتمد الصادر على `request_business_date`، والوارد المعالج على `action_business_date`، والوارد المعلق على يوم الإرسال مؤقتًا. تعتمد أسماء المنتجات والوحدات على اللقطات المحفوظة، ولا تعرض أرصدة المخزون الداخلية قبل النقل وبعده.

```bash
npm run test:mobile-store-transfers
php artisan route:list --path=api/v1/store-transfers
```
