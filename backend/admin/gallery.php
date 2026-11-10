<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

admin_header('Gallery', 'gallery');
?>
<section>
  <div class="mb-5">
    <div>
      <h2 class="text-xl font-medium">Gallery</h2>
      <p class="mt-1 text-sm text-slate-500">Upload, edit, and remove gallery images.</p>
    </div>
  </div>
  <div id="galleryGrid" class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-5"></div>
</section>

<button id="addGalleryButton" class="primary fixed right-5 top-20 z-30" type="button" onclick="openAddGalleryModal()">
  <i class="fa-solid fa-plus"></i>
  <span>Add Image</span>
</button>

<div id="galleryModal" class="modal-backdrop fixed inset-0 z-50 items-start justify-center overflow-y-auto bg-slate-950/60 p-3 sm:p-4 md:items-center">
  <div class="my-3 w-full max-w-3xl rounded-lg bg-white p-4 shadow-2xl sm:my-6 sm:max-h-[calc(100vh-3rem)] sm:overflow-y-auto sm:p-5">
    <div class="sticky top-0 z-10 -mx-4 -mt-4 mb-4 flex items-center justify-between gap-3 border-b border-slate-100 bg-white px-4 py-3 sm:static sm:m-0 sm:mb-4 sm:border-0 sm:p-0">
      <h3 id="galleryModalTitle" class="text-lg font-medium">Add Image</h3>
      <button class="secondary grid h-8 w-8 place-items-center px-0 py-0" type="button" onclick="closeGalleryModal()" title="Close"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form id="galleryForm" class="grid gap-4">
      <input type="hidden" name="id">
      <input type="hidden" name="span" value="normal">
      <input type="hidden" name="sort_order" value="0">
      <div class="grid gap-4 md:grid-cols-[260px_minmax(0,1fr)] md:gap-5">
        <div class="grid content-start gap-3">
          <div class="grid h-48 max-h-48 min-h-48 place-items-center overflow-hidden rounded-lg border border-slate-200 bg-slate-50 sm:h-56 sm:max-h-56 sm:min-h-56 md:h-64 md:max-h-64 md:min-h-64">
            <img id="galleryPreview" class="hidden max-h-full max-w-full rounded-lg object-contain object-center" alt="">
            <span id="galleryPreviewEmpty" class="px-4 text-center text-sm font-normal text-slate-400">Select image</span>
          </div>
        </div>
        <div class="grid content-start gap-4">
          <label class="grid gap-1.5 text-sm font-medium text-slate-700">Title <textarea class="form-control h-[42px] min-h-0 resize-none overflow-hidden" name="title" rows="1" required></textarea></label>
          <div class="grid gap-3 md:grid-cols-2">
            <label class="grid gap-1.5 text-sm font-medium text-slate-700">Location <input class="form-control" name="location" value="Zanzibar"></label>
            <label class="grid gap-1.5 text-sm font-medium text-slate-700">Category
              <select class="form-control" name="category">
                <option value="Activities">Activities</option>
                <option value="Beach">Beach</option>
                <option value="Culture">Culture</option>
                <option value="Nature">Nature</option>
                <option value="Tours">Tours</option>
                <option value="Hotels">Hotels</option>
                <option value="Food">Food</option>
              </select>
            </label>
          </div>
          <label class="grid gap-1.5 text-sm font-medium text-slate-700">Image
            <button class="secondary inline-flex w-fit items-center justify-center gap-2" type="button" onclick="pickGalleryImage()"><i class="fa-solid fa-image"></i>Choose Image</button>
            <input id="galleryImageInput" class="hidden" name="image" type="file" accept="image/*">
            <span id="galleryImageFileName" class="text-xs font-normal text-slate-500">No image selected</span>
          </label>
          <input type="hidden" name="alt_text">
        </div>
      </div>
      <div class="grid gap-2 sm:flex sm:justify-end">
        <button class="secondary w-full sm:w-auto" type="button" onclick="closeGalleryModal()">Cancel</button>
        <button id="gallerySaveButton" class="primary w-full sm:w-auto" type="submit"><i class="fa-solid fa-upload"></i><span>Save Image</span></button>
      </div>
    </form>
  </div>
