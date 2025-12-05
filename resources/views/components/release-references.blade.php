@props([
    'package',
    'isFirstRelease' => false,
    'isOldestRelease' => false,
    'ancestorRelease' => null,
    'hasAncestorReferences' => false,
    'release' => null, // Optional: existing release for edit mode
    'ancestorReferencesUrl' => null, // Optional: URL for importing ancestor references
])

<!-- References Section (Collapsed) -->
<x-collapsible-card name="References (Dependencies)" id="referencesCollapse" :expanded="$isFirstRelease" cardClass="mb-3 border-secondary">
    <div class="card-body">
            @if(!$isFirstRelease && !$isOldestRelease)
            <div class="mb-3">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="inherit-references" checked>
                    <input type="hidden" name="inherit_references" id="inherit-references-hidden" value="1">
                    <label class="form-check-label" for="inherit-references">
                        Inherit references from previous release
                    </label>
                </div>
                @if($hasAncestorReferences && $ancestorRelease)
                    <small class="text-muted d-block mt-1">
                        References from version <code>{{ $ancestorRelease->version }}</code> will be inherited
                    </small>
                @endif
            </div>
            @endif
            <div id="references-container" class="{{ (!$isFirstRelease && !$isOldestRelease) ? 'd-none' : '' }}">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Bundle ID</th>
                            <th>Version</th>
                            <th style="width: 50px;"></th>
                        </tr>
                    </thead>
                    <tbody id="references-tbody">
                        @if($release && $release->dependencies && $release->dependencies->isNotEmpty())
                            @foreach($release->dependencies as $index => $dependency)
                                <tr data-index="{{ $index }}">
                                    <td>
                                        <input type="text" class="form-control form-control-sm" 
                                               name="references[{{ $index }}][bundle_id]" 
                                               value="{{ old("references.$index.bundle_id", $dependency->bundle_id ?? '') }}" 
                                               placeholder="com.example.package" required>
                                    </td>
                                    <td>
                                        <input type="text" class="form-control form-control-sm" 
                                               name="references[{{ $index }}][version]" 
                                               value="{{ old("references.$index.version", $dependency->version ?? '') }}" 
                                               placeholder="1.0.0 (optional)">
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-danger remove-reference" title="Remove">
                                            <x-icon-x />
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        @else
                            <tr data-index="0">
                                <td>
                                    <input type="text" class="form-control form-control-sm" 
                                           name="references[0][bundle_id]" 
                                           value="{{ old('references.0.bundle_id', '') }}" 
                                           placeholder="com.example.package" required>
                                </td>
                                <td>
                                    <input type="text" class="form-control form-control-sm" 
                                           name="references[0][version]" 
                                           value="{{ old('references.0.version', '') }}" 
                                           placeholder="1.0.0 (optional)">
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-danger remove-reference" title="Remove">
                                        <x-icon-x />
                                    </button>
                                </td>
                            </tr>
                        @endif
                    </tbody>
                </table>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-primary" id="add-reference">
                        <x-icon-plus />
                        Add Reference
                    </button>
                    @if(!$isFirstRelease && !$isOldestRelease && $hasAncestorReferences && $ancestorReferencesUrl)
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="import-ancestor-references" data-ancestor-url="{{ $ancestorReferencesUrl }}">
                        <x-icon-download />
                        Import References from Ancestor
                    </button>
                    @endif
                </div>
            </div>
        </div>
</x-collapsible-card>

