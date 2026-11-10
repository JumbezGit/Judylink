<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

$tourId = (int) ($_GET['id'] ?? 0);
$isEditing = $tourId > 0;

admin_header($isEditing ? 'Edit Tour' : 'Add Tour', 'tours');
?>
<section class="grid gap-5">
  <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
    <div>
      <a class="text-sm font-normal text-slate-500 no-underline hover:text-[#2FABE1]" href="<?= admin_url('tours') ?>"><i class="fa-solid fa-arrow-left mr-2"></i>Back to tours</a>
      <h2 id="tourFormTitle" class="mt-2 text-xl font-medium"><?= $isEditing ? 'Edit Tour' : 'Add Tour' ?></h2>
    </div>
    <a class="secondary inline-flex items-center justify-center no-underline" href="<?= admin_url('tours') ?>">Cancel</a>
  </div>

  <div class="admin-card">
    <form id="tourImageForm" class="mb-5 grid gap-3 rounded-lg bg-slate-50 p-4 md:grid-cols-[minmax(0,1fr)_auto] md:items-end">
      <label class="grid gap-1.5 text-sm font-medium text-slate-700">Upload Main Image
        <button class="secondary inline-flex w-fit items-center justify-center gap-2" type="button" onclick="pickTourMainImage()"><i class="fa-solid fa-image"></i>Choose Image</button>
        <input id="tourMainImageInput" class="hidden" name="image" type="file" accept="image/*" onchange="setTourMainImageName(this)">
        <span id="tourMainImageFileName" class="text-xs font-normal text-slate-500">No image selected</span>
      </label>
      <button id="tourImageUploadButton" class="secondary" type="submit"><i class="fa-solid fa-upload"></i><span>Upload Image</span></button>
    </form>

    <form id="tourForm" class="grid gap-4">
      <input type="hidden" name="id" value="<?= $tourId ?: '' ?>">
      <div class="grid gap-4 lg:grid-cols-2">
        <label class="grid gap-1.5 text-sm font-medium text-slate-700">Name <input class="form-control" name="name" required></label>
        <label class="grid gap-1.5 text-sm font-medium text-slate-700">Slug <input class="form-control" name="slug"></label>
        <label class="grid gap-1.5 text-sm font-medium text-slate-700">Category
          <select class="form-control" name="category">
            <option value="Activities">Activities</option>
            <option value="Beach">Beach</option>
            <option value="Culture">Culture</option>
            <option value="Nature">Nature</option>
            <option value="Tours">Tours</option>
            <option value="Hotels">Hotels</option>
            <option value="Food">Food</option>
            <option value="Unique">Unique</option>
          </select>
        </label>
        <label class="grid gap-1.5 text-sm font-medium text-slate-700">Location <input class="form-control" name="location"></label>
        <label class="grid gap-1.5 text-sm font-medium text-slate-700">Duration <input class="form-control" name="duration"></label>
        <label class="grid gap-1.5 text-sm font-medium text-slate-700">Group Size <input class="form-control" name="group_size"></label>
        <label class="grid gap-1.5 text-sm font-medium text-slate-700">Currency
          <select class="form-control" name="currency">
            <option value="USD">USD</option>
            <option value="TZS">TZS</option>
            <option value="EUR">EUR</option>
            <option value="GBP">GBP</option>
          </select>
        </label>
        <label class="grid gap-1.5 text-sm font-medium text-slate-700">Price <input class="form-control" name="price" type="number" min="0" step="0.01"></label>
        <label class="grid gap-1.5 text-sm font-medium text-slate-700">Rating (0–5) <input class="form-control" name="rating" type="number" min="0" max="5" step="0.1"></label>
        <label class="grid gap-1.5 text-sm font-medium text-slate-700">Reviews <input class="form-control" name="review_count" type="number" min="0" step="1"></label>
        <label class="grid gap-1.5 text-sm font-medium text-slate-700">Difficulty <input class="form-control" name="difficulty"></label>
        <label class="grid gap-1.5 text-sm font-medium text-slate-700">Display Order <input class="form-control" name="display_order" type="number" min="0" step="1"></label>
      </div>

      <label class="grid gap-1.5 text-sm font-medium text-slate-700">Main Image Path <input class="form-control" name="image_path"></label>
      <label class="grid gap-1.5 text-sm font-medium text-slate-700">Description <textarea class="form-control min-h-28" name="description"></textarea></label>
      <label class="flex items-center gap-2 text-sm font-medium text-slate-700"><input name="pickup" type="checkbox" checked> Pickup available</label>
      <label class="grid gap-1.5 text-sm font-medium text-slate-700">Cancellation <input class="form-control" name="cancellation"></label>

      <div class="grid gap-4 lg:grid-cols-2">
        <?php foreach ([
            'highlights' => 'Highlights',
            'includes' => 'Includes',
            'excludes' => 'Excludes',
            'what_to_bring' => 'What To Bring',
            'languages' => 'Languages',
            'gallery' => 'Gallery Images',
            'tags' => 'Tags',
        ] as $field => $label): ?>
          <section class="grid gap-3 rounded-lg border border-slate-200 bg-slate-50 p-4">
            <div class="flex items-center justify-between gap-3">
              <h3 class="text-sm font-medium text-slate-700"><?= $label ?></h3>
              <button class="secondary" type="button" onclick="addListItem('<?= $field ?>')"><i class="fa-solid fa-plus mr-2"></i>Add</button>
            </div>
            <div class="grid gap-2" data-array-field="<?= $field ?>"></div>
            <textarea class="hidden" name="<?= $field ?>"></textarea>
          </section>
        <?php endforeach; ?>

        <section class="grid gap-3 rounded-lg border border-slate-200 bg-slate-50 p-4">
          <div class="flex items-center justify-between gap-3">
            <h3 class="text-sm font-medium text-slate-700">Itinerary</h3>
            <button class="secondary" type="button" onclick="addObjectItem('itinerary')"><i class="fa-solid fa-plus mr-2"></i>Add</button>
          </div>
          <div class="grid gap-2" data-object-field="itinerary"></div>
          <textarea class="hidden" name="itinerary"></textarea>
        </section>

        <section class="grid gap-3 rounded-lg border border-slate-200 bg-slate-50 p-4">
          <div class="flex items-center justify-between gap-3">
            <h3 class="text-sm font-medium text-slate-700">FAQs</h3>
            <button class="secondary" type="button" onclick="addObjectItem('faqs')"><i class="fa-solid fa-plus mr-2"></i>Add</button>
          </div>
          <div class="grid gap-2" data-object-field="faqs"></div>
          <textarea class="hidden" name="faqs"></textarea>
        </section>
      </div>

      <div class="flex flex-col-reverse justify-end gap-2 sm:flex-row">
        <a class="secondary inline-flex items-center justify-center no-underline" href="<?= admin_url('tours') ?>">Cancel</a>
        <button class="primary" type="submit">Save Tour</button>
      </div>
    </form>
  </div>
