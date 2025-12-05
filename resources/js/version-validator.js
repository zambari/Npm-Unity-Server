/**
 * Version Validator and Semantic Versioning Helper
 * 
 * Validates version numbers and provides semantic versioning bump functionality.
 * 
 * @param {string} currentHighestVersion - The current highest version (e.g., "1.2.3")
 * @param {string} versionInputId - The ID of the version input field
 */
export function initVersionValidator(currentHighestVersion, versionInputId = 'version') {
    const versionInput = document.getElementById(versionInputId);
    if (!versionInput) {
        console.error('Version input field not found');
        return;
    }

    // Parse semantic version (major.minor.patch)
    function parseVersion(version) {
        if (!version || typeof version !== 'string') {
            return null;
        }
        const parts = version.trim().split('.');
        if (parts.length !== 3) {
            return null;
        }
        const major = parseInt(parts[0], 10);
        const minor = parseInt(parts[1], 10);
        const patch = parseInt(parts[2], 10);
        
        if (isNaN(major) || isNaN(minor) || isNaN(patch)) {
            return null;
        }
        
        return { major, minor, patch, original: version };
    }

    // Format version as string
    function formatVersion(version) {
        if (!version) return '';
        return `${version.major}.${version.minor}.${version.patch}`;
    }

    // Compare two versions
    function compareVersions(v1, v2) {
        if (v1.major !== v2.major) {
            return v1.major - v2.major;
        }
        if (v1.minor !== v2.minor) {
            return v1.minor - v2.minor;
        }
        return v1.patch - v2.patch;
    }

    // Check if version is higher than current highest
    function isVersionHigher(newVersion, currentHighest) {
        const newVer = parseVersion(newVersion);
        const currentVer = parseVersion(currentHighest);
        
        if (!newVer || !currentVer) {
            return false;
        }
        
        return compareVersions(newVer, currentVer) > 0;
    }

    // Validate version format and value
    function validateVersion(version) {
        if (!version || version.trim() === '') {
            return { valid: false, message: 'Version is required' };
        }

        const parsed = parseVersion(version);
        if (!parsed) {
            return { valid: false, message: 'Invalid version format. Use semantic versioning (e.g., 1.2.3)' };
        }

        if (!currentHighestVersion) {
            return { valid: true, message: '' };
        }

        if (!isVersionHigher(version, currentHighestVersion)) {
            return { 
                valid: false, 
                message: `Version must be higher than current highest version (${currentHighestVersion}). Use bump buttons to increment.` 
            };
        }

        return { valid: true, message: '' };
    }

    // Bump version functions
    function bumpMajor() {
        const current = parseVersion(currentHighestVersion || '0.0.0');
        if (!current) {
            current = { major: 0, minor: 0, patch: 0 };
        }
        const newVersion = {
            major: current.major + 1,
            minor: 0,
            patch: 0
        };
        versionInput.value = formatVersion(newVersion);
        validateAndUpdate();
    }

    function bumpMinor() {
        const current = parseVersion(currentHighestVersion || '0.0.0');
        if (!current) {
            current = { major: 0, minor: 0, patch: 0 };
        }
        const newVersion = {
            major: current.major,
            minor: current.minor + 1,
            patch: 0
        };
        versionInput.value = formatVersion(newVersion);
        validateAndUpdate();
    }

    function bumpPatch() {
        const current = parseVersion(currentHighestVersion || '0.0.0');
        if (!current) {
            current = { major: 0, minor: 0, patch: 0 };
        }
        const newVersion = {
            major: current.major,
            minor: current.minor,
            patch: current.patch + 1
        };
        versionInput.value = formatVersion(newVersion);
        validateAndUpdate();
    }

    // Validation feedback element
    let feedbackElement = versionInput.parentElement.querySelector('.version-feedback');
    if (!feedbackElement) {
        feedbackElement = document.createElement('div');
        feedbackElement.className = 'version-feedback mt-1';
        versionInput.parentElement.appendChild(feedbackElement);
    }

    function validateAndUpdate() {
        const version = versionInput.value.trim();
        const validation = validateVersion(version);
        
        // Update input styling
        versionInput.classList.remove('is-valid', 'is-invalid');
        feedbackElement.textContent = '';
        feedbackElement.className = 'version-feedback mt-1';
        
        if (version === '') {
            return;
        }
        
        if (validation.valid) {
            versionInput.classList.add('is-valid');
            feedbackElement.className = 'version-feedback mt-1 text-success';
            feedbackElement.textContent = '✓ Valid version';
        } else {
            versionInput.classList.add('is-invalid');
            feedbackElement.className = 'version-feedback mt-1 text-danger';
            feedbackElement.textContent = validation.message;
        }
    }

    // Real-time validation
    versionInput.addEventListener('input', validateAndUpdate);
    versionInput.addEventListener('blur', validateAndUpdate);

    // Form submission validation
    const form = versionInput.closest('form');
    if (form) {
        form.addEventListener('submit', function(e) {
            const version = versionInput.value.trim();
            const validation = validateVersion(version);
            
            if (!validation.valid) {
                e.preventDefault();
                versionInput.focus();
                validateAndUpdate();
                return false;
            }
        });
    }

    // Create bump buttons container
    const buttonsContainer = document.createElement('div');
    buttonsContainer.className = 'version-bump-buttons mt-2 d-flex gap-2 flex-wrap';
    buttonsContainer.innerHTML = `
        <button type="button" class="btn btn-sm btn-outline-primary version-bump-major" title="Bump major version (X.0.0) - Use when breaking changes make it incompatible with previous versions">
            <strong>Major</strong> <small class="d-block" style="font-size: 0.7em; opacity: 0.8;">Breaking</small>
        </button>
        <button type="button" class="btn btn-sm btn-outline-success version-bump-minor" title="Bump minor version (X.Y.0) - Use when adding new features that are backward compatible">
            <strong>Minor</strong> <small class="d-block" style="font-size: 0.7em; opacity: 0.8;">Feature</small>
        </button>
        <button type="button" class="btn btn-sm btn-outline-info version-bump-patch" title="Bump patch version (X.Y.Z) - Use for bug fixes and minor updates">
            <strong>Patch</strong> <small class="d-block" style="font-size: 0.7em; opacity: 0.8;">Fix</small>
        </button>
    `;
    
    // Insert buttons after the input field
    versionInput.parentElement.appendChild(buttonsContainer);
    
    // Attach event listeners to bump buttons
    buttonsContainer.querySelector('.version-bump-major').addEventListener('click', bumpMajor);
    buttonsContainer.querySelector('.version-bump-minor').addEventListener('click', bumpMinor);
    buttonsContainer.querySelector('.version-bump-patch').addEventListener('click', bumpPatch);

    // Initial validation
    validateAndUpdate();
}

// Make it available globally for inline scripts
if (typeof window !== 'undefined') {
    window.initVersionValidator = initVersionValidator;
    
    // Auto-initialize if DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', autoInit);
    } else {
        autoInit();
    }
    
    function autoInit() {
        const versionInput = document.getElementById('version');
        if (versionInput) {
            const currentHighestVersion = versionInput.getAttribute('data-current-highest-version');
            if (currentHighestVersion !== null) {
                initVersionValidator(currentHighestVersion || null, 'version');
            }
        }
    }
}
