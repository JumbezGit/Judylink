<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

$publicAppUrl = rtrim(APP_URL, '/');
$listFields = [
    'highlights' => 'Highlights',
    'includes' => 'Includes',
    'excludes' => 'Excludes',
    'what_to_bring' => 'What To Bring',
    'languages' => 'Languages',
    'tags' => 'Tags',
];

admin_header('Tours', 'tours');
?>
<section class="grid gap-5">
  <div class="grid gap-4">
    <div class="mb-4 grid gap-1">
      <h2 class="text-xl font-medium">Tours</h2>
      <p id="popularCount" class="text-sm text-slate-500">Most Popular: 0/3 selected</p>
    </div>
    <div id="toursGrid" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"></div>
  </div>
</section>

<button id="addTourButton" class="primary fixed right-5 top-20 z-30" type="button" onclick="openTourForm()">
  <i class="fa-solid fa-plus"></i>
  <span>New Tour</span>
</button>

<div id="tourViewModal" class="modal-backdrop fixed inset-0 z-50 items-start justify-center overflow-y-auto bg-slate-950/60 p-3 sm:p-4">
  <div class="my-3 w-full max-w-5xl rounded-lg bg-white shadow-2xl sm:my-6">
    <div class="sticky top-0 z-10 flex items-center justify-between gap-3 border-b border-slate-100 bg-white p-4">
      <h3 id="tourViewTitle" class="text-lg font-medium">Tour Details</h3>
      <button class="secondary grid h-8 w-8 place-items-center px-0 py-0" type="button" onclick="closeTourView()" title="Close"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div id="tourViewBody" class="grid gap-5 p-4"></div>
  </div>
</div>