</section>
<?php
$script = 'const tourId = ' . json_encode($tourId) . ';' . PHP_EOL . <<<'JS'

const listFields = ['highlights', 'includes', 'excludes', 'what_to_bring', 'languages', 'gallery', 'tags'];
const languageOptions = ['English', 'French', 'Spanish', 'Italian', 'Arabic', 'German', 'Hungarian', 'Polish', 'Russian'];
const objectFields = {
  itinerary: ['time', 'activity'],
  faqs: ['q', 'a'],
};

function escapeHtml(value) {
  return String(value ?? '').replace(/[&<>"']/g, (char) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;',
  })[char]);
}

function listContainer(field) {
  return document.querySelector(`[data-array-field="${field}"]`);
}

function objectContainer(field) {
  return document.querySelector(`[data-object-field="${field}"]`);
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
  for (const field of listFields) {
    syncField(field, listValues(field));
  }
  for (const field of Object.keys(objectFields)) {
    syncField(field, objectValues(field));
  }
}

function renderListField(field, values = []) {
  const container = listContainer(field);
  const rows = values.length ? values : [''];
  container.innerHTML = rows.map((value) => `
    <div class="flex gap-2" data-array-row>
      ${field === 'languages'
        ? `<select class="form-control" data-item-input>
            <option value="">Select language</option>
            ${(!languageOptions.includes(value) && value ? [value, ...languageOptions] : languageOptions)
              .map((language) => `<option value="${escapeHtml(language)}"${language === value ? ' selected' : ''}>${escapeHtml(language)}</option>`)
              .join('')}
          </select>`
        : `<input class="form-control" data-item-input value="${escapeHtml(value)}">`}
      <button class="danger grid h-10 w-10 shrink-0 place-items-center px-0 py-0" type="button" onclick="removeArrayRow(this)" title="Remove" aria-label="Remove"><i class="fa-solid fa-trash"></i></button>
    </div>
  `).join('');
  syncField(field, listValues(field));
}

