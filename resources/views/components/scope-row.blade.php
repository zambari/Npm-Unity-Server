@props([
    'scope',
])

@php
    $collapseId = 'collapse-scope-' . $scope->id;
@endphp

<div class="list-group-item">
    <div class="d-flex justify-content-between align-items-start">
        <div class="flex-grow-1">
            @if($scope->display_name)
                <div class="fw-bold mb-1">{{ $scope->display_name }}</div>
            @else
                <div class="fw-bold mb-1 text-muted">—</div>
            @endif
            <div class="small text-muted mb-1">
                <code>{{ $scope->scope }}</code>
            </div>
            <div class="small text-muted">
                ID: {{ $scope->id }}
            </div>
            <button class="btn btn-link p-0 text-start text-decoration-none mt-1 d-flex align-items-center justify-content-between w-100" 
                    type="button" 
                    data-bs-toggle="collapse" 
                    data-bs-target="#{{ $collapseId }}" 
                    aria-expanded="false" 
                    aria-controls="{{ $collapseId }}">
                <span>{{ $scope->packages_count }} packages</span>
                <span class="chevron-icon ms-2">
                    <x-icon-chevron-down />
                </span>
            </button>
        </div>
        <div class="ms-3">
            <a href="{{ route('admin.scopes.edit', $scope->id) }}" class="btn btn-sm btn-primary">Edit</a>
            <form method="POST" action="{{ route('admin.scopes.destroy', $scope->id) }}" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this scope? This action cannot be undone.');">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-sm btn-danger" {{ $scope->packages_count > 0 ? 'disabled title="Cannot delete scope with associated packages"' : '' }}>
                    Delete
                </button>
            </form>
        </div>
    </div>
    <div class="collapse mt-2" id="{{ $collapseId }}">
        <div class="ps-3">
            <x-packages-list :scopeId="$scope->id" :allowEditing="true" />
        </div>
    </div>
</div>

<style>
    .chevron-icon {
        transition: transform 0.3s ease;
    }
    button[aria-expanded="true"] .chevron-icon {
        transform: rotate(180deg);
    }
</style>
