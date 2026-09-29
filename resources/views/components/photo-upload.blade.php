@props([
    'name',
    'label' => 'Photo',
    'hint' => null,
    'optional' => true,
    // One file, or a gallery of up to maxFiles (name must then end in "[]").
    'multiple' => false,
    'maxFiles' => 10,
    'maxMb' => 5,
    // MIME types accepted, and how to describe them to people.
    'accept' => 'image/jpeg,image/png,image/webp',
    'types' => 'JPG, PNG or WebP',
    'prompt' => null,
])

{{--
    Upload box with a tap/drag drop zone and live previews (images as
    thumbnails, PDFs as a file icon). Types and size are checked here for
    instant feedback and again by the server's validation rules. On phones
    the browser offers camera or gallery for image fields.

    <x-photo-upload name="photo" label="Photo of the issue" />
    <x-photo-upload name="images[]" label="Photos" multiple :max-mb="4" />
    <x-photo-upload name="document" label="Lease agreement" accept="application/pdf,image/jpeg,image/png" types="PDF, JPG or PNG" :max-mb="10" :optional="false" prompt="Choose the signed agreement" />
--}}
@php
    $field = str_replace('[]', '', $name);
    $errorKeys = [$field, $field.'.*'];
    $fieldError = collect($errorKeys)->map(fn ($k) => $errors->first($k))->filter()->first();
    $isImageOnly = ! str_contains($accept, 'pdf');
    $id = 'upload-'.$field.'-'.uniqid();
    $prompt ??= $isImageOnly ? ($multiple ? 'Add photos' : 'Take a photo or choose one') : 'Choose a file';
@endphp

<div class="photo-upload" data-photo-upload
     data-accept="{{ $accept }}" data-types="{{ $types }}" data-max-bytes="{{ $maxMb * 1024 * 1024 }}" data-max-mb="{{ $maxMb }}"
     data-multiple="{{ $multiple ? '1' : '0' }}" data-max-files="{{ $multiple ? $maxFiles : 1 }}">
    <span class="form-label d-block">
        {{ $label }} @if ($optional)<span class="text-muted fw-normal">(optional)</span>@endif
    </span>

    <input type="file" id="{{ $id }}" name="{{ $name }}" class="pu-input" accept="{{ $accept }}" @if ($multiple) multiple @endif @if (! $optional) required @endif data-pu-input>

    <label for="{{ $id }}" class="pu-drop @if ($fieldError) pu-invalid @endif" data-pu-drop>
        <span class="pu-icon"><i class="bi {{ $isImageOnly ? 'bi-camera' : 'bi-file-earmark-arrow-up' }}"></i></span>
        <span class="fw-semibold">{{ $prompt }}</span>
        <span class="small text-muted d-none d-md-inline">or drag and drop {{ $multiple ? 'them' : 'it' }} here</span>
        <span class="small text-muted">{{ $types }} &middot; up to {{ $maxMb }} MB{{ $multiple ? ' each, max '.$maxFiles.' files' : '' }}</span>
    </label>

    <div class="pu-list d-none" data-pu-list></div>

    <div class="small text-danger mt-1" data-pu-error>{{ $fieldError }}</div>
    @if ($errors->any() && ! $fieldError)
        <div class="small text-muted mt-1"><i class="bi bi-info-circle me-1"></i>Please attach the {{ $multiple ? 'files' : 'file' }} again — uploads aren't kept when the form has an error.</div>
    @elseif ($hint)
        <div class="form-text">{{ $hint }}</div>
    @endif
</div>