function renderObjectField(field, values = []) {
  const container = objectContainer(field);
  const keys = objectFields[field];
  const rows = values.length ? values : [{}];
  container.innerHTML = rows.map((value) => `
    <div class="grid gap-2 rounded-md border border-slate-200 bg-white p-2 sm:grid-cols-[minmax(0,0.7fr)_minmax(0,1fr)_auto]" data-object-row>
      ${keys.map((key) => `<input class="form-control" data-object-key="${key}" placeholder="${key}" value="${escapeHtml(value?.[key] ?? '')}">`).join('')}
      <button class="danger grid h-10 w-10 shrink-0 place-items-center px-0 py-0" type="button" onclick="removeArrayRow(this)" title="Remove" aria-label="Remove"><i class="fa-solid fa-trash"></i></button>
    </div>
  `).join('');
  syncField(field, objectValues(field));
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

function removeArrayRow(button) {
  const group = button.closest('[data-array-field], [data-object-field]');
  const row = button.closest('[data-array-row], [data-object-row]');
  row?.remove();
  if (group.dataset.arrayField) {
    renderListField(group.dataset.arrayField, listValues(group.dataset.arrayField));
  }
  if (group.dataset.objectField) {
    renderObjectField(group.dataset.objectField, objectValues(group.dataset.objectField));
  }
}

document.addEventListener('input', (event) => {
  if (event.target.matches('[data-item-input], [data-object-key]')) {
    syncAllArrayFields();
  }
});

function formDataToTour(form) {
  syncAllArrayFields();
  const data = asJson(form);
  const jsonFields = ['highlights', 'includes', 'excludes', 'what_to_bring', 'languages', 'gallery', 'itinerary', 'faqs', 'tags'];
  for (const field of jsonFields) {
    try {
      data[field] = JSON.parse(data[field] || '[]');
    } catch {
      throw new Error(`${field} must be valid JSON`);
    }
  }
  data.pickup = form.pickup.checked;
  data.price = Number(data.price || 0);
  data.rating = Number(data.rating || 0);
  data.review_count = Number(data.review_count || 0);
  data.display_order = Number(data.display_order || 0);
  return data;
}

function setDefaults() {
  const form = document.querySelector('#tourForm');
  form.currency.value = 'USD';
  form.rating.value = '4.9';
  form.review_count.value = '0';
  form.price.value = '0';
  form.display_order.value = '0';
  form.pickup.checked = true;
  form.cancellation.value = 'Free cancellation up to 24 hours before';
  form.group_size.value = 'Private or group';
  form.difficulty.value = 'Easy';
  for (const field of listFields) {
    renderListField(field, []);
  }
  for (const field of Object.keys(objectFields)) {
    renderObjectField(field, []);
  }
}

async function loadTourForEdit() {
  if (!tourId) return;
  try {
    const data = await request(api('tours.php'), { headers: adminHeaders() });
    const tour = (data.tours || []).find((entry) => Number(entry.id) === Number(tourId));
    if (!tour) throw new Error('Tour not found');
    const form = document.querySelector('#tourForm');
    for (const field of ['id', 'name', 'slug', 'category', 'location', 'duration', 'group_size', 'price', 'currency', 'rating', 'review_count', 'difficulty', 'display_order', 'image_path', 'description', 'cancellation']) {
      const input = form[field];
      const value = tour[field] ?? '';
      if (input?.tagName === 'SELECT' && value && ![...input.options].some((option) => option.value === String(value))) {
        input.add(new Option(value, value));
      }
      input.value = value;
    }
    form.pickup.checked = Boolean(tour.pickup);
    for (const field of listFields) {
      renderListField(field, Array.isArray(tour[field]) ? tour[field] : []);
    }
    for (const field of Object.keys(objectFields)) {
      renderObjectField(field, Array.isArray(tour[field]) ? tour[field] : []);
    }
  } catch (error) {
    failure(error.message || 'Could not load tour');
  }
}

document.querySelector('#tourImageForm').addEventListener('submit', async (event) => {
  event.preventDefault();
  const uploadButton = document.querySelector('#tourImageUploadButton');
  setButtonBusy(uploadButton, true);
  try {
    const data = await request(api('tour-image.php'), {
      method: 'POST',
      headers: adminHeaders(),
      body: new FormData(event.currentTarget),
    });
    document.querySelector('#tourForm').image_path.value = data.image_path;
    event.currentTarget.reset();
    document.querySelector('#tourMainImageFileName').textContent = 'No image selected';
    success('Tour image uploaded');
  } catch (error) {
    failure(error.message || 'Could not upload tour image');
  } finally {
    setButtonBusy(uploadButton, false);
  }
});

function pickTourMainImage() {
  document.querySelector('#tourMainImageInput')?.click();
}

function setTourMainImageName(input) {
  const file = input.files?.[0];
  if (file) document.querySelector('#tourMainImageFileName').textContent = file.name;
}

document.querySelector('#tourForm').addEventListener('submit', async (event) => {
  event.preventDefault();
  try {
    const data = formDataToTour(event.currentTarget);
    const id = Number(data.id || 0);
    delete data.id;
    await request(id ? api('tours.php', { id }) : api('tours.php'), {
      method: id ? 'PUT' : 'POST',
      headers: jsonHeaders(),
      body: JSON.stringify(data),
    });
    success('Tour saved');
    window.setTimeout(() => { window.location.href = '/backend/secure-operation-4f2a/tours'; }, 700);
  } catch (error) {
    failure(error.message || 'Could not save tour');
  }
});

setDefaults();
loadTourForEdit();
JS;

admin_footer($script);

