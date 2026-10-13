<?php

namespace App\Services;

use App\Models\Accountant;
use App\Models\InventoryCountSession;
use App\Models\Product;
use App\Models\Store;
use App\Models\StoreTransfer;
use App\Models\SupportTicket;
use App\Models\User;
use App\Modules\PurchaseOrders\Models\StorePurchaseOrder;
use App\Modules\PurchaseOrders\Support\PurchaseOrderWorkflow;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/** Read-only workspace navigation and bounded results. Detail controllers retain authorization. */
class WorkspaceUiService
{
    public function stores(Authenticatable $account): Collection
    {
        return $account instanceof Accountant
            ? Store::whereKey($account->store_id)->where('status', 'active')->get()
            : $account->stores()->where('status', 'active')->orderBy('name')->get();
    }

    public function links(string $role, ?Store $store): array
    {
        $link = fn ($title, $route, $icon, $parameters = []) => ['title' => $title, 'url' => route($route, $parameters), 'icon' => $icon];
        if ($role === 'admin') {
            return [$link('الرئيسية', 'admin.dashboard.index', 'house'), $link('تذاكر الدعم', 'admin.support-tickets.index', 'headset'), $link('طلبيات التوريد', 'admin.purchase-orders.index', 'truck'), $link('جلسات الدعم', 'admin.support.index', 'life-ring')];
        }
        if ($role === 'accountant') {
            return [$link('الرئيسية', 'accountant.dashboard', 'house'), $link('بيع سريع', 'accountant.quick-sale.index', 'cash-register'), $link('طلبيات التوريد', 'accountant.purchase-orders.index', 'truck'), $link('الجرد', 'accountant.inventory-counts.index', 'boxes-stacked'), $link('التحويلات', 'accountant.transfers.index', 'right-left'), $link('الفواتير', 'accountant.invoices.index', 'file-invoice')];
        }
        $links = [$link('الرئيسية', 'user.dashboard', 'house'), $link('المتاجر', 'user.stores.index', 'store')];
        if ($store) {
            $links = array_merge($links, [$link('المنتجات', 'user.stores.products.index', 'boxes-stacked', $store), $link('التقارير', 'user.stores.reports.index', 'chart-simple', $store), $link('طلبيات التوريد', 'user.stores.purchase-orders.index', 'truck', $store), $link('الجرد', 'user.stores.inventory-counts.index', 'clipboard-check', $store), $link('تكلفة المنتجات', 'user.stores.reports.sales-cost', 'calculator', $store), $link('التحويلات', 'user.stores.transfers.index', 'right-left', $store)]);
        }
        return $links;
    }

    private function orders(Authenticatable $account, Collection $stores): Builder
    {
        $query = StorePurchaseOrder::whereIn('store_id', $stores->modelKeys());
        if ($account instanceof Accountant) {
            $query->where('user_id', $stores->first()?->user_id)->where(fn ($q) => $q->where('accountant_id', $account->id)->orWhereNull('accountant_id'));
        } else {
            $query->where('user_id', $account->id);
        }
        return $query;
    }

