@extends('layouts.admin')

@section('title', 'Announcements')

@section('content')
<x-page-header title="Announcements" subtitle="Post a broadcast, or target a property, floor, or specific tenant" />

<div class="card border-0 shadow-sm mb-4" style="max-width: 720px;">
    <div class="card-body p-4">
        <form method="POST" action="{{ route('admin.announcements.store') }}">
            @csrf
            <div class="mb-3">
                <label class="form-label">Title</label>
                <input type="text" name="title" class="form-control @error('title') is-invalid @enderror" value="{{ old('title') }}" required>
                @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label class="form-label">Message</label>
                <textarea name="body" class="form-control @error('body') is-invalid @enderror" rows="4" required>{{ old('body') }}</textarea>
                @error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label class="form-label">Audience</label>
                <select name="audience" id="audienceSelect" class="form-select @error('audience') is-invalid @enderror" required>
                    <option value="all" @selected(old('audience', 'all') === 'all')>All tenants (broadcast)</option>
                    <option value="property" @selected(old('audience') === 'property')>Everyone in a property</option>
                    <option value="floor" @selected(old('audience') === 'floor')>Everyone on a floor</option>
                    <option value="tenant" @selected(old('audience') === 'tenant')>A specific tenant</option>
                </select>
                @error('audience')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3 audience-field" data-audience="property floor">
                <label class="form-label">Property</label>
                <select name="property_id" class="form-select @error('property_id') is-invalid @enderror">
                    <option value="">Select a property</option>
                    @foreach ($properties as $property)
                        <option value="{{ $property->id }}" @selected((string) old('property_id') === (string) $property->id)>{{ $property->name }}</option>
                    @endforeach
                </select>
                @error('property_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3 audience-field" data-audience="floor">
                <label class="form-label">Floor</label>
                <input type="number" name="floor" class="form-control @error('floor') is-invalid @enderror" min="1" value="{{ old('floor') }}">
                @error('floor')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3 audience-field" data-audience="tenant">
                <label class="form-label">Tenant</label>
                <select name="tenant_id" class="form-select @error('tenant_id') is-invalid @enderror">
                    <option value="">Select a tenant</option>
                    @foreach ($tenants as $tenant)
                        <option value="{{ $tenant->id }}" @selected((string) old('tenant_id') === (string) $tenant->id)>{{ $tenant->name }} ({{ $tenant->email }})</option>
                    @endforeach
                </select>
                @error('tenant_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <button class="btn btn-primary"><i class="bi bi-megaphone me-1"></i>Post Announcement</button>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th>Title</th><th>Audience</th><th>Posted By</th><th>Posted</th><th class="text-end">Actions</th></tr>
            </thead>
            <tbody>
                @forelse ($announcements as $announcement)
                    <tr>
                        <td class="fw-medium">{{ $announcement->title }}</td>
                        <td>{{ $announcement->targetDescription() }}</td>
                        <td>{{ $announcement->author->name }}</td>
                        <td>{{ $announcement->created_at->diffForHumans() }}</td>
                        <td class="text-end">
                            <form method="POST" action="{{ route('admin.announcements.destroy', $announcement) }}" class="d-inline" onsubmit="return confirm('Delete this announcement?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5"><x-empty-state icon="bi-megaphone" message="No announcements posted yet." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $announcements->links() }}</div>

<script>
    const audienceSelect = document.getElementById('audienceSelect');

    function syncAudienceFields() {
        const selected = audienceSelect.value;
        document.querySelectorAll('.audience-field').forEach(function (field) {
            field.style.display = field.dataset.audience.split(' ').includes(selected) ? '' : 'none';
        });
    }

    audienceSelect.addEventListener('change', syncAudienceFields);
    syncAudienceFields();
</script>
@endsection
