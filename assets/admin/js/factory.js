document.addEventListener('DOMContentLoaded', () => {
  const toggle = document.getElementById('menu-toggle');
  toggle?.addEventListener('click', () => toggle.setAttribute('aria-expanded', String(document.body.classList.toggle('sidebar-open'))));
  document.querySelectorAll('[data-print]').forEach(button => button.addEventListener('click', () => window.print()));
  document.querySelectorAll('form').forEach(form => form.addEventListener('submit', event => {
    if (form.hasAttribute('data-location-form')) return;
    if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) { event.preventDefault(); return; }
    if (form.dataset.submitted) { event.preventDefault(); return; }
    form.dataset.submitted = '1';
    form.querySelectorAll('button[type="submit"],button:not([type])').forEach(button => { button.disabled = true; });
  }));
  document.querySelectorAll('[data-location-form]').forEach(form => form.addEventListener('submit', event => {
    event.preventDefault();
    if (form.dataset.locating) return;
    const message = form.querySelector('[data-location-message]');
    const button = form.querySelector('button');
    const failed = () => { message.textContent = form.dataset.error; button.disabled = false; delete form.dataset.locating; };
    if (!window.isSecureContext || !navigator.geolocation) { failed(); return; }
    form.dataset.locating = '1'; button.disabled = true; message.textContent = form.dataset.pending;
    navigator.geolocation.getCurrentPosition(position => {
      const coordinates = position.coords;
      form.elements.latitude.value = coordinates.latitude;
      form.elements.longitude.value = coordinates.longitude;
      form.elements.accuracy.value = coordinates.accuracy;
      HTMLFormElement.prototype.submit.call(form);
    }, failed, { enableHighAccuracy: true, maximumAge: 0, timeout: 20000 });
  }));
  document.querySelectorAll('[data-locate-settings]').forEach(button => button.addEventListener('click', () => {
    const form = button.closest('form'); const message = form.querySelector('[data-location-message]');
    const failed = () => { message.textContent = button.dataset.error; button.disabled = false; };
    if (!window.isSecureContext || !navigator.geolocation) { failed(); return; }
    button.disabled = true;
    navigator.geolocation.getCurrentPosition(position => {
      form.elements.latitude.value = position.coords.latitude.toFixed(7);
      form.elements.longitude.value = position.coords.longitude.toFixed(7);
      message.textContent = ''; button.disabled = false;
    }, failed, { enableHighAccuracy: true, maximumAge: 0, timeout: 20000 });
  }));
  let nextIndex = 1000;
  document.querySelectorAll('[data-leave-form]').forEach(form => {
    const sync = () => {
      const departure = form.elements.type.value === 'departure';
      form.querySelector('[data-departure-times]').hidden = !departure;
      ['from_time','to_time'].forEach(name => { form.elements[name].required = departure; form.elements[name].disabled = !departure; });
      form.elements.end_date.readOnly = departure;
      if (departure) form.elements.end_date.value = form.elements.start_date.value;
    };
    form.elements.type.addEventListener('change', sync);
    form.elements.start_date.addEventListener('change', sync);
    sync();
  });
  // Searchable dropdowns (Select2). Selects inside <template> are enhanced when their row is added.
  const $ = window.jQuery;
  const setSelect = (select, value) => { select.value = value; if ($ && $.fn.select2 && select.classList.contains('select2-hidden-accessible')) $(select).trigger('change.select2'); };
  const enhance = root => {
    if (!$ || !$.fn.select2) return;
    root.querySelectorAll('select:not([data-no-search]):not(.select2-hidden-accessible)').forEach(select => {
      const empty = select.options.length && select.options[0].value === '';
      $(select).select2({
        width: '100%', dir: document.documentElement.dir || 'ltr', language: document.documentElement.lang === 'ar' ? 'ar' : 'en',
        placeholder: empty ? { id: '', text: select.options[0].text } : undefined, allowClear: empty && !select.required,
        dropdownParent: $(select.closest('dialog') || document.body),
      });
      // Select2 reports changes through jQuery only; forward them so native listeners keep working.
      $(select).on('select2:select select2:clear', () => select.dispatchEvent(new Event('change', { bubbles: true })));
    });
  };
  enhance(document);
  const decimalText = value => (value || '0').includes('.') ? value.replace(/\.?0+$/, '') || '0' : value;
  const recalc = form => {
    const box = form?.querySelector('[data-invoice-totals]'); if (!box) return;
    const places = Number(box.dataset.decimals || 3); const fmt = n => n.toLocaleString('en-US', { minimumFractionDigits: places, maximumFractionDigits: places });
    let subtotal = 0;
    form.querySelectorAll('[data-line]').forEach(row => {
      const line = (parseFloat(row.querySelector('[data-quantity]')?.value) || 0) * (parseFloat(row.querySelector('[data-unit-price]')?.value) || 0);
      subtotal += line; const cell = row.querySelector('[data-line-total]'); if (cell) cell.textContent = fmt(line);
    });
    const discount = parseFloat(form.elements.discount?.value) || 0; const rate = parseFloat(form.elements.tax_rate?.value) || 0;
    const tax = (subtotal - discount) * rate / 100;
    box.querySelector('[data-total="subtotal"]').textContent = fmt(subtotal);
    box.querySelector('[data-total="tax"]').textContent = fmt(tax);
    box.querySelector('[data-total="total"]').textContent = fmt(subtotal - discount + tax);
  };
  document.querySelectorAll('[data-invoice-totals]').forEach(box => recalc(box.closest('form')));
  document.addEventListener('input', event => { if (event.target.closest('form')?.querySelector('[data-invoice-totals]')) recalc(event.target.closest('form')); });
  // Raw materials are not sold: hide and skip the selling price.
  document.querySelectorAll('select[name="item_type"]').forEach(select => {
    const price = select.form?.elements.selling_price; if (!price) return;
    const sync = () => { const raw = select.value === 'raw'; price.closest('.field').hidden = raw; price.disabled = raw; price.required = !raw; };
    select.addEventListener('change', sync); sync();
  });
  document.querySelectorAll('[data-add-line]').forEach(button => button.addEventListener('click', () => {
    const template = document.getElementById(button.dataset.addLine);
    const target = document.getElementById(button.dataset.target);
    target.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', String(nextIndex++)));
    enhance(target.lastElementChild);
    recalc(target.closest('form'));
  }));
  document.addEventListener('click', event => {
    const row = event.target.closest('[data-remove-line]')?.closest('[data-line]'); if (!row) return;
    const form = row.closest('form'); row.remove(); recalc(form);
  });
  document.addEventListener('change', event => {
    if (event.target.matches('[data-product-select]')) {
      const option = event.target.selectedOptions[0]; const row = event.target.closest('[data-line]');
      const price = row.querySelector('[data-unit-price]');
      if (price) price.value = decimalText(option?.dataset.price || '0');
      // Use the product's own warehouse when the document has none yet.
      const warehouse = event.target.form?.elements.warehouse_id;
      if (warehouse && !warehouse.value && option?.dataset.warehouse && warehouse.querySelector(`option[value="${option.dataset.warehouse}"]`)) setSelect(warehouse, option.dataset.warehouse);
      recalc(event.target.form);
    } else if (event.target.form?.querySelector('[data-invoice-totals]')) {
      recalc(event.target.form);
    }
  });
  window.addEventListener('pageshow', event => { if (event.persisted) window.location.reload(); });
});
