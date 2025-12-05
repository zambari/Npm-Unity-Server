@props([
    'name',
    'id' => null,
    'expanded' => false,
    'cardClass' => 'mb-2 border-primary',
])

@php
    $collapseId = $id ?? 'collapse' . uniqid();
    $isExpanded = $expanded ? 'true' : 'false';
    $collapseClass = $expanded ? 'collapse show' : 'collapse';
@endphp

<div class="card {{ $cardClass }}">
    <div class="p-1 bg-primary text-white">
        <button class="btn text-decoration-none p-0 w-100 text-start text-white" type="button" 
                data-bs-toggle="collapse" data-bs-target="#{{ $collapseId }}" 
                aria-expanded="{{ $isExpanded }}" aria-controls="{{ $collapseId }}">
            <h5 class="mb-0 d-flex justify-content-between align-items-center">
                <span>{{ $name }}</span>
                <x-icon-chevron-down />
            </h5>
        </button>
    </div>
    <div class="{{ $collapseClass }}" id="{{ $collapseId }}">
        {{ $slot }}
    </div>
</div>