</div>
<?php
admin_footer(<<<'JS'
let galleryItems = [];
let galleryMaxImages = 100;

function media(path) {
  if (!path) return '';
  if (/^https?:\/\//.test(path)) return path;
  return path.startsWith('/') ? path : `/${path.replace(/^\/+/, '')}`;
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

async function loadGallery() {
  try {
    const data = await request(api('gallery.php'), { headers: adminHeaders() });
    galleryItems = data.items || [];
    galleryMaxImages = Number(data.max || 100);
    updateAddGalleryButton();
    document.querySelector('#galleryGrid').innerHTML = galleryItems.map((item) => `
      <article class="gallery-card">
        <img src="${media(item.image_path)}" alt="${escapeHtml(item.alt_text || item.title || 'Gallery image')}">
        <div class="gallery-card-body">
          <div class="min-w-0 overflow-hidden">
            <p class="gallery-card-title" title="${escapeHtml(item.title || 'Untitled image')}">${escapeHtml(item.title || 'Untitled image')}</p>
            <p class="gallery-card-meta">${escapeHtml(item.location || '')}${item.category ? ' | ' + escapeHtml(item.category) : ''}</p>
          </div>
          <div class="gallery-actions">
            <button class="secondary " onclick="openEditGalleryModal(${item.id})" title="Edit" aria-label="Edit"><i class="fa-solid fa-pen-to-square"></i></button>
            <button class="danger " onclick="deleteGallery(${item.id})" title="Delete" aria-label="Delete"><i class="fa-solid fa-trash"></i></button>
          </div>
        </div>
      </article>
    `).join('') || '<p class="text-sm text-slate-500">No gallery images found.</p>';
  } catch (error) {
    failure(error.message || 'Could not load gallery');
  }
}

function updateAddGalleryButton() {
  const button = document.querySelector('#addGalleryButton');
  const isFull = galleryItems.length >= galleryMaxImages;
  button.disabled = isFull;
  button.title = isFull ? `Gallery limit reached (${galleryItems.length}/${galleryMaxImages})` : `Add Image (${galleryItems.length}/${galleryMaxImages})`;
  button.classList.toggle('opacity-60', isFull);
  button.classList.toggle('cursor-not-allowed', isFull);
}

function fillGalleryForm(item = null) {
  const form = document.querySelector('#galleryForm');
  form.reset();
  form.id.value = item?.id || '';
  form.title.value = item?.title || '';
  form.location.value = item?.location || 'Zanzibar';
  setCategoryValue(item?.category || 'Activities');
  form.alt_text.value = item?.title || '';
  form.span.value = item?.span || 'normal';
  form.sort_order.value = item?.sort_order || nextGallerySortOrder();
  form.image.required = !item;
  document.querySelector('#galleryImageFileName').textContent = item ? 'Keep current image' : 'No image selected';
  const preview = document.querySelector('#galleryPreview');
  const empty = document.querySelector('#galleryPreviewEmpty');
  const hasImage = Boolean(item?.image_path);
  preview.classList.toggle('hidden', !hasImage);
  empty.classList.toggle('hidden', hasImage);
  preview.src = item?.image_path ? media(item.image_path) : '';
}

function setCategoryValue(category) {
  const select = document.querySelector('#galleryForm').category;
  if (![...select.options].some((option) => option.value === category)) {
    select.add(new Option(category, category));
  }
  select.value = category;
}

function nextGallerySortOrder() {
  return galleryItems.reduce((max, item) => Math.max(max, Number(item.sort_order || 0)), 0) + 10;
}

document.querySelector('#galleryForm').image.addEventListener('change', (event) => {
  const file = event.target.files?.[0];
  if (!file) return;
  document.querySelector('#galleryImageFileName').textContent = file.name;
  const preview = document.querySelector('#galleryPreview');
  const empty = document.querySelector('#galleryPreviewEmpty');
  preview.src = URL.createObjectURL(file);
  preview.classList.remove('hidden');
  empty.classList.add('hidden');
});

function pickGalleryImage() {
  document.querySelector('#galleryImageInput')?.click();
}

function openAddGalleryModal() {
  if (galleryItems.length >= galleryMaxImages) {
    return failure(`Gallery limit reached (${galleryItems.length}/${galleryMaxImages})`);
  }
  document.querySelector('#galleryModalTitle').textContent = 'Add Image';
  fillGalleryForm();
  document.querySelector('#galleryModal').classList.add('active');
}

function openEditGalleryModal(id) {
  const item = galleryItems.find((entry) => Number(entry.id) === Number(id));
  if (!item) return failure('Gallery image not found');
  document.querySelector('#galleryModalTitle').textContent = 'Edit Image';
  fillGalleryForm(item);
  document.querySelector('#galleryModal').classList.add('active');
}

function closeGalleryModal() {
  document.querySelector('#galleryModal').classList.remove('active');
}

document.querySelector('#galleryForm').addEventListener('submit', async (event) => {
  event.preventDefault();
  const form = event.currentTarget;
  const saveButton = document.querySelector('#gallerySaveButton');
  setButtonBusy(saveButton, true, form.id.value ? 'Updating...' : 'Uploading...');
  form.setAttribute('aria-busy', 'true');
  try {
    form.alt_text.value = form.title.value.trim();
    await request(api('gallery.php'), {
      method: 'POST',
      headers: adminHeaders(),
      body: new FormData(form),
    });
    await loadGallery();
    closeGalleryModal();
    success('Gallery image uploaded and displayed');
  } catch (error) {
    failure(error.message || 'Could not save gallery image');
  } finally {
    form.removeAttribute('aria-busy');
    setButtonBusy(saveButton, false);
  }
});

async function deleteGallery(id) {
  if (!await confirmDelete('Delete this gallery image?')) return;
  try {
    await request(api('gallery.php', { id }), { method: 'DELETE', headers: adminHeaders() });
    success('Gallery image deleted');
    await loadGallery();
  } catch (error) {
    failure(error.message || 'Could not delete gallery image');
  }
}

loadGallery();
JS);
