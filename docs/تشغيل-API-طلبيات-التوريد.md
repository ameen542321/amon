# تشغيل API طلبيات التوريد

توفر هذه الدفعة قراءة طلبيات التوريد لتطبيق الهاتف وFlutter دون إنشاء أو تعديل أو إرسال أو استلام أو اعتماد أو رفض الطلبية.

## المسارات

- `GET /api/v1/purchase-orders?store_id=1`: القائمة مع Cursor Pagination.
- `GET /api/v1/purchase-orders/summary?store_id=1`: الأعداد بحسب المرحلة.
- `GET /api/v1/purchase-orders/{id}?store_id=1`: التفاصيل والبنود والأحداث.

تتطلب المسارات Bearer Token وصلاحية `purchase-orders:read`، ويتحقق الخادم من حق الحساب في المتجر. يرى المالك طلبيات متجره التابعة لحسابه، ويرى المحاسب الطلبيات المسندة إليه والطلبيات غير المسندة فقط، مطابقًا لنطاق واجهة الويب. تدعم القائمة `status` و`supplier` و`from` و`to` و`limit` و`cursor`.

تعرض التفاصيل تكاليف الطلب والاستلام ويوم عمل المحاسبة للاعتماد، ولا تعرض لقطات رصيد المخزون الداخلية. لا يوجد Offline Outbox أو اعتماد تلقائي عند عودة الشبكة.

```bash
npm run test:mobile-purchase-orders
php artisan route:list --path=api/v1/purchase-orders
```