<div id="tourFormModal" class="modal-backdrop fixed inset-0 z-50 items-start justify-center overflow-y-auto bg-slate-950/60 p-3 sm:p-4">
  <div class="my-3 w-full max-w-6xl rounded-lg bg-white shadow-2xl sm:my-6">
    <div class="sticky top-0 z-10 flex items-center justify-between gap-3 border-b border-slate-100 bg-white p-4">
      <h3 id="tourFormTitle" class="text-lg font-medium">Add Tour</h3>
      <button class="secondary grid h-8 w-8 place-items-center px-0 py-0" type="button" onclick="closeTourForm()" title="Close"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="max-h-[calc(100vh-7rem)] overflow-y-auto p-4">
      <form id="tourForm" class="grid gap-4">
        <input type="hidden" name="id">
        <input type="hidden" name="image_path">
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
          <label class="grid gap-1.5 text-sm font-medium text-slate-700">Name <input class="form-control" name="name" placeholder="Prison Island" required></label>
          <label class="grid gap-1.5 text-sm font-medium text-slate-700">Category <input class="form-control" name="category" placeholder="Mnemba"></label>
          <label class="grid gap-1.5 text-sm font-medium text-slate-700">Location <input class="form-control" name="location" placeholder="Stone Town, Zanzibar"></label>
          <label class="grid gap-1.5 text-sm font-medium text-slate-700">Duration
            <select class="form-control" name="duration">
              <option value="2 Hours">2 Hours</option>
              <option value="Half Day">Half Day</option>
              <option value="Full Day">Full Day</option>
              <option value="Evening">Evening</option>
              <option value="Flexible">Flexible</option>
              <option value="Flexible Visit">Flexible Visit</option>
              <option value="1 Day or 3 Days">1 Day or 3 Days</option>
            </select>
          </label>
          <label class="grid gap-1.5 text-sm font-medium text-slate-700">Group Size
            <select class="form-control" name="group_size">
              <option value="Private or group">Private or group</option>
              <option value="Private">Private</option>
              <option value="Group">Group</option>
              <option value="Couples">Couples</option>
              <option value="Family friendly">Family friendly</option>
            </select>
          </label>
          <label class="grid gap-1.5 text-sm font-medium text-slate-700">Price <input class="form-control" name="price" type="number" step="0.01" placeholder="45.00"></label>
          <label class="grid gap-1.5 text-sm font-medium text-slate-700">Currency
            <select class="form-control" name="currency">
              <option value="USD">USD</option>
              <option value="TZS">TZS</option>
              <option value="EUR">EUR</option>
              <option value="GBP">GBP</option>
            </select>
          </label>
          <label class="grid gap-1.5 text-sm font-medium text-slate-700">Rating <input class="form-control" name="rating" type="number" step="0.1" placeholder="4.9"></label>
          <label class="grid gap-1.5 text-sm font-medium text-slate-700">Reviews <input class="form-control" name="review_count" type="number" placeholder="33"></label>
          <label class="grid gap-1.5 text-sm font-medium text-slate-700">Difficulty
            <select class="form-control" name="difficulty">
              <option value="Easy">Easy</option>
              <option value="Moderate">Moderate</option>
              <option value="Challenging">Challenging</option>
            </select>
          </label>
          <label class="grid gap-1.5 text-sm font-medium text-slate-700">Display Order <input class="form-control" name="display_order" type="number" placeholder="10"></label>
        </div>

        <section class="grid gap-3 rounded-lg border border-slate-200 bg-slate-50 p-4 sm:grid-cols-[160px_minmax(0,1fr)] sm:items-center">
          <div class="grid aspect-[4/3] place-items-center overflow-hidden rounded-lg bg-slate-100" id="mainImagePreview">
            <span class="px-3 text-center text-sm font-medium text-slate-400">No main image</span>
          </div>
          <div class="grid gap-2">
            <h4 class="text-sm font-medium text-slate-700">Main Image</h4>
            <label class="secondary inline-flex cursor-pointer items-center justify-center gap-2">
              <i class="fa-solid fa-image"></i>Choose File
              <input class="hidden" id="mainImageInput" type="file" accept="image/*" onchange="uploadMainImage(this)">
            </label>
          </div>
        </section>
        <label class="grid gap-1.5 text-sm font-medium text-slate-700">Description <textarea class="form-control min-h-28" name="description" placeholder="Write a clear tour overview for guests..."></textarea></label>
        <div class="flex flex-wrap gap-4">
          <label class="flex items-center gap-2 text-sm font-medium text-slate-700"><input name="pickup" type="checkbox" checked> Pickup available</label>
          <label class="flex items-center gap-2 text-sm font-medium text-slate-700"><input name="is_popular" type="checkbox"> Show in Most Popular</label>
        </div>
        <label class="grid gap-1.5 text-sm font-medium text-slate-700">Cancellation <input class="form-control" name="cancellation" placeholder="Free cancellation up to 24 hours before"></label>

        <div class="grid items-start gap-4 lg:grid-cols-2">
          <?php foreach ($listFields as $field => $label): ?>
            <section class="grid self-start gap-3 rounded-lg border border-slate-200 bg-slate-50 p-4">
              <div class="flex items-center justify-between gap-3">
                <h4 class="text-sm font-medium text-slate-700"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></h4>
                <button class="secondary" type="button" onclick="addListItem('<?= htmlspecialchars($field, ENT_QUOTES, 'UTF-8') ?>')"><i class="fa-solid fa-plus mr-2"></i>Add</button>
              </div>
              <div class="grid gap-2" data-array-field="<?= htmlspecialchars($field, ENT_QUOTES, 'UTF-8') ?>"></div>
              <textarea class="hidden" name="<?= htmlspecialchars($field, ENT_QUOTES, 'UTF-8') ?>"></textarea>
            </section>
          <?php endforeach; ?>

          <section class="grid self-start gap-3 rounded-lg border border-slate-200 bg-slate-50 p-4">
            <div class="flex items-center justify-between gap-3">
              <h4 class="text-sm font-medium text-slate-700">Gallery Images</h4>
              <button class="secondary" type="button" onclick="addGalleryItem()"><i class="fa-solid fa-plus mr-2"></i>Add Image</button>
            </div>
            <div class="grid gap-3 sm:grid-cols-2" data-gallery-field="gallery"></div>
            <textarea class="hidden" name="gallery"></textarea>
          </section>

          <section class="grid self-start gap-3 rounded-lg border border-slate-200 bg-slate-50 p-4">
            <div class="flex items-center justify-between gap-3">
              <h4 class="text-sm font-medium text-slate-700">Itinerary</h4>
              <button class="secondary" type="button" onclick="addObjectItem('itinerary')"><i class="fa-solid fa-plus mr-2"></i>Add</button>
            </div>
            <div class="grid gap-2" data-object-field="itinerary"></div>
            <textarea class="hidden" name="itinerary"></textarea>
          </section>

          <section class="grid self-start gap-3 rounded-lg border border-slate-200 bg-slate-50 p-4">
            <div class="flex items-center justify-between gap-3">
              <h4 class="text-sm font-medium text-slate-700">FAQs</h4>
              <button class="secondary" type="button" onclick="addObjectItem('faqs')"><i class="fa-solid fa-plus mr-2"></i>Add</button>
            </div>
            <div class="grid gap-2" data-object-field="faqs"></div>
            <textarea class="hidden" name="faqs"></textarea>
          </section>
        </div>

        <div class="flex flex-col-reverse justify-end gap-2 border-t border-slate-100 pt-4 sm:flex-row">
          <button class="secondary" type="button" onclick="closeTourForm()">Cancel</button>
          <button class="primary" type="submit">Save Tour</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php
