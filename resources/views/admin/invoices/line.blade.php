<tr data-line>
    <td><select name="lines[{{ $index }}][product_id]" required data-product-select aria-label="{{ __('erp.product_id') }}">
        <option value="">{{ __('erp.select') }}</option>
        @foreach($products as $product)
        <option value="{{ $product->id }}" data-price="{{ \App\Services\Admin\InvoiceService::sells($type) ? $product->selling_price : $product->cost }}" data-warehouse="{{ $product->warehouse_id }}" @selected((string)($line['product_id'] ?? '') === (string)$product->id)>{{ $product->label }} · {{ $product->baseUnit->label }}</option>
        @endforeach
    </select></td>
    <td><input name="lines[{{ $index }}][quantity]" value="{{ $line['quantity'] ?? '1' }}" inputmode="decimal" dir="ltr" required data-quantity aria-label="{{ __('erp.quantity') }}"></td>
    <td><input name="lines[{{ $index }}][unit_price]" value="{{ $line['unit_price'] ?? '0' }}" inputmode="decimal" dir="ltr" required data-unit-price aria-label="{{ __('erp.unit_price') }}"></td>
    <td data-line-total dir="ltr">0</td>
    <td><button class="btn small danger-outline" type="button" data-remove-line>{{ __('erp.remove') }}</button></td>
</tr>
