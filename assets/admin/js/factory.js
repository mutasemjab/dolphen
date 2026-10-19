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
  document.querySelectorAll('[data-add-line]').forEach(button => button.addEventListener('click', () => {
    const template = document.getElementById(button.dataset.addLine);
    document.getElementById(button.dataset.target).insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', String(nextIndex++)));
  }));
  document.addEventListener('click', event => event.target.closest('[data-remove-line]')?.closest('[data-line]').remove());
  document.addEventListener('change', event => {
    if (event.target.matches('[data-product-select]')) {
      event.target.closest('[data-line]').querySelector('[data-unit-price]').value = event.target.selectedOptions[0]?.dataset.price || '0';
    }
  });
  window.addEventListener('pageshow', event => { if (event.persisted) window.location.reload(); });
});