$script = 'const publicAppUrl = ' . json_encode($publicAppUrl) . ';' . PHP_EOL . <<<'JS'
let tours = [];
let toursMaxItems = 20;
const listFields = ['highlights', 'includes', 'excludes', 'what_to_bring', 'languages', 'tags'];
const objectFields = {
  itinerary: ['time', 'activity'],
  faqs: ['q', 'a'],
};
const listPlaceholders = {
  highlights: 'Meet the giant tortoises of Prison Island',
  includes: 'Hotel pickup & drop-off',
  excludes: 'Personal expenses',
  what_to_bring: 'Sunscreen',
  languages: 'English',
  tags: 'beach',
};
const objectPlaceholders = {
  itinerary: {
    time: 'Pickup',
    activity: 'Pickup from your hotel',
  },
  faqs: {
    q: 'Is hotel pickup included?',
    a: 'Yes, hotel pickup is included.',
  },
};

function media(path) {
  if (!path) return '';
  if (/^https?:\/\//.test(path)) return path;
  return path.startsWith('/') ? path : `/${path.replace(/^\/+/, '')}`;
}

function renderMainImage(path = '') {
  const preview = document.querySelector('#mainImagePreview');
  preview.innerHTML = path
    ? `<img class="h-full w-full object-cover" src="${media(path)}" alt="">`
    : '<span class="px-3 text-center text-sm font-medium text-slate-400">No main image</span>';
}

function escapeHtml(value) {
  return String(value ?? '').replace(/[&<>"']/g, (char) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;',
  })[char]);
}

function publicTourUrl(tour) {
  return `${publicAppUrl}/tours/${encodeURIComponent(tour.slug || '')}`;
}

function findTour(id) {
  return tours.find((entry) => Number(entry.id) === Number(id));
}

function popularToursCount() {
  return tours.filter((tour) => Boolean(tour.is_popular)).length;
}

function updatePopularCount() {
  const count = popularToursCount();
  document.querySelector('#popularCount').textContent = `Most Popular: ${count}/3 selected`;
}

function renderPopularToggle(tour) {
  if (!tour.is_popular && popularToursCount() >= 3) return '';

  return `
          <label class="flex items-center justify-between gap-3 rounded-lg border border-slate-100 bg-slate-50 px-3 py-2 text-xs font-normal text-slate-600">
            <span>Show in Most Popular</span>
            <input type="checkbox" ${tour.is_popular ? 'checked' : ''} onchange="togglePopularTour(${tour.id}, this)">
          </label>`;
}

function renderPills(items = []) {
  return (items || []).map((item) => `<span class="rounded-md bg-slate-100 px-2 py-1 text-xs font-normal text-slate-600">${escapeHtml(item)}</span>`).join('');
}

function renderObjectList(items = [], firstKey, secondKey) {
  return (items || []).map((item) => `
    <li class="rounded-lg border border-slate-100 bg-slate-50 p-3">
      <strong class="block text-sm text-slate-900">${escapeHtml(item?.[firstKey] || '')}</strong>
      <span class="mt-1 block text-sm text-slate-600">${escapeHtml(item?.[secondKey] || '')}</span>
    </li>
  `).join('');
}

