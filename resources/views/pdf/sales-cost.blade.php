<!DOCTYPE html>
<html lang="ar" dir="rtl"><head><meta charset="UTF-8"><style>
        body { font-family: 'Cairo', 'DejaVu Sans', Arial, sans-serif; direction: rtl; font-size: 12px; color: #172033; margin: 0; padding: 10px; }
        
        .header { border: 1px solid #d7deea; border-radius: 8px; padding: 10px; background: #f8fafc; margin-bottom: 10px }
        .brand { font-size: 20px; font-weight: 800; color: #0f766e; }
        .title { font-size: 16px; font-weight: 800; margin: 5px 0; }
        
        .meta { width: 100%; border-collapse: collapse; margin-top: 5px }
        .meta td { padding: 5px; border: 1px solid #e5e7eb }
        
        table.items { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table.items th { background: #0f766e; color: #fff; padding: 8px; border: 1px solid #0f766e; font-size: 11px; text-align: center; }
        table.items td { padding: 8px; border: 1px solid #d8dee9; text-align: center; font-size: 11px; }
        
        .write-space { color: #ccc; }
        .footer { margin-top: 20px; text-align: center; color: #64748b; font-size: 10px; }
        
        .signatures { margin-top: 20px; width: 100%; }
        .signatures td { width: 50%; padding: 10px; text-align: center; }
        .text-right { text-align: right; }
        .total-label { text-align: left; font-weight: bold; background: #f8fafc; }
        .total-value { text-align: right; font-weight: bold; color: #0f766e; }
        .document-notes { margin-top: 25px; width: 100%; }
        .document-alert { margin-bottom: 15px; padding: 12px 15px; border: 1px solid #ef4444; border-radius: 8px; background: #fef2f2; }
        .document-alert-title { margin: 0 0 8px; color: #b91c1c; font-size: 13px; font-weight: bold; }
        .document-alert-list { margin: 0; padding-right: 20px; font-size: 11px; color: #7f1d1d; line-height: 1.8; }
        .document-help { padding: 12px 15px; border: 1px solid #22c55e; border-radius: 8px; background: #f0fdf4; }
        .document-help-title { margin: 0 0 8px; color: #15803d; font-size: 13px; font-weight: bold; }
        .document-help-list { margin: 0; padding-right: 20px; font-size: 11px; color: #166534; line-height: 1.8; }
    </style></head><body>
<div class="header"><div class="brand">CARLED</div><div class="title">تقرير تكلفة المبيعات — العمليات المحددة</div>
<table class="meta"><tr><td>المتجر: {{ $store->name }}</td><td>الفترة: {{ $from }} — {{ $to }}</td><td>عدد العمليات: {{ $rows->count() }}</td></tr></table></div>
<table class="items"><thead><tr><th>العملية</th><th>التاريخ</th><th>الوصف والمواد</th><th>المحاسب</th><th>المبيعات</th><th>شغل اليد</th><th>إجمالي التكلفة</th></tr></thead><tbody>
@foreach($rows as $row)
<tr><td>#{{ $row['id'] }}</td><td>{{ $row['business_date'] }}</td><td class="text-right">{{ $row['description'] }}@if($row['products'])<br>{{ $row['products'] }}@endif</td><td>{{ $row['accountant'] }}</td><td>{{ number_format($row['sales_total'], 2) }}</td><td>{{ number_format($row['labor_total'], 2) }}</td><td>{{ number_format($row['total_cost'], 2) }}</td></tr>
@endforeach
</tbody><tfoot><tr><td colspan="4" class="total-label">الإجمالي — ريال</td><td class="total-value">{{ number_format($summary['sales_total'], 2) }}</td><td class="total-value">{{ number_format($summary['labor_total'], 2) }}</td><td class="total-value">{{ number_format($summary['total_cost'], 2) }}</td></tr></tfoot></table>
<div class="footer">CARLED — تقرير تكلفة المبيعات</div></body></html>
