/**
 * Version Validator and Semantic Versioning Helper
 * 
 * Validates version numbers and provides semantic versioning bump functionality.
 */

(function() {
    'use strict';

    /**
     * Initialize version validator
     * @param {string} currentHighestVersion - The current highest version (e.g., "1.2.3")
     * @param {string} versionInputId - The ID of the version input field
     */
    function initVersionValidator(currentHighestVersion, versionInputId = 'version') {
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
            return version.major + '.' + version.minor + '.' + version.patch;
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
                return { valid: false, message: 'Version is required', isFormatError: true };
            }

            const parsed = parseVersion(version);
            if (!parsed) {
                return { valid: false, message: 'Invalid version format. Use semantic versioning (e.g., 1.2.3)', isFormatError: true };
            }

            if (!currentHighestVersion) {
                return { valid: true, message: '', isFormatError: false };
            }

            if (!isVersionHigher(version, currentHighestVersion)) {
                return { 
                    valid: false, 
                    message: 'Version must be higher than current highest version (' + currentHighestVersion + '). Use bump buttons to increment.',
                    isFormatError: false
                };
            }

            return { valid: true, message: '', isFormatError: false };
        }

        // Bump version functions
        function bumpMajor() {
            const current = parseVersion(currentHighestVersion || '0.0.0') || { major: 0, minor: 0, patch: 0 };
            const newVersion = {
                major: current.major + 1,
                minor: 0,
                patch: 0
            };
            versionInput.value = formatVersion(newVersion);
            validateAndUpdate();
        }

        function bumpMinor() {
            const current = parseVersion(currentHighestVersion || '0.0.0') || { major: 0, minor: 0, patch: 0 };
            const newVersion = {
                major: current.major,
                minor: current.minor + 1,
                patch: 0
            };
            versionInput.value = formatVersion(newVersion);
            validateAndUpdate();
        }

        function bumpPatch() {
            const current = parseVersion(currentHighestVersion || '0.0.0') || { major: 0, minor: 0, patch: 0 };
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
            
            // Reset all styling
            versionInput.classList.remove('is-valid', 'is-invalid');
            versionInput.style.color = '';
            feedbackElement.textContent = '';
            feedbackElement.className = 'version-feedback mt-1';
            
            if (version === '') {
                return;
            }
            
            if (validation.valid) {
                versionInput.classList.add('is-valid');
                versionInput.style.color = '';
                feedbackElement.className = 'version-feedback mt-1 text-success';
                feedbackElement.textContent = '✓ Valid version';
            } else {
                if (validation.isFormatError) {
                    // Format error: red border and red text
                    versionInput.classList.add('is-invalid');
                    versionInput.style.color = '#dc3545';
                } else {
                    // Comparison error: just red text, no red border
                    versionInput.style.color = '#dc3545';
                }
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
        buttonsContainer.innerHTML = 
         
             '<button type="button" class="btn btn-sm btn-outline-secondary version-bump-major" title="Bump major version (X.0.0) - Use when breaking changes make it incompatible with previous versions">' +
                '<strong class="text-muted">Major update +1.0.0</strong> <small class="d-block" style="font-size: 0.7em; opacity: 0.8;">Anyhing that breaks compatibility</small>' +
            '</button>' +
            '<button type="button" class="btn btn-sm btn-outline-secondary  version-bump-minor" title="Bump minor version (X.Y.0) - Use when adding new features that are backward compatible">' +
                '<strong>Minor update +0.1.0</strong> <small class="d-block" style="font-size: 0.7em; opacity: 0.8;">Backward compatible features.</small>' +
            '</button>' +
            '<button type="button" class="btn btn-sm btn-outline-secondary  version-bump-patch" title="Bump patch version (X.Y.Z) - Use for bug fixes and minor updates">' +
                '<strong>Patch update +0.0.1</strong> <small class="d-block" style="font-size: 0.7em; opacity: 0.8;">Small changes, bugfixes.</small>' +
            '</button> ';
        
        // Insert buttons after the input field
        versionInput.parentElement.appendChild(buttonsContainer);
        
        // Attach event listeners to bump buttons
        buttonsContainer.querySelector('.version-bump-major').addEventListener('click', bumpMajor);
        buttonsContainer.querySelector('.version-bump-minor').addEventListener('click', bumpMinor);
        buttonsContainer.querySelector('.version-bump-patch').addEventListener('click', bumpPatch);

        // Initial validation
        validateAndUpdate();
    }

    // Make it available globally
    window.initVersionValidator = initVersionValidator;

    // Auto-initialize if DOM is ready
    function autoInit() {
        const versionInput = document.getElementById('version');
        if (versionInput) {
            const currentHighestVersion = versionInput.getAttribute('data-current-highest-version');
            if (currentHighestVersion !== null) {
                initVersionValidator(currentHighestVersion || null, 'version');
            }
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', autoInit);
    } else {
        autoInit();
    }
})();