async function loadTours() {
  try {
    const data = await request(api('tours.php'), { headers: adminHeaders() });
    tours = data.tours || [];
    toursMaxItems = Number(data.max || 20);
    updateAddTourButton();
    updatePopularCount();
    document.querySelector('#toursGrid').innerHTML = tours.map((tour) => `
      <article class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-lg shadow-slate-900/5">
        <div class="relative aspect-[4/3] bg-slate-100">
          ${tour.image_path ? `<img class="h-full w-full object-cover" src="${media(tour.image_path)}" alt="${escapeHtml(tour.name)}">` : '<div class="grid h-full place-items-center text-sm font-normal text-slate-400">No image</div>'}
          <span class="tour-image-badge tour-category-badge">${escapeHtml(tour.category || 'Tour')}</span>
          ${tour.is_popular ? '<span class="tour-image-badge tour-popular-badge">Popular</span>' : ''}
        </div>
        <div class="grid gap-3 p-4">
          <div>
            <h3 class="line-clamp-2 text-base font-medium leading-snug text-slate-900">${escapeHtml(tour.name || 'Untitled tour')}</h3>
            <p class="mt-1 text-xs font-normal text-slate-500">${escapeHtml(tour.location || '')}</p>
          </div>
          ${renderPopularToggle(tour)}
          <div class="grid grid-cols-2 gap-2 rounded-lg bg-slate-50 p-3 text-center">
            <div><p class="text-[11px] font-medium uppercase text-slate-400">Price</p><p class="text-sm font-medium text-slate-900">${tour.price || 0} ${escapeHtml(tour.currency || 'USD')}</p></div>
            <div><p class="text-[11px] font-medium uppercase text-slate-400">Duration</p><p class="text-sm font-medium text-slate-900">${escapeHtml(tour.duration || '-')}</p></div>
          </div>
          <div class="flex flex-wrap gap-2">
            <button class="secondary inline-flex flex-1 items-center justify-center gap-2" type="button" onclick="openTourView(${tour.id})"><i class="fa-solid fa-eye"></i>View</button>
            <button class="primary inline-flex flex-1 items-center justify-center gap-2" type="button" onclick="openTourForm(${tour.id})"><i class="fa-solid fa-pen-to-square"></i>Edit</button>
            <button class="danger inline-flex flex-1 items-center justify-center gap-2" type="button" onclick="deleteTour(${tour.id})"><i class="fa-solid fa-trash"></i>Delete</button>
          </div>
        </div>
      </article>
    `).join('') || '<p class="text-sm text-slate-500">No tours found.</p>';
  } catch (error) {
    failure(error.message || 'Could not load tours');
  }
}

function updateAddTourButton() {
  const button = document.querySelector('#addTourButton');
  const isFull = tours.length >= toursMaxItems;
  button.disabled = isFull;
  button.title = isFull ? `Tour limit reached (${tours.length}/${toursMaxItems})` : `New Tour (${tours.length}/${toursMaxItems})`;
  button.classList.toggle('opacity-60', isFull);
  button.classList.toggle('cursor-not-allowed', isFull);
}

async function togglePopularTour(id, input) {
  const tour = findTour(id);
  if (!tour) return failure('Tour not found');
  const checked = input.checked;
  if (checked && popularToursCount() >= 3 && !tour.is_popular) {
    input.checked = false;
    return failure('Only three tours can show in Most Popular.');
  }

  input.disabled = true;
  try {
    await request(`${api('tours.php')}?id=${id}`, {
      method: 'PATCH',
      headers: jsonHeaders(),
      body: JSON.stringify({ is_popular: checked }),
    });
    tour.is_popular = checked;
    updatePopularCount();
    success(checked ? 'Tour added to Most Popular' : 'Tour removed from Most Popular');
    loadTours();
  } catch (error) {
    input.checked = !checked;
    failure(error.message || 'Could not update Most Popular');
  } finally {
    input.disabled = false;
  }
}

