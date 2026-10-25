<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Bootstrap.php';
require_once __DIR__ . '/includes/layout.php';

require_admin_page();
$siteId = admin_selected_site_id();

$id = (int) ($_GET['id'] ?? 0);
if ($id < 1) {
    http_response_code(400);
    exit('Booking id is required');
}

$stmt = Database::pdo()->prepare(
    'SELECT bookings.*, tours.image_path AS tour_image_path
     FROM bookings
     LEFT JOIN tours ON tours.site_id = bookings.site_id AND (tours.id = bookings.tour_id OR tours.slug = bookings.tour_slug)
     WHERE bookings.id = ? AND bookings.site_id = ?
     LIMIT 1'
);
$stmt->execute([$id, $siteId]);
$booking = $stmt->fetch();
if (!$booking) {
    http_response_code(404);
    exit('Booking not found');
}

$guestName = trim((string) $booking['first_name'] . ' ' . (string) $booking['last_name']);
$publicTourUrl = rtrim(APP_URL, '/') . '/tours/' . rawurlencode((string) $booking['tour_slug']);
$shareText = sprintf(
    "%s\nGuest: %s\nTour: %s\nDate: %s %s\nTotal: %.2f %s\n%s",
    (string) $booking['booking_ref'],
    $guestName,
    (string) $booking['tour_name'],
    (string) $booking['travel_date'],
    (string) $booking['start_time'],
    (float) $booking['total'],
    (string) $booking['currency'],
    $publicTourUrl
);
$selectedContactHref = $booking['contact_method'] === 'phone'
    ? 'tel:' . preg_replace('/\s+/', '', (string) $booking['phone'])
    : 'mailto:' . rawurlencode((string) $booking['email']) . '?subject=' . rawurlencode('Booking ' . (string) $booking['booking_ref']);
$selectedContactLabel = $booking['contact_method'] === 'phone' ? 'Call Selected Contact' : 'Email Selected Contact';
$selectedContactIcon = $booking['contact_method'] === 'phone' ? 'fa-phone' : 'fa-envelope';
$brandName = admin_setting('brand_name', 'Hyena Admin');
$brandLogo = admin_setting('brand_logo', '');

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function media_url(?string $path): string
{
    if (!$path) {
        return '';
    }
    if (preg_match('/^https?:\/\//', $path)) {
        return $path;
    }
    return str_starts_with($path, '/') ? $path : '/' . ltrim($path, '/');
}

function yes_no(mixed $value): string
{
    return (int) $value === 1 ? 'Yes' : 'No';
}

function money_value(mixed $value, mixed $currency): string
{
    return number_format((float) $value, 2) . ' ' . (string) $currency;
}

function detail_card(string $label, mixed $value): string
{
    return '<div class="rounded-lg bg-slate-50 p-3"><p class="text-[11px] font-medium uppercase tracking-wide text-slate-400">' . e($label) . '</p><p class="mt-1 break-words text-sm font-medium text-slate-800">' . e($value ?: '-') . '</p></div>';
}

