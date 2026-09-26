@props([
    'item',
    'label',
    'note',
])

@php
    $hiddenInputId = 'receiver-product-id-' . $item->id;
    $suggestions = $item->receiverSuggestions ?? collect();
    $senderName = trim((string) ($item->product_name_snapshot ?? $item->senderProduct?->name));
    $matchedSuggestion = $suggestions->first(fn ($suggestion) => mb_strtolower(trim($suggestion->name)) === mb_strtolower($senderName));
    $unitLabel = function ($product): string {
        if ($product?->product_type === 'fractional') return 'رول أو متر';
        if ($product?->is_splittable) return 'طقم أو حبة';
        return 'حبة';
    };
@endphp

<div class="space-y-3">
    <label class="block font-bold ui-text-caption ui-text-soft" for="picker-input-{{ $item->id }}">{{ $label }}</label>
    <input type="hidden" id="{{ $hiddenInputId }}" name="receiver_product_id[{{ $item->id }}]" value="{{ $matchedSuggestion?->id }}">
    <div class="relative" data-transfer-product-picker data-hidden-input="{{ $hiddenInputId }}">
        <input type="text"
               id="picker-input-{{ $item->id }}"
               data-picker-input
               required
               autocomplete="off"
               value="{{ $matchedSuggestion?->name }}"
               placeholder="ابحث في جميع منتجات المتجر المستلم..."
               class="ui-input px-3 py-2 text-sm">
        <div data-picker-options class="absolute z-50 mt-2 hidden max-h-56 w-full overflow-y-auto rounded-xl border ui-border ui-surface-strong-bg shadow-2xl">
            @foreach($suggestions as $suggestion)
                <button type="button"
                        data-picker-option
                        data-id="{{ $suggestion->id }}"
                        data-label="{{ $suggestion->name }}"
                        data-unit-label="{{ $unitLabel($suggestion) }}"
                        data-search="{{ trim($suggestion->name.' '.($suggestion->barcode ?? '')) }}"
                        class="block w-full px-3 py-2 text-right text-sm ui-title ui-hover-surface">
                    <span>{{ $suggestion->name }}</span>
                    <span class="block ui-text-caption ui-text-muted">الوحدة: {{ $unitLabel($suggestion) }}</span>
                </button>
            @endforeach
            <p data-picker-empty class="hidden p-3 text-center ui-text-caption ui-text-muted">لا يوجد منتج بهذا الاسم ضمن منتجات المتجر المستلم.</p>
        </div>
    </div>
    <p class="ui-text-caption ui-status-info" data-picker-match>
        @if($matchedSuggestion)
            هل المنتج المستلم هو: <strong>{{ $matchedSuggestion->name }}</strong>؟ — الوحدة: {{ $unitLabel($matchedSuggestion) }}
        @else
            ابحث عن المنتج الموجود فعليًا في المتجر المستلم وطابقه مع البند المرسل.
        @endif
    </p>
    <p class="ui-text-caption ui-status-warning">{{ $note }}</p>
</div>