function openTourView(id) {
  const tour = findTour(id);
  if (!tour) return failure('Tour not found');
  document.querySelector('#tourViewTitle').textContent = tour.name || 'Tour Details';
  document.querySelector('#tourViewBody').innerHTML = `
    <div class="grid gap-5 lg:grid-cols-[360px_minmax(0,1fr)]">
      <img class="aspect-[4/3] w-full rounded-lg bg-slate-100 object-cover" src="${media(tour.image_path)}" alt="${escapeHtml(tour.name)}">
      <div class="grid content-start gap-4">
        <div class="flex flex-wrap gap-2">${renderPills([tour.category, tour.duration, tour.location].filter(Boolean))}</div>
        <p class="text-sm leading-6 text-slate-600">${escapeHtml(tour.description || '')}</p>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
          <div class="rounded-lg bg-slate-50 p-3"><p class="text-xs font-medium uppercase text-slate-400">Price</p><p class="font-medium">${tour.price || 0} ${escapeHtml(tour.currency || 'USD')}</p></div>
          <div class="rounded-lg bg-slate-50 p-3"><p class="text-xs font-medium uppercase text-slate-400">Rating</p><p class="font-medium">${tour.rating || 0}</p></div>
          <div class="rounded-lg bg-slate-50 p-3"><p class="text-xs font-medium uppercase text-slate-400">Reviews</p><p class="font-medium">${tour.review_count || 0}</p></div>
          <div class="rounded-lg bg-slate-50 p-3"><p class="text-xs font-medium uppercase text-slate-400">Pickup</p><p class="font-medium">${tour.pickup ? 'Yes' : 'No'}</p></div>
        </div>
        <a class="secondary inline-flex w-fit items-center gap-2 no-underline" href="${publicTourUrl(tour)}" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square"></i>Open public page</a>
      </div>
    </div>
    <div class="grid gap-4 lg:grid-cols-2">
      <section><h4 class="mb-2 text-sm font-medium text-slate-900">Highlights</h4><div class="flex flex-wrap gap-2">${renderPills(tour.highlights)}</div></section>
      <section><h4 class="mb-2 text-sm font-medium text-slate-900">Includes</h4><div class="flex flex-wrap gap-2">${renderPills(tour.includes)}</div></section>
      <section><h4 class="mb-2 text-sm font-medium text-slate-900">Itinerary</h4><ul class="grid gap-2">${renderObjectList(tour.itinerary, 'time', 'activity')}</ul></section>
      <section><h4 class="mb-2 text-sm font-medium text-slate-900">FAQs</h4><ul class="grid gap-2">${renderObjectList(tour.faqs, 'q', 'a')}</ul></section>
    </div>
  `;
  document.querySelector('#tourViewModal').classList.add('active');
}

function closeTourView() {
  document.querySelector('#tourViewModal').classList.remove('active');
}

function listContainer(field) {
  return document.querySelector(`[data-array-field="${field}"]`);
}

function objectContainer(field) {
  return document.querySelector(`[data-object-field="${field}"]`);
}

function galleryContainer() {
  return document.querySelector('[data-gallery-field="gallery"]');
}

function listValues(field) {
  return [...listContainer(field).querySelectorAll('[data-item-input]')]
    .map((input) => input.value.trim())
    .filter(Boolean);
}

function objectValues(field) {
  const keys = objectFields[field];
  return [...objectContainer(field).querySelectorAll('[data-object-row]')]
    .map((row) => Object.fromEntries(keys.map((key) => [key, row.querySelector(`[data-object-key="${key}"]`).value.trim()])))
    .filter((item) => Object.values(item).some(Boolean));
}

function syncField(field, value) {
  document.querySelector(`#tourForm [name="${field}"]`).value = JSON.stringify(value, null, 2);
}

function syncAllArrayFields() {
  for (const field of listFields) syncField(field, listValues(field));
  syncField('gallery', galleryValues());
  for (const field of Object.keys(objectFields)) syncField(field, objectValues(field));
}

function renderListField(field, values = []) {
  const rows = values.length ? values : [''];
  listContainer(field).innerHTML = rows.map((value) => `
    <div class="flex gap-2" data-array-row>
      <input class="form-control" data-item-input placeholder="${escapeHtml(listPlaceholders[field] || 'Add item')}" value="${escapeHtml(value)}">
      <button class="danger grid h-10 w-10 shrink-0 place-items-center px-0 py-0" type="button" onclick="removeArrayRow(this)" title="Remove"><i class="fa-solid fa-trash"></i></button>
    </div>
  `).join('');
  syncField(field, listValues(field));
}

