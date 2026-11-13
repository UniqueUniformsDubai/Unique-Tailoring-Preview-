/* Prepare a quotation enquiry locally. No form data is stored or submitted by this site. */
(() => {
  'use strict';
  const form = document.getElementById('quote-form');
  if (!form) return;
  const review = document.getElementById('quote-review');
  const message = document.getElementById('quote-message');
  const whatsapp = document.getElementById('quote-whatsapp');
  const date = form.elements.date;
  const todayInDubai = () => new Intl.DateTimeFormat('en-CA', {
    timeZone: 'Asia/Dubai', year: 'numeric', month: '2-digit', day: '2-digit'
  }).format(new Date());
  date.min = todayInDubai();
  const services = { corporate: 'Corporate uniforms', alterations: 'Tailoring & alterations' };
  const selected = services[new URLSearchParams(window.location.search).get('service')];
  if (selected) form.elements.service.value = selected;
  const invalidateReview = () => {
    review.hidden = true;
    whatsapp.removeAttribute('href');
    message.textContent = '';
  };
  form.addEventListener('input', invalidateReview);
  form.addEventListener('change', invalidateReview);
  form.addEventListener('submit', event => {
    event.preventDefault();
    date.min = todayInDubai();
    form.elements.name.setCustomValidity(form.elements.name.value.trim() ? '' : 'Please enter your name.');
    form.elements.garment.setCustomValidity(form.elements.garment.value.trim() ? '' : 'Please enter a garment or uniform type.');
    if (!form.reportValidity()) return;
    const data = new FormData(form);
    const val = key => String(data.get(key) || '').trim();
    const lines = ['Hello Unique Tailoring Dubai, I would like a quotation.', '',
      `Service: ${val('service')}`, `Garment / uniform: ${val('garment')}`,
      `Quantity: ${val('quantity')}`, `Preferred completion / delivery date: ${val('date') || 'Flexible / please advise'}`,
      `Fabric: ${val('fabric')}`, `Logo / branding: ${val('branding')}`, `Name: ${val('name')}`];
    if (val('company')) lines.push(`Company / organisation: ${val('company')}`);
    if (val('details')) lines.push('', `Additional details: ${val('details')}`);
    lines.push('', 'Please confirm the quotation including any applicable VAT, availability and timing.',
      'Please advise whether quantity-based special pricing is available for this order.');
    const draft = lines.join('\n');
    message.textContent = draft;
    whatsapp.href = 'https://wa.me/971555597963?text=' + encodeURIComponent(draft);
    review.hidden = false;
    document.getElementById('quote-review-heading').focus();
  });
  for (const key of ['name', 'garment']) {
    form.elements[key].addEventListener('input', () => form.elements[key].setCustomValidity(''));
  }
  document.getElementById('quote-edit').addEventListener('click', () => {
    invalidateReview();
    form.elements.service.focus();
  });
  document.getElementById('quote-submit').disabled = false;
})();
