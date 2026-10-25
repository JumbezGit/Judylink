<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

admin_header('Bookings', 'bookings');
?>
<section class="grid gap-4">
  <div class="mb-4 flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
    <h2 class="text-xl font-medium">Bookings</h2>
  </div>
  <div id="bookingsList" class="grid gap-2 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4"></div>
</section>

<?php
admin_footer(<<<'JS'
let bookings = [];

function escapeHtml(value) {
  return String(value ?? '').replace(/[&<>"']/g, (char) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;',
  })[char]);
}

function yesNo(value) {
  return Number(value) === 1 || value === true ? 'Yes' : 'No';
}

function money(value, currency) {
  return `${Number(value || 0).toFixed(2)} ${escapeHtml(currency || 'USD')}`;
}

function media(path) {
  if (!path) return '';
  if (/^https?:\/\//.test(path)) return path;
  return path.startsWith('/') ? path : `/${path.replace(/^\/+/, '')}`;
}

function statusBadge(value) {
  const colors = {
    new: 'bg-sky-50 text-sky-700',
    confirmed: 'bg-emerald-50 text-emerald-700',
    cancelled: 'bg-red-50 text-red-700',
    completed: 'bg-slate-100 text-slate-700',
    pending: 'bg-amber-50 text-amber-700',
    paid: 'bg-emerald-50 text-emerald-700',
    failed: 'bg-red-50 text-red-700',
    refunded: 'bg-violet-50 text-violet-700',
  };
  return `<span class="rounded-md px-2.5 py-1 text-xs font-medium ${colors[value] || 'bg-slate-100 text-slate-700'}">${escapeHtml(value || '-')}</span>`;
}

function bookingName(b) {
  return `${b.first_name || ''} ${b.last_name || ''}`.trim() || '-';
}

async function loadBookings() {
  try {
    const data = await request(api('bookings.php'), { headers: adminHeaders() });
    bookings = data.bookings || [];
    document.querySelector('#bookingsList').innerHTML = bookings.map((b) => `
      <article class="flex h-full flex-col justify-between gap-2 rounded-lg border border-slate-200 bg-white p-2 shadow-lg shadow-slate-900/5">
        <div class="min-w-0">
          <div class="grid aspect-[4/3] w-full place-items-center overflow-hidden rounded-md bg-slate-100">
            ${b.tour_image_path ? `<img class="block max-h-full max-w-full object-contain p-1" src="${media(b.tour_image_path)}" alt="${escapeHtml(b.tour_name || 'Tour image')}">` : '<span class="px-2 text-center text-xs font-normal text-slate-400">No image</span>'}
          </div>
          <div class="mt-2">
            <div class="flex flex-wrap items-start justify-between gap-2">
              <h3 class="min-w-0 truncate text-sm font-medium text-slate-900">${escapeHtml(b.booking_ref)}</h3>
              <div class="flex flex-wrap justify-end gap-1">
              ${statusBadge(b.status)}
              ${statusBadge(b.payment_status)}
              </div>
            </div>
            <p class="mt-1 truncate text-xs text-slate-500">${escapeHtml(bookingName(b))}</p>
            <p class="mt-0.5 line-clamp-1 text-xs text-slate-500">${escapeHtml(b.tour_name || '-')}</p>
            <p class="mt-1 text-xs text-slate-400">${escapeHtml(b.travel_date || '')} ${escapeHtml(b.start_time || '')}</p>
          </div>
        </div>
        <div class="mt-auto grid gap-1.5">
          <div>
            <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Total</p>
            <p class="text-base font-medium text-[#172033]">${money(b.total, b.currency)}</p>
          </div>
          <a class="secondary inline-flex w-full items-center justify-center gap-1.5 px-2 py-1.5 text-[11px] no-underline" href="${adminPage('booking-view/')}?id=${encodeURIComponent(b.id)}"><i class="fa-solid fa-eye"></i>View Details</a>
        </div>
      </article>
    `).join('') || '<p class="text-sm text-slate-500">No bookings yet.</p>';
  } catch (error) {
    failure(error.message || 'Could not load bookings');
  }
}

loadBookings();
JS);