function renderObjectField(field, values = []) {
  const keys = objectFields[field];
  const rows = values.length ? values : [{}];
  objectContainer(field).innerHTML = rows.map((value) => `
    <div class="grid gap-2 rounded-md border border-slate-200 bg-white p-2 sm:grid-cols-[minmax(0,0.7fr)_minmax(0,1fr)_auto]" data-object-row>
      ${keys.map((key) => `<input class="form-control" data-object-key="${key}" placeholder="${escapeHtml(objectPlaceholders[field]?.[key] || key)}" value="${escapeHtml(value?.[key] ?? '')}">`).join('')}
      <button class="danger grid h-10 w-10 shrink-0 place-items-center px-0 py-0" type="button" onclick="removeArrayRow(this)" title="Remove"><i class="fa-solid fa-trash"></i></button>
    </div>
  `).join('');
  syncField(field, objectValues(field));
}

function galleryValues() {
  return [...galleryContainer().querySelectorAll('[data-gallery-path]')]
    .map((input) => input.value.trim())
    .filter(Boolean);
}

function renderGalleryField(values = []) {
  const rows = values.length ? values : [''];
  galleryContainer().innerHTML = rows.map((value) => {
    const hasImage = Boolean(value);
    return `
      <div class="grid gap-2 rounded-md border border-slate-200 bg-white p-2" data-gallery-row>
        <div class="grid aspect-[4/3] place-items-center overflow-hidden rounded-md bg-slate-100" data-gallery-preview>
          ${hasImage ? `<img class="h-full w-full object-cover" src="${media(value)}" alt="">` : '<span class="text-sm font-normal text-slate-400">Choose image</span>'}
        </div>
        <input type="hidden" data-gallery-path value="${escapeHtml(value)}">
        <div class="flex gap-2">
          <label class="secondary inline-flex flex-1 cursor-pointer items-center justify-center gap-2">
            <i class="fa-solid fa-image"></i>Choose File
            <input class="hidden" type="file" accept="image/*" onchange="uploadGalleryImage(this)">
          </label>
          <button class="danger grid h-10 w-10 shrink-0 place-items-center px-0 py-0" type="button" onclick="removeArrayRow(this)" title="Remove"><i class="fa-solid fa-trash"></i></button>
        </div>
      </div>
    `;
  }).join('');
  syncField('gallery', galleryValues());
}

function addListItem(field, value = '') {
  const values = listValues(field);
  values.push(value);
  renderListField(field, values);
  listContainer(field).querySelector('[data-array-row]:last-child input')?.focus();
}

function addObjectItem(field, value = {}) {
  const values = objectValues(field);
  values.push(value);
  renderObjectField(field, values);
  objectContainer(field).querySelector('[data-object-row]:last-child input')?.focus();
}

function addGalleryItem(value = '') {
  const values = galleryValues();
  values.push(value);
  renderGalleryField(values);
}

async function uploadGalleryImage(input) {
  const file = input.files?.[0];
  if (!file) return;
  const row = input.closest('[data-gallery-row]');
  const body = new FormData();
  body.append('image', file);
  try {
    const data = await request(api('tour-image.php'), {
      method: 'POST',
      headers: adminHeaders(),
      body,
    });
    row.querySelector('[data-gallery-path]').value = data.image_path;
    row.querySelector('[data-gallery-preview]').innerHTML = `<img class="h-full w-full object-cover" src="${media(data.image_path)}" alt="">`;
    syncField('gallery', galleryValues());
    success('Gallery image uploaded');
  } catch (error) {
    input.value = '';
    failure(error.message || 'Could not upload gallery image');
  }
}

async function uploadMainImage(input) {
  const file = input.files?.[0];
  if (!file) return;
  const body = new FormData();
  body.append('image', file);
  try {
    const data = await request(api('tour-image.php'), {
      method: 'POST',
      headers: adminHeaders(),
      body,
    });
    document.querySelector('#tourForm').image_path.value = data.image_path;
    renderMainImage(data.image_path);
    input.value = '';
    success('Main image uploaded');
  } catch (error) {
    input.value = '';
    failure(error.message || 'Could not upload main image');
  }
}

function removeArrayRow(button) {
  const group = button.closest('[data-array-field], [data-object-field], [data-gallery-field]');
  button.closest('[data-array-row], [data-object-row], [data-gallery-row]')?.remove();
  if (group.dataset.arrayField) renderListField(group.dataset.arrayField, listValues(group.dataset.arrayField));
  if (group.dataset.objectField) renderObjectField(group.dataset.objectField, objectValues(group.dataset.objectField));
  if (group.dataset.galleryField) renderGalleryField(galleryValues());
}

