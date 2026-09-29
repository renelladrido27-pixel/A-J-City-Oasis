@props([
    'name',
    'label' => 'Photo',
    'hint' => null,
    'optional' => true,
])

{{--
    Image-only upload with a tap/drag drop zone and live preview. Accepts
    JPG, PNG or WebP up to 5 MB — checked here for instant feedback and again
    by the server's validation rules. On phones the browser offers camera or
    gallery. Usage: <x-photo-upload name="photo" label="Receipt photo" />
--}}
@php($id = 'photo-'.str_replace(['[', ']'], '-', $name).'-'.uniqid())

<div class="photo-upload" data-photo-upload>
    <span class="form-label d-block">
        {{ $label }} @if ($optional)<span class="text-muted fw-normal">(optional)</span>@endif
    </span>

    <label for="{{ $id }}" class="pu-drop @error($name) pu-invalid @enderror" data-pu-drop>
        <input type="file" id="{{ $id }}" name="{{ $name }}" class="pu-input" accept="image/jpeg,image/png,image/webp" data-pu-input>
        <span class="pu-icon"><i class="bi bi-camera"></i></span>
        <span class="fw-semibold">Take a photo or choose one</span>
        <span class="small text-muted d-none d-md-inline">or drag and drop it here</span>
        <span class="small text-muted">JPG, PNG or WebP &middot; up to 5 MB</span>
    </label>

    <div class="pu-preview d-none" data-pu-preview>
        <img alt="Selected photo preview" data-pu-img>
        <div class="pu-meta">
            <div class="small fw-semibold text-truncate" data-pu-name></div>
            <div class="small text-muted" data-pu-size></div>
            <div class="d-flex gap-2 mt-2">
                <label for="{{ $id }}" class="btn btn-sm btn-outline-secondary mb-0"><i class="bi bi-arrow-repeat me-1"></i>Change</label>
                <button type="button" class="btn btn-sm btn-outline-danger" data-pu-remove><i class="bi bi-trash me-1"></i>Remove</button>
            </div>
        </div>
    </div>

    <div class="small text-danger mt-1" data-pu-error>@error($name){{ $message }}@enderror</div>
    @if ($errors->any() && ! $errors->has($name))
        <div class="small text-muted mt-1"><i class="bi bi-info-circle me-1"></i>Please attach the photo again — files aren't kept when the form has an error.</div>
    @elseif ($hint)
        <div class="form-text">{{ $hint }}</div>
    @endif
</div>

@once
    <style>
        .photo-upload .pu-input { position: absolute; width: 1px; height: 1px; opacity: 0; overflow: hidden; }
        .photo-upload .pu-drop {
            position: relative; display: flex; flex-direction: column; align-items: center; gap: .15rem;
            padding: 1.5rem 1rem; text-align: center; cursor: pointer; margin: 0;
            border: 2px dashed #c9d3cd; border-radius: .75rem; background: #fafbfa;
            transition: border-color .15s, background-color .15s;
        }
        .photo-upload .pu-drop:hover, .photo-upload .pu-drop.pu-dragover { border-color: var(--oasis-green-light, #2d6a4f); background: #f1f7f3; }
        .photo-upload .pu-drop:focus-within { outline: 3px solid rgba(45, 106, 79, .35); outline-offset: 2px; }
        .photo-upload .pu-drop.pu-invalid { border-color: var(--bs-danger); }
        .photo-upload .pu-icon {
            display: inline-flex; align-items: center; justify-content: center; width: 3rem; height: 3rem; margin-bottom: .35rem;
            border-radius: 50%; background: var(--oasis-green, #1b4332); color: #fff; font-size: 1.35rem;
        }
        .photo-upload .pu-preview { display: flex; gap: 1rem; align-items: center; padding: .75rem; border: 1px solid #dee2e6; border-radius: .75rem; background: #fff; }
        .photo-upload .pu-preview.d-none { display: none !important; }
        .photo-upload .pu-preview img { width: 96px; height: 96px; object-fit: cover; border-radius: .5rem; flex-shrink: 0; }
        .photo-upload .pu-meta { min-width: 0; }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const ALLOWED = ['image/jpeg', 'image/png', 'image/webp'];
            const MAX_BYTES = 5 * 1024 * 1024;

            document.querySelectorAll('[data-photo-upload]').forEach(function (root) {
                const input = root.querySelector('[data-pu-input]');
                const drop = root.querySelector('[data-pu-drop]');
                const preview = root.querySelector('[data-pu-preview]');
                const img = root.querySelector('[data-pu-img]');
                const error = root.querySelector('[data-pu-error]');
                let objectUrl = null;

                function reset(message) {
                    input.value = '';
                    if (objectUrl) URL.revokeObjectURL(objectUrl);
                    objectUrl = null;
                    preview.classList.add('d-none');
                    drop.classList.remove('d-none');
                    drop.classList.toggle('pu-invalid', !!message);
                    error.textContent = message || '';
                }

                function show(file) {
                    if (!ALLOWED.includes(file.type)) return reset('Only JPG, PNG or WebP images are allowed.');
                    if (file.size > MAX_BYTES) return reset('That photo is larger than 5 MB — please choose a smaller one.');

                    if (objectUrl) URL.revokeObjectURL(objectUrl);
                    objectUrl = URL.createObjectURL(file);
                    img.src = objectUrl;
                    root.querySelector('[data-pu-name]').textContent = file.name;
                    root.querySelector('[data-pu-size]').textContent = file.size >= 1024 * 1024
                        ? (file.size / 1024 / 1024).toFixed(1) + ' MB'
                        : Math.max(1, Math.round(file.size / 1024)) + ' KB';
                    error.textContent = '';
                    drop.classList.remove('pu-invalid');
                    drop.classList.add('d-none');
                    preview.classList.remove('d-none');
                }

                input.addEventListener('change', function () {
                    input.files.length ? show(input.files[0]) : reset();
                });

                root.querySelector('[data-pu-remove]').addEventListener('click', function () { reset(); });

                ['dragenter', 'dragover'].forEach(function (type) {
                    drop.addEventListener(type, function (e) { e.preventDefault(); drop.classList.add('pu-dragover'); });
                });
                ['dragleave', 'drop'].forEach(function (type) {
                    drop.addEventListener(type, function () { drop.classList.remove('pu-dragover'); });
                });
                drop.addEventListener('drop', function (e) {
                    e.preventDefault();
                    if (!e.dataTransfer.files.length) return;
                    const transfer = new DataTransfer();
                    transfer.items.add(e.dataTransfer.files[0]);
                    input.files = transfer.files;
                    show(input.files[0]);
                });
            });
        });
    </script>
@endonce
