@extends('layouts.admin')

@section('title', 'Properties')

@section('content')
<x-page-header title="Properties">
    <x-slot:actions>
        <a href="{{ route('admin.properties.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Add Property</a>
    </x-slot:actions>
</x-page-header>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th>Name</th><th>Address</th><th>Rooms</th><th class="text-end">Actions</th></tr>
            </thead>
            <tbody>
                @forelse ($properties as $property)
                    <tr>
                        <td class="fw-medium">{{ $property->name }}</td>
                        <td>{{ $property->address }}</td>
                        <td>
                            <a href="{{ route('admin.rooms.index', ['property_id' => $property->id]) }}" class="badge text-bg-light text-decoration-none">
                                {{ $property->rooms_count }} room{{ $property->rooms_count === 1 ? '' : 's' }}
                            </a>
                        </td>
                        <td class="text-end">
                            <a href="{{ route('admin.properties.edit', $property) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                            <form method="POST" action="{{ route('admin.properties.destroy', $property) }}" class="d-inline" onsubmit="return confirm('Delete this property?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4"><x-empty-state icon="bi-buildings" message="No properties yet." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