<script>
(function() {
    // Icon helper for JavaScript-generated content
    const iconX = '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" class="bi bi-x" viewBox="0 0 16 16"><path d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708z"/></svg>';

    document.addEventListener('DOMContentLoaded', function() {
        const tbody = document.getElementById('references-tbody');
        if (!tbody) return;
        
        // Calculate starting index based on existing rows
        const existingRows = tbody.querySelectorAll('tr');
        let referenceIndex = existingRows.length > 0 ? existingRows.length : 0;
        
        // Add new reference row
        const addButton = document.getElementById('add-reference');
        if (addButton) {
            addButton.addEventListener('click', function() {
                const row = document.createElement('tr');
                row.setAttribute('data-index', referenceIndex);
                row.innerHTML = `
                    <td>
                        <input type="text" class="form-control form-control-sm" 
                               name="references[${referenceIndex}][bundle_id]" 
                               value="" 
                               placeholder="com.example.package" required>
                    </td>
                    <td>
                        <input type="text" class="form-control form-control-sm" 
                               name="references[${referenceIndex}][version]" 
                               value="" 
                               placeholder="1.0.0 (optional)">
                    </td>
                    <td>
                        <button type="button" class="btn btn-sm btn-danger remove-reference" title="Remove">
                            ${iconX}
                        </button>
                    </td>
                `;
                tbody.appendChild(row);
                referenceIndex++;
                
                // Attach remove handler to new row
                row.querySelector('.remove-reference').addEventListener('click', function() {
                    row.remove();
                });
            });
        }
        
        // Remove reference row
        document.querySelectorAll('.remove-reference').forEach(function(btn) {
            btn.addEventListener('click', function() {
                this.closest('tr').remove();
            });
        });
        
        // Inherit references toggle
        const inheritToggle = document.getElementById('inherit-references');
        const referencesContainer = document.getElementById('references-container');
        const importButton = document.getElementById('import-ancestor-references');
        
        if (inheritToggle && referencesContainer) {
            const inheritHidden = document.getElementById('inherit-references-hidden');
            
            inheritToggle.addEventListener('change', function() {
                if (this.checked) {
                    // Hide editor when inheriting
                    referencesContainer.classList.add('d-none');
                    if (inheritHidden) inheritHidden.value = '1';
                    // Clear all reference inputs so they're not submitted
                    const inputs = referencesContainer.querySelectorAll('input[name^="references"]');
                    inputs.forEach(input => {
                        input.disabled = true;
                        input.name = input.name.replace('references', 'references_disabled');
                    });
                } else {
                    // Show editor when not inheriting
                    referencesContainer.classList.remove('d-none');
                    if (inheritHidden) inheritHidden.value = '0';
                    // Re-enable inputs
                    const inputs = referencesContainer.querySelectorAll('input[name^="references_disabled"]');
                    inputs.forEach(input => {
                        input.disabled = false;
                        input.name = input.name.replace('references_disabled', 'references');
                    });
                }
            });
            
            // Initialize on page load
            if (inheritToggle.checked) {
                const inputs = referencesContainer.querySelectorAll('input[name^="references"]');
                inputs.forEach(input => {
                    input.disabled = true;
                    input.name = input.name.replace('references', 'references_disabled');
                });
            }
        }
        
        // Import ancestor references
        if (importButton) {
            const ancestorUrl = importButton.getAttribute('data-ancestor-url');
            if (ancestorUrl) {
                importButton.addEventListener('click', function() {
                    fetch(ancestorUrl)
                    .then(response => response.json())
                    .then(data => {
                        if (data.references && data.references.length > 0) {
                            // Clear existing rows
                            tbody.innerHTML = '';
                            referenceIndex = 0;
                            
                            // Add rows for each reference
                            data.references.forEach(function(ref) {
                                const row = document.createElement('tr');
                                row.setAttribute('data-index', referenceIndex);
                                row.innerHTML = `
                                    <td>
                                        <input type="text" class="form-control form-control-sm" 
                                               name="references[${referenceIndex}][bundle_id]" 
                                               value="${ref.bundle_id || ''}" 
                                               placeholder="com.example.package" required>
                                    </td>
                                    <td>
                                        <input type="text" class="form-control form-control-sm" 
                                               name="references[${referenceIndex}][version]" 
                                               value="${ref.version || ''}" 
                                               placeholder="1.0.0 (optional)">
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-danger remove-reference" title="Remove">
                                            ${iconX}
                                        </button>
                                    </td>
                                `;
                                tbody.appendChild(row);
                                referenceIndex++;
                                
                                // Attach remove handler
                                row.querySelector('.remove-reference').addEventListener('click', function() {
                                    row.remove();
                                });
                            });
                            
                            // Show success message
                            alert(`Imported ${data.references.length} reference(s) from version ${data.ancestor_version}`);
                        } else {
                            alert('No references found in ancestor releases.');
                        }
                    })
                    .catch(error => {
                        console.error('Error importing references:', error);
                        alert('Error importing references. Please try again.');
                    });
                });
            }
        }
    });
})();
</script>
