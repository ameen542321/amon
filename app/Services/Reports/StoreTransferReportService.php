<?php

namespace App\Services\Reports;

use App\Models\Store;
use App\Models\StoreTransfer;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class StoreTransferReportService
{
    /**
     * يبني تقرير النقل للمتجر وفق يوم العمل: الصادر بتاريخ الإرسال، والوارد بتاريخ الإجراء أو الإرسال إذا بقي معلقًا.
     */
    public function build(Store $store, array $filters, bool $paginate = true): array
    {
        $from = CarbonImmutable::parse($filters['from'])->startOfDay();
        $to = CarbonImmutable::parse($filters['to'])->startOfDay();
        $status = $filters['status'] ?? null;

        $query = StoreTransfer::query()
            ->with([
                'senderStore:id,name',
                'receiverStore:id,name',
                'items.senderProduct:id,name',
                'items.receiverProduct:id,name',
                'createdBy',
                'actionBy',
            ])
            ->where(function (Builder $direction) use ($store, $from, $to): void {
                $direction
                    ->where(function (Builder $outgoing) use ($store, $from, $to): void {
                        $outgoing->where('sender_store_id', $store->id)
                            ->whereBetween('request_business_date', [$from->toDateString(), $to->toDateString()]);
                    })
                    ->orWhere(function (Builder $incoming) use ($store, $from, $to): void {
                        $incoming->where('receiver_store_id', $store->id)
                            ->where(function (Builder $date) use ($from, $to): void {
                                $date->whereBetween('action_business_date', [$from->toDateString(), $to->toDateString()])
                                    ->orWhere(function (Builder $pendingDate) use ($from, $to): void {
                                        $pendingDate->whereNull('action_business_date')
                                            ->whereBetween('request_business_date', [$from->toDateString(), $to->toDateString()]);
                                    });
                            });
                    });
            })
            ->when($status, fn (Builder $builder) => $builder->where('status', $status));

        $summary = [
            'total' => (clone $query)->count(),
            'outgoing' => (clone $query)->where('sender_store_id', $store->id)->count(),
            'incoming' => (clone $query)->where('receiver_store_id', $store->id)->count(),
            'rejected' => (clone $query)->where('status', 'rejected')->count(),
        ];

        // تعتمد القيمة على الكمية المخزنية الموحّدة وسعر التكلفة المحفوظ مع بند النقل.
        // لا تدخل الطلبات المرفوضة أو الملغاة لأنها أعيدت إلى مخزون المتجر المرسل.
        $costTransfers = (clone $query)
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->with('items:id,store_transfer_id,normalized_quantity,cost_price')
            ->get();
        $summary['outgoing_cost'] = round((float) $costTransfers
            ->where('sender_store_id', $store->id)
            ->sum(fn (StoreTransfer $transfer) => $transfer->items->sum(
                fn ($item) => (float) $item->normalized_quantity * (float) $item->cost_price
            )), 2);
        $summary['incoming_cost'] = round((float) $costTransfers
            ->where('receiver_store_id', $store->id)
            ->sum(fn (StoreTransfer $transfer) => $transfer->items->sum(
                fn ($item) => (float) $item->normalized_quantity * (float) $item->cost_price
            )), 2);

        $ordered = $query
            // ترتيب التقرير ثابت: الصادر أولًا، ثم الوارد، ثم المرفوض بصرف النظر عن اتجاهه.
            ->orderByRaw("CASE WHEN status = 'rejected' THEN 3 WHEN sender_store_id = ? THEN 1 ELSE 2 END ASC", [$store->id])
            ->orderByRaw('CASE WHEN sender_store_id = ? THEN request_business_date ELSE COALESCE(action_business_date, request_business_date) END DESC', [$store->id])
            ->orderByDesc('id');
        $transfers = $paginate
            ? $ordered->paginate(20)->withQueryString()
            : $ordered->get();

        return compact('from', 'to', 'status', 'summary', 'transfers');
    }
}