    public function search(Authenticatable $account, Collection $stores, string $term): array
    {
        $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term).'%';
        if (($account->role ?? '') === 'admin') {
            return SupportTicket::whereRaw("subject LIKE ? ESCAPE '!'", [$like])->latest()->limit(12)->get()->map(fn ($ticket) => [
                'title' => $ticket->subject, 'detail' => 'تذكرة '.$ticket->reference, 'url' => route('admin.support-tickets.show', $ticket),
            ])->all();
        }
        $results = [];
        $owner = $account instanceof User;
        // Literal LIKE search: do not let a wildcard fetch an entire catalogue.
        $products = Product::whereIn('store_id', $stores->modelKeys())
            ->when(!$owner, fn ($q) => $q->sellable()->where('status', 'active'))
            ->where(fn ($q) => $q->whereRaw("name LIKE ? ESCAPE '!'", [$like])->orWhereRaw("barcode LIKE ? ESCAPE '!'", [$like]))
            ->orderBy('name')->limit(8)->get(['id', 'store_id', 'name', 'barcode']);
        foreach ($products as $product) {
            $results[] = ['title' => $product->name, 'detail' => 'منتج · '.($stores->firstWhere('id', $product->store_id)?->name ?? ''), 'url' => $owner ? route('user.stores.products.edit', [$product->store_id, $product->id]) : route('accountant.quick-sale.index', ['workspace_search' => $product->barcode ?: $product->name])];
        }
        $reference = preg_match('/^PO-(\d+)-(\d{4})-(\d+)$/i', $term, $match) ? $match : null;
        $orders = $this->orders($account, $stores)->where(fn ($q) => $q->whereRaw("supplier_name LIKE ? ESCAPE '!'", [$like])
            ->when(ctype_digit($term), fn ($q) => $q->orWhere('id', (int) $term))
            ->when($reference, fn ($q) => $q->orWhere(fn ($ref) => $ref->where('id', (int) $reference[3])->where('store_id', (int) $reference[1])->whereYear('created_at', (int) $reference[2]))))
            ->latest('created_at')->limit(8)->get();
        foreach ($orders as $order) {
            $results[] = ['title' => $order->supplier_name ?: $order->referenceCode(), 'detail' => 'طلبية · '.$order->referenceCode(), 'url' => $owner ? route('user.stores.purchase-orders.show', [$order->store_id, $order->id]) : route('accountant.purchase-orders.show', $order->id)];
        }
        return $results;
    }

    public function tasks(Authenticatable $account, Collection $stores): array
    {
        if (($account->role ?? '') === 'admin') {
            $tickets = SupportTicket::whereIn('status', ['open', 'waiting_support', 'in_progress']);
            $total = $tickets->count();
            $items = $tickets->oldest('updated_at')->limit(24)->get()->map(fn ($ticket) => [
                'title' => $ticket->subject, 'detail' => 'تذكرة '.$ticket->reference, 'date' => $ticket->updated_at?->format('Y-m-d'), 'url' => route('admin.support-tickets.show', $ticket),
            ])->all();
            return ['total' => $total, 'items' => $items, 'limited' => $total > count($items)];
        }
        $owner = $account instanceof User;
        $orders = $this->orders($account, $stores)->whereIn('workflow_status', $owner
            ? ['pending_owner_review', 'returned_after_edit', 'returned_after_count', 'pending_owner_receipt_review', 'pending_inventory_approval']
            : ['draft_accountant', 'returned_for_edit', 'returned_for_count', 'pending_receipt_confirmation']);
        $counts = InventoryCountSession::whereIn('store_id', $stores->modelKeys())
            ->where($owner ? 'owner_id' : 'accountant_id', $account->id)
            ->whereIn('status', $owner ? ['pending_owner', 'partially_approved'] : ['sent_to_accountant', 'counting', 'returned_to_accountant']);
        $transfers = StoreTransfer::where('status', 'pending')->whereIn('receiver_store_id', $stores->modelKeys());
        $total = $orders->count() + $counts->count() + $transfers->count();
        $items = [];
        foreach ($orders->oldest('updated_at')->limit(12)->get() as $order) {
            $items[] = ['title' => 'طلبية '.$order->referenceCode(), 'detail' => PurchaseOrderWorkflow::label($order->workflow_status), 'date' => $order->updated_at?->format('Y-m-d'), 'url' => $owner ? route('user.stores.purchase-orders.show', [$order->store_id, $order->id]) : route('accountant.purchase-orders.show', $order->id)];
        }
        foreach ($counts->oldest('updated_at')->limit(12)->get() as $count) {
            $items[] = ['title' => 'جرد '.$count->referenceCode(), 'detail' => $count->statusLabel(), 'date' => $count->updated_at?->format('Y-m-d'), 'url' => $owner ? route('user.stores.inventory-counts.show', [$count->store_id, $count->id]) : route('accountant.inventory-counts.show', $count->id)];
        }
        foreach ($transfers->oldest('updated_at')->limit(12)->get() as $transfer) {
            $items[] = ['title' => 'تحويل #'.$transfer->id, 'detail' => 'بانتظار مراجعة المتجر المستلم', 'date' => $transfer->updated_at?->format('Y-m-d'), 'url' => $owner ? route('user.stores.transfers.index', $transfer->receiver_store_id) : route('accountant.transfers.index')];
        }
        usort($items, fn ($a, $b) => strcmp($a['date'] ?? '', $b['date'] ?? ''));
        return ['total' => $total, 'items' => $items, 'limited' => $total > count($items)];
    }
}