@once
    <style>
        .photo-upload { position: relative; }
        .photo-upload .pu-input { position: absolute; width: 1px; height: 1px; opacity: 0; overflow: hidden; }
        .photo-upload .pu-drop {
            display: flex; flex-direction: column; align-items: center; gap: .15rem;
            padding: 1.5rem 1rem; text-align: center; cursor: pointer; margin: 0;
            border: 2px dashed #c9d3cd; border-radius: .75rem; background: #fafbfa;
            transition: border-color .15s, background-color .15s;
        }
        .photo-upload .pu-drop:hover, .photo-upload .pu-drop.pu-dragover { border-color: var(--oasis-green-light, #2d6a4f); background: #f1f7f3; }
        .photo-upload:focus-within .pu-drop { outline: 3px solid rgba(45, 106, 79, .35); outline-offset: 2px; }
        .photo-upload .pu-drop.pu-invalid { border-color: var(--bs-danger); }
        .photo-upload .pu-drop.pu-compact { flex-direction: row; justify-content: center; gap: .5rem; padding: .6rem 1rem; margin-top: .5rem; }
        .photo-upload .pu-drop.pu-compact .pu-icon { width: 2rem; height: 2rem; font-size: 1rem; margin: 0; }
        .photo-upload .pu-drop.pu-compact .small { display: none !important; }
        .photo-upload .pu-icon {
            display: inline-flex; align-items: center; justify-content: center; width: 3rem; height: 3rem; margin-bottom: .35rem;
            border-radius: 50%; background: var(--oasis-green, #1b4332); color: #fff; font-size: 1.35rem;
        }
        .photo-upload .pu-list { display: grid; gap: .6rem; }
        .photo-upload .pu-list.pu-grid { grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); }
        .photo-upload .pu-item { display: flex; gap: 1rem; align-items: center; padding: .75rem; border: 1px solid #dee2e6; border-radius: .75rem; background: #fff; min-width: 0; }
        .photo-upload .pu-thumb { width: 96px; height: 96px; border-radius: .5rem; flex-shrink: 0; object-fit: cover; display: flex; align-items: center; justify-content: center; background: #f1f3f2; font-size: 2.25rem; color: #c0392b; }
        .photo-upload .pu-meta { min-width: 0; }
        .photo-upload .pu-grid .pu-item { position: relative; flex-direction: column; padding: 0; overflow: hidden; gap: 0; }
        .photo-upload .pu-grid .pu-thumb { width: 100%; height: 100px; border-radius: 0; }
        .photo-upload .pu-grid .pu-meta { width: 100%; padding: .35rem .5rem; }
        .photo-upload .pu-grid .pu-remove-x {
            position: absolute; top: .3rem; right: .3rem; width: 1.75rem; height: 1.75rem; padding: 0; border-radius: 50%;
            display: flex; align-items: center; justify-content: center; background: rgba(0, 0, 0, .6); color: #fff; border: 0;
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const ICONS = { 'application/pdf': 'bi-file-earmark-pdf' };
            const sizeLabel = b => b >= 1048576 ? (b / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(b / 1024)) + ' KB';
            const esc = s => s.replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

            document.querySelectorAll('[data-photo-upload]').forEach(function (root) {
                const input = root.querySelector('[data-pu-input]');
                const drop = root.querySelector('[data-pu-drop]');
                const list = root.querySelector('[data-pu-list]');
                const error = root.querySelector('[data-pu-error]');
                const accept = root.dataset.accept.split(',');
                const multiple = root.dataset.multiple === '1';
                const maxFiles = parseInt(root.dataset.maxFiles, 10);
                const maxBytes = parseInt(root.dataset.maxBytes, 10);
                let files = [];
                let urls = [];

                function render(message) {
                    // Keep the real <input> in sync so the form submits exactly what's shown.
                    const transfer = new DataTransfer();
                    files.forEach(f => transfer.items.add(f));
                    input.files = transfer.files;

                    urls.forEach(URL.revokeObjectURL);
                    urls = [];
                    list.innerHTML = '';
                    list.classList.toggle('pu-grid', multiple);
                    list.classList.toggle('d-none', files.length === 0);

                    files.forEach(function (file, index) {
                        const item = document.createElement('div');
                        item.className = 'pu-item';
                        let thumb;
                        if (file.type.startsWith('image/')) {
                            const url = URL.createObjectURL(file);
                            urls.push(url);
                            thumb = '<img class="pu-thumb" src="' + url + '" alt="Preview of ' + esc(file.name) + '">';
                        } else {
                            thumb = '<span class="pu-thumb"><i class="bi ' + (ICONS[file.type] || 'bi-file-earmark') + '"></i></span>';
                        }
                        item.innerHTML = multiple
                            ? thumb + '<div class="pu-meta small text-truncate">' + esc(file.name) + '</div>'
                              + '<button type="button" class="pu-remove-x" aria-label="Remove ' + esc(file.name) + '"><i class="bi bi-x-lg"></i></button>'
                            : thumb + '<div class="pu-meta"><div class="small fw-semibold text-truncate">' + esc(file.name) + '</div>'
                              + '<div class="small text-muted">' + sizeLabel(file.size) + '</div>'
                              + '<div class="d-flex gap-2 mt-2"><label for="' + input.id + '" class="btn btn-sm btn-outline-secondary mb-0"><i class="bi bi-arrow-repeat me-1"></i>Change</label>'
                              + '<button type="button" class="btn btn-sm btn-outline-danger pu-remove"><i class="bi bi-trash me-1"></i>Remove</button></div></div>';
                        item.querySelector('.pu-remove, .pu-remove-x').addEventListener('click', function () {
                            files.splice(index, 1);
                            render();
                        });
                        list.appendChild(item);
                    });

                    // Single: hide the drop zone once a file is chosen. Multiple: shrink it
                    // to an "add more" button until the limit is reached.
                    const full = files.length >= maxFiles;
                    drop.classList.toggle('d-none', multiple ? full : files.length > 0);
                    drop.classList.toggle('pu-compact', multiple && files.length > 0);
                    drop.classList.toggle('pu-invalid', !!message);
                    error.textContent = message || (multiple && files.length ? files.length + ' of ' + maxFiles + ' selected' : '');
                    error.classList.toggle('text-danger', !!message);
                    error.classList.toggle('text-muted', !message);
                }

                function add(picked) {
                    if (!multiple) picked = picked.slice(0, 1);

                    let message = '';
                    const next = multiple ? files.slice() : [];
                    for (const file of picked) {
                        if (!accept.includes(file.type)) { message = 'Only ' + root.dataset.types + ' files are allowed.'; continue; }
                        if (file.size > maxBytes) { message = '"' + file.name + '" is larger than ' + root.dataset.maxMb + ' MB.'; continue; }
                        if (next.length >= maxFiles) { message = 'You can add up to ' + maxFiles + ' files.'; break; }
                        next.push(file);
                    }

                    // Multiple keeps the valid files and reports the rejected ones;
                    // a rejected single file clears the field.
                    files = next;
                    render(message);
                }

                input.addEventListener('change', function () {
                    // The browser replaced input.files with just the new pick; merge it into our list.
                    if (input.files.length) add(Array.from(input.files)); else render();
                });

                ['dragenter', 'dragover'].forEach(t => drop.addEventListener(t, function (e) { e.preventDefault(); drop.classList.add('pu-dragover'); }));
                ['dragleave', 'drop'].forEach(t => drop.addEventListener(t, function () { drop.classList.remove('pu-dragover'); }));
                drop.addEventListener('drop', function (e) {
                    e.preventDefault();
                    if (e.dataTransfer.files.length) add(Array.from(e.dataTransfer.files));
                });
            });
        });
    </script>
@endonce