function setDefaults() {
  const form = document.querySelector('#tourForm');
  form.reset();
  form.id.value = '';
  form.currency.value = 'USD';
  form.rating.value = '4.9';
  form.review_count.value = '0';
  form.price.value = '0';
  form.display_order.value = '0';
  form.pickup.checked = true;
  form.is_popular.checked = false;
  form.cancellation.value = 'Free cancellation up to 24 hours before';
  form.group_size.value = 'Private or group';
  form.difficulty.value = 'Easy';
  renderMainImage('');
  for (const field of listFields) renderListField(field, []);
  renderGalleryField([]);
  for (const field of Object.keys(objectFields)) renderObjectField(field, []);
}

function setFieldValue(form, field, value) {
  const input = form[field];
  if (!input) return;
  if (input.tagName === 'SELECT' && value && ![...input.options].some((option) => option.value === String(value))) {
    input.add(new Option(value, value));
  }
  input.value = value ?? '';
}

function fillTourForm(tour) {
  const form = document.querySelector('#tourForm');
  for (const field of ['id', 'name', 'category', 'location', 'duration', 'group_size', 'price', 'currency', 'rating', 'review_count', 'difficulty', 'display_order', 'image_path', 'description', 'cancellation']) {
    setFieldValue(form, field, tour?.[field] ?? '');
  }
  form.pickup.checked = tour ? Boolean(tour.pickup) : true;
  form.is_popular.checked = tour ? Boolean(tour.is_popular) : false;
  renderMainImage(tour?.image_path || '');
  for (const field of listFields) renderListField(field, Array.isArray(tour?.[field]) ? tour[field] : []);
  renderGalleryField(Array.isArray(tour?.gallery) ? tour.gallery : []);
  for (const field of Object.keys(objectFields)) renderObjectField(field, Array.isArray(tour?.[field]) ? tour[field] : []);
}

function openTourForm(id = 0) {
  if (!id && tours.length >= toursMaxItems) {
    return failure(`Tour limit reached (${tours.length}/${toursMaxItems})`);
  }
  setDefaults();
  const tour = id ? findTour(id) : null;
  if (id && !tour) return failure('Tour not found');
  if (tour) fillTourForm(tour);
  document.querySelector('#tourFormTitle').textContent = tour ? 'Edit Tour' : 'Add Tour';
  document.querySelector('#tourFormModal').classList.add('active');
}

function closeTourForm() {
  document.querySelector('#tourFormModal').classList.remove('active');
}

function formDataToTour(form) {
  syncAllArrayFields();
  const data = asJson(form);
  for (const field of [...listFields, 'gallery', ...Object.keys(objectFields)]) {
    try {
      data[field] = JSON.parse(data[field] || '[]');
    } catch {
      throw new Error(`${field} must be valid JSON`);
    }
  }
  data.pickup = form.pickup.checked;
  data.is_popular = form.is_popular.checked;
  data.price = Number(data.price || 0);
  data.rating = Number(data.rating || 0);
  data.review_count = Number(data.review_count || 0);
  data.display_order = Number(data.display_order || 0);
  data.slug = data.name.toLowerCase().replace(/&/g, 'and').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
  return data;
}

document.addEventListener('input', (event) => {
  if (event.target.matches('[data-item-input], [data-object-key]')) syncAllArrayFields();
});

document.querySelector('#tourForm').addEventListener('submit', async (event) => {
  event.preventDefault();
  try {
    const data = formDataToTour(event.currentTarget);
    const id = Number(data.id || 0);
    delete data.id;
    await request(id ? `${api('tours.php')}?id=${id}` : api('tours.php'), {
      method: id ? 'PUT' : 'POST',
      headers: jsonHeaders(),
      body: JSON.stringify(data),
    });
    closeTourForm();
    success('Tour saved');
    loadTours();
  } catch (error) {
    failure(error.message || 'Could not save tour');
  }
});

async function deleteTour(id) {
  if (!await confirmDelete('Delete this tour?')) return;
  try {
    await request(`${api('tours.php')}?id=${id}`, { method: 'DELETE', headers: adminHeaders() });
    success('Tour deleted');
    loadTours();
  } catch (error) {
    failure(error.message || 'Could not delete tour');
  }
}

setDefaults();
loadTours();
JS;

admin_footer($script);

