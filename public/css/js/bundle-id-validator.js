/**
 * Bundle ID Validation and Auto-fill
 * Validates bundle_id against selected scope and auto-fills when scope changes
 */
function initBundleIdValidator(options) {
    const {
        scopeSelectId = 'scope_id',
        bundleIdInputId = 'bundle_id',
        validationDivId = 'bundle_id_validation',
        enableAutoFill = true
    } = options || {};

    const scopeSelect = document.getElementById(scopeSelectId);
    const bundleIdInput = document.getElementById(bundleIdInputId);
    const validationDiv = document.getElementById(validationDivId);

    if (!scopeSelect || !bundleIdInput || !validationDiv) {
        console.warn('Bundle ID validator: Required elements not found');
        return;
    }

    // Collect all scope values from options
    const allScopeValues = [];
    Array.from(scopeSelect.options).forEach(option => {
        const scopeValue = option.getAttribute('data-scope');
        if (scopeValue) {
            allScopeValues.push(scopeValue);
        }
    });

    // Check if bundle_id matches any scope value (user hasn't altered it)
    // Remove trailing dots before comparison since we add '.' at the end
    function matchesAnyScope(bundleId) {
        if (!bundleId) return true;
        const trimmed = bundleId.trim().replace(/\.+$/, ''); // Remove trailing dots
        return allScopeValues.includes(trimmed);
    }

    // Validate that bundle_id starts with selected scope and has content after the dot
    function validateBundleId() {
        const selectedOption = scopeSelect.options[scopeSelect.selectedIndex];
        const selectedScope = selectedOption ? selectedOption.getAttribute('data-scope') : null;
        const bundleId = bundleIdInput.value.trim();

        // Check for empty bundle_id
        if (!bundleId) {
            validationDiv.textContent = 'Bundle ID is required';
            validationDiv.style.display = 'block';
            return;
        }

        // Hide validation if no scope selected (but bundle_id is not empty)
        if (!selectedScope) {
            validationDiv.style.display = 'none';
            return;
        }

        // Check if bundle_id starts with the selected scope
        if (!bundleId.startsWith(selectedScope)) {
            validationDiv.textContent = 'Bundle ID must start with the selected scope: ' + selectedScope;
            validationDiv.style.display = 'block';
            return;
        }

        // Check if bundle_id ends with just a dot (no content after the dot)
        if (bundleId.endsWith('.') && bundleId === selectedScope + '.') {
            validationDiv.textContent = 'Bundle ID must include content after the scope (e.g., ' + selectedScope + '.mypackage)';
            validationDiv.style.display = 'block';
            return;
        }

        // All validations passed
        validationDiv.style.display = 'none';
    }

    // Handle scope change
    if (enableAutoFill) {
        scopeSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const scopeValue = selectedOption.getAttribute('data-scope');
            const bundleId = bundleIdInput.value.trim();

            // Auto-fill if bundle_id is empty or matches any scope value (user hasn't altered it)
            // Remove trailing dots before checking to account for the '.' we add
            if (scopeValue && (!bundleId || matchesAnyScope(bundleId))) {
                bundleIdInput.value = scopeValue + '.';
            }

            // Validate after auto-fill
            validateBundleId();
        });
    } else {
        // Just validate on scope change, don't auto-fill
        scopeSelect.addEventListener('change', validateBundleId);
    }

    // Validate on bundle_id input change - multiple events to catch all changes
    bundleIdInput.addEventListener('input', validateBundleId);
    bundleIdInput.addEventListener('keyup', validateBundleId);
    bundleIdInput.addEventListener('paste', function() {
        // Use setTimeout to allow paste to complete before validation
        setTimeout(validateBundleId, 0);
    });
    bundleIdInput.addEventListener('blur', validateBundleId);
    bundleIdInput.addEventListener('change', validateBundleId);

    // Initial validation if there's a pre-selected scope
    validateBundleId();
}
