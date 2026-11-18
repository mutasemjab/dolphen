<tr data-line>
    <td><select name="lines[{{ $index }}][product_id]" required data-product-select aria-label="{{ __('erp.product_id') }}">
        <option value="">{{ __('erp.select') }}</option>
        @foreach($products as $product)
        <option value="{{ $product->id }}" data-warehouse="{{ $product->warehouse_id }}" @selected((string)($line['product_id'] ?? '') === (string)$product->id)>{{ $product->label }} · {{ $product->baseUnit->label }} · {{ __('erp.'.$product->item_type) }}</option>
        @endforeach
    </select></td>
    <td><input name="lines[{{ $index }}][quantity]" value="{{ $line['quantity'] ?? '1' }}" inputmode="decimal" dir="ltr" required aria-label="{{ __('erp.quantity') }}"></td>
    <td><button class="btn small danger-outline" type="button" data-remove-line>{{ __('erp.remove') }}</button></td>
</tr>