admin_header('Booking Details', 'bookings');
?>
<style>
  @media print {
    .sidebar, header, .no-print { display: none !important; }
    .admin-shell { display: block !important; }
    main { padding: 0 !important; background: #fff !important; color: #111827 !important; }
    .admin-card { box-shadow: none !important; break-inside: avoid; }
  }
</style>

<section class="grid gap-4">
  <div class="no-print flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
    <a class="secondary inline-flex w-fit items-center gap-2 no-underline" href="<?= admin_url('bookings') ?>"><i class="fa-solid fa-arrow-left"></i>Back to Bookings</a>
    <div class="flex flex-wrap gap-2">
      <a class="secondary inline-flex items-center gap-2 no-underline" href="<?= e($selectedContactHref) ?>"><i class="fa-solid <?= e($selectedContactIcon) ?>"></i><?= e($selectedContactLabel) ?></a>
      <button class="secondary inline-flex items-center gap-2" type="button" onclick="downloadBookingPdf()"><i class="fa-solid fa-file-pdf"></i>Download PDF</button>
      <button class="secondary inline-flex items-center gap-2" type="button" onclick="window.print()"><i class="fa-solid fa-print"></i>Print / Save PDF</button>
      <a class="secondary inline-flex items-center gap-2 no-underline" href="https://wa.me/?text=<?= rawurlencode($shareText) ?>" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i>Share WhatsApp</a>
      <a class="secondary inline-flex items-center gap-2 no-underline" href="mailto:?subject=<?= rawurlencode('Booking ' . (string) $booking['booking_ref']) ?>&body=<?= rawurlencode($shareText) ?>"><i class="fa-solid fa-envelope"></i>Share Email</a>
    </div>
  </div>

  <article class="admin-card grid gap-4 sm:grid-cols-[160px_minmax(0,1fr)]">
    <div class="grid aspect-[4/3] place-items-center overflow-hidden rounded-lg bg-slate-100">
      <?php if (!empty($booking['tour_image_path'])): ?>
        <img class="h-full w-full object-contain" src="<?= e(media_url((string) $booking['tour_image_path'])) ?>" alt="<?= e($booking['tour_name']) ?>">
      <?php else: ?>
        <span class="text-sm font-normal text-slate-400">No tour image</span>
      <?php endif; ?>
    </div>
    <div class="grid content-start gap-3">
      <div>
        <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Booking</p>
        <h2 class="mt-1 text-2xl font-medium text-slate-900"><?= e($booking['booking_ref']) ?> - <?= e($guestName) ?></h2>
        <p class="mt-1 text-sm text-slate-500"><?= e($booking['tour_name']) ?></p>
      </div>
      <div class="flex flex-wrap gap-2">
        <span class="rounded-md bg-sky-50 px-2.5 py-1 text-xs font-medium text-sky-700"><?= e($booking['status']) ?></span>
        <span class="rounded-md bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-700"><?= e($booking['payment_status']) ?></span>
        <span class="rounded-md bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700"><?= e($booking['contact_method']) ?></span>
      </div>
    </div>
  </article>

  <div class="grid grid-cols-2 gap-3 xl:grid-cols-4">
    <?= detail_card('Guest', $guestName) ?>
    <?= detail_card('Email', $booking['email']) ?>
    <?= detail_card('Phone', $booking['phone']) ?>
    <?= detail_card('Nationality', $booking['nationality']) ?>
    <?= detail_card('Country', $booking['country']) ?>
    <?= detail_card('Contact Method', $booking['contact_method']) ?>
    <?= detail_card('Tour', $booking['tour_name']) ?>
    <?= detail_card('Tour Slug', $booking['tour_slug']) ?>
    <?= detail_card('Travel Date', $booking['travel_date']) ?>
    <?= detail_card('Start Time', $booking['start_time']) ?>
    <?= detail_card('Adults', $booking['adults']) ?>
    <?= detail_card('Children', $booking['children']) ?>
    <?= detail_card('Pickup', yes_no($booking['pickup'])) ?>
    <?= detail_card('Pickup Location', $booking['pickup_location']) ?>
    <?= detail_card('Payment Method', $booking['payment_method']) ?>
    <?= detail_card('Promo Code', $booking['promo_code']) ?>
    <?= detail_card('Subtotal', money_value($booking['subtotal'], $booking['currency'])) ?>
    <?= detail_card('Discount', money_value($booking['discount'], $booking['currency'])) ?>
    <?= detail_card('Service Fee', money_value($booking['service_fee'], $booking['currency'])) ?>
    <?= detail_card('Total', money_value($booking['total'], $booking['currency'])) ?>
    <?= detail_card('Terms Accepted', yes_no($booking['agreed_terms'])) ?>
    <?= detail_card('Cancellation Accepted', yes_no($booking['agreed_cancellation'])) ?>
    <?= detail_card('Booking Created', $booking['created_at']) ?>
    <?= detail_card('Last Updated', $booking['updated_at']) ?>
  </div>

  <div class="grid gap-3 lg:grid-cols-2">
    <article class="admin-card">
      <p class="text-[11px] font-medium uppercase tracking-wide text-slate-400">Special Requests</p>
      <p class="mt-1 whitespace-pre-wrap text-sm leading-6 text-slate-700"><?= e($booking['special_requests'] ?: '-') ?></p>
    </article>
    <article class="admin-card">
      <p class="text-[11px] font-medium uppercase tracking-wide text-slate-400">Technical Details</p>
      <p class="mt-1 break-words text-sm leading-6 text-slate-700">IP: <?= e($booking['ip_address'] ?: '-') ?><br>User Agent: <?= e($booking['user_agent'] ?: '-') ?></p>
    </article>
  </div>
</section>
<?php
$downloadData = [
    'brandName' => $brandName,
    'brandLogo' => media_url($brandLogo),
    'bookingRef' => $booking['booking_ref'],
    'bookingNumber' => preg_match_all('/\d+/', (string) $booking['booking_ref'], $bookingRefNumbers) ? end($bookingRefNumbers[0]) : $booking['booking_ref'],
    'bookingYear' => date('Y', strtotime((string) ($booking['created_at'] ?: 'now'))),
    'createdAt' => $booking['created_at'],
    'guest' => $guestName,
    'tour' => $booking['tour_name'],
    'tourSlug' => $booking['tour_slug'],
    'travelDate' => $booking['travel_date'],
    'startTime' => $booking['start_time'],
    'email' => $booking['email'],
    'phone' => $booking['phone'],
    'nationality' => $booking['nationality'],
    'country' => $booking['country'],
    'contactMethod' => $booking['contact_method'],
    'adults' => $booking['adults'],
    'children' => $booking['children'],
    'pickup' => yes_no($booking['pickup']),
    'pickupLocation' => $booking['pickup_location'],
    'paymentMethod' => $booking['payment_method'],
    'subtotal' => money_value($booking['subtotal'], $booking['currency']),
    'discount' => money_value($booking['discount'], $booking['currency']),
    'serviceFee' => money_value($booking['service_fee'], $booking['currency']),
    'total' => money_value($booking['total'], $booking['currency']),
    'status' => $booking['status'],
    'paymentStatus' => $booking['payment_status'],
    'specialRequests' => $booking['special_requests'],
];
$script = 'const bookingDownloadData = ' . json_encode($downloadData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ';' . PHP_EOL . <<<'JS'
function htmlEscape(value) {
  return String(value ?? '').replace(/[&<>"']/g, (char) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;',
  })[char]);
}

async function downloadBookingPdf() {
  const fileSlug = (value) => String(value || 'booking')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-|-$/g, '');
  const brandName = bookingDownloadData.brandName || 'Hyena Admin';
  const brandSlug = fileSlug(brandName).split('-')[0] || 'hyena';
  const bookingNumber = bookingDownloadData.bookingNumber || fileSlug(bookingDownloadData.bookingRef);
  const year = bookingDownloadData.bookingYear || new Date().getFullYear();
  const filename = `${brandSlug}-${bookingNumber}-${year}.pdf`;

  const canvas = document.createElement('canvas');
  canvas.width = 1240;
  canvas.height = 1754;
  const ctx = canvas.getContext('2d');
  ctx.fillStyle = '#ffffff';
  ctx.fillRect(0, 0, canvas.width, canvas.height);

  const drawText = (text, x, y, size = 24, color = '#172033', weight = '400', maxWidth = 420) => {
    ctx.fillStyle = color;
    ctx.font = `${weight} ${size}px Arial, sans-serif`;
    ctx.fillText(String(text || '-'), x, y, maxWidth);
  };
  const drawCard = (x, y, w, h) => {
    ctx.fillStyle = '#f8fafc';
    ctx.strokeStyle = '#e2e8f0';
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.roundRect(x, y, w, h, 18);
    ctx.fill();
    ctx.stroke();
  };
  const wrapText = (text, x, y, maxWidth, lineHeight, maxLines = 3) => {
    const words = String(text || '-').split(/\s+/);
    let line = '';
    let lines = 0;
    for (const word of words) {
      const test = line ? `${line} ${word}` : word;
      if (ctx.measureText(test).width > maxWidth && line) {
        ctx.fillText(line, x, y);
        y += lineHeight;
        line = word;
        lines += 1;
        if (lines >= maxLines - 1) break;
      } else {
        line = test;
      }
    }
    ctx.fillText(line, x, y);
  };
  const loadImage = (src) => new Promise((resolve) => {
    if (!src) return resolve(null);
    const img = new Image();
    img.onload = () => resolve(img);
    img.onerror = () => resolve(null);
    img.src = src;
  });

  ctx.fillStyle = '#eaf7fc';
  ctx.fillRect(0, 0, canvas.width, 190);
  const logo = await loadImage(bookingDownloadData.brandLogo);
  if (logo) {
    const size = 104;
    ctx.drawImage(logo, 70, 44, size, size);
  } else {
    ctx.fillStyle = '#2FABE1';
    ctx.beginPath();
    ctx.roundRect(70, 44, 104, 104, 22);
    ctx.fill();
    drawText(brandSlug.charAt(0).toUpperCase(), 108, 112, 44, '#ffffff', '700', 80);
  }
  drawText(brandName, 205, 88, 34, '#172033', '700', 560);
  drawText(`${bookingDownloadData.bookingRef} Booking Form`, 205, 128, 22, '#64748b', '400', 560);
  drawText(`Year ${year}`, 1000, 88, 22, '#172033', '700', 160);
  drawText(`No. ${bookingNumber}`, 1000, 124, 20, '#64748b', '400', 180);

  const sections = [
    ['Guest', [
      ['Guest', bookingDownloadData.guest],
      ['Email', bookingDownloadData.email],
      ['Phone', bookingDownloadData.phone],
      ['Nationality', bookingDownloadData.nationality],
      ['Country', bookingDownloadData.country],
      ['Contact', bookingDownloadData.contactMethod],
    ]],
    ['Tour', [
      ['Tour', bookingDownloadData.tour],
      ['Tour Slug', bookingDownloadData.tourSlug],
      ['Date', bookingDownloadData.travelDate],
      ['Time', bookingDownloadData.startTime],
      ['Pickup', bookingDownloadData.pickup],
      ['Pickup Location', bookingDownloadData.pickupLocation],
    ]],
    ['Group', [
      ['Adults', bookingDownloadData.adults],
      ['Children', bookingDownloadData.children],
      ['Requests', bookingDownloadData.specialRequests],
    ]],
    ['Payment', [
      ['Method', bookingDownloadData.paymentMethod],
      ['Subtotal', bookingDownloadData.subtotal],
      ['Discount', bookingDownloadData.discount],
      ['Service Fee', bookingDownloadData.serviceFee],
      ['Total', bookingDownloadData.total],
      ['Status', bookingDownloadData.status],
      ['Payment Status', bookingDownloadData.paymentStatus],
      ['Created', bookingDownloadData.createdAt],
    ]],
  ];

  sections.forEach(([title, rows], index) => {
    const x = index % 2 === 0 ? 70 : 640;
    const y = index < 2 ? 250 : 800;
    drawCard(x, y, 530, 480);
    drawText(title, x + 28, y + 52, 28, '#172033', '700', 440);
    rows.forEach(([label, value], rowIndex) => {
      const rowY = y + 100 + rowIndex * 54;
      drawText(label, x + 28, rowY, 18, '#64748b', '700', 150);
      ctx.font = '400 20px Arial, sans-serif';
      ctx.fillStyle = '#172033';
      wrapText(value, x + 190, rowY, 290, 24, label === 'Requests' ? 4 : 2);
    });
  });

  drawText('Generated from admin booking details', 70, 1660, 18, '#94a3b8', '400', 500);

  const dataUrl = canvas.toDataURL('image/jpeg', 0.9);
  const imageBytes = Uint8Array.from(atob(dataUrl.split(',')[1]), (char) => char.charCodeAt(0));
  const encoder = new TextEncoder();
  const chunks = [];
  let offset = 0;
  const addChunk = (value) => {
    const chunk = typeof value === 'string' ? encoder.encode(value) : value;
    chunks.push(chunk);
    offset += chunk.length;
  };
  const offsets = [];
  const addObject = (number, bodyChunks) => {
    offsets[number] = offset;
    addChunk(`${number} 0 obj\n`);
    bodyChunks.forEach(addChunk);
    addChunk('\nendobj\n');
  };

  addChunk('%PDF-1.4\n');
  addObject(1, ['<< /Type /Catalog /Pages 2 0 R >>']);
  addObject(2, ['<< /Type /Pages /Kids [3 0 R] /Count 1 >>']);
  addObject(3, ['<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /XObject << /Im1 4 0 R >> >> /Contents 5 0 R >>']);
  addObject(4, [
    `<< /Type /XObject /Subtype /Image /Width ${canvas.width} /Height ${canvas.height} /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ${imageBytes.length} >>\nstream\n`,
    imageBytes,
    '\nendstream',
  ]);
  const content = 'q\n595 0 0 842 0 0 cm\n/Im1 Do\nQ';
  addObject(5, [`<< /Length ${content.length} >>\nstream\n${content}\nendstream`]);
  const xrefOffset = offset;
  addChunk('xref\n0 6\n0000000000 65535 f \n');
  for (let i = 1; i <= 5; i += 1) {
    addChunk(`${String(offsets[i]).padStart(10, '0')} 00000 n \n`);
  }
  addChunk(`trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n${xrefOffset}\n%%EOF`);

  const blob = new Blob(chunks, { type: 'application/pdf' });
  const link = document.createElement('a');
  link.href = URL.createObjectURL(blob);
  link.download = filename;
  document.body.appendChild(link);
  link.click();
  link.remove();
  URL.revokeObjectURL(link.href);
}
JS;

admin_footer($script);
