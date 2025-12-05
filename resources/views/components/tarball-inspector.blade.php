@props([
'package',
'release',
])

<div id="tarball-inspector-container" style="display: none;">
    <div class="mt-3">
        <div id="tarball-inspector-loading" class="text-center py-3">
            <div class="spinner-border spinner-border-sm" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <span class="ms-2">Inspecting tarball...</span>
        </div>

        <div id="tarball-inspector-content" style="display: none;">


            <div class="mb-3">
                <h6>package.json</h6>
                <pre id="tarball-package-json" class="p-3 rounded border" style="font-family: 'Courier New', monospace; font-size: 0.75rem; max-height: 300px; overflow-y: auto;"></pre>
            </div>
            <div class="mb-3">
                <h6>Directory Structure</h6>
                <pre id="tarball-tree" class="p-3 rounded border" style="font-family: 'Courier New', monospace; font-size: 0.75rem; max-height: 400px; overflow-y: auto;"></pre>
            </div>

        </div>

        <div id="tarball-inspector-error" class="alert alert-danger" style="display: none;"></div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const inspectorContainer = document.getElementById('tarball-inspector-container');
        const inspectorContent = document.getElementById('tarball-inspector-content');
        const inspectorLoading = document.getElementById('tarball-inspector-loading');
        const inspectorError = document.getElementById('tarball-inspector-error');
        const treeElement = document.getElementById('tarball-tree');
        const packageJsonElement = document.getElementById('tarball-package-json');

        let hasLoaded = false;

        // Find the collapse trigger for tarball inspector
        const inspectorCollapse = document.getElementById('tarballInspectorCollapse');
        if (inspectorCollapse) {
            inspectorCollapse.addEventListener('show.bs.collapse', function() {
                if (!hasLoaded) {
                    loadTarballInspection();
                    hasLoaded = true;
                }
            });
        }

        function loadTarballInspection() {
            inspectorContainer.style.display = 'block';
            inspectorLoading.style.display = 'block';
            inspectorContent.style.display = 'none';
            inspectorError.style.display = 'none';

            const url = '{{ route("packages.releases.inspect-tarball", ["package" => $package->bundle_id, "release" => $release->id]) }}';

            fetch(url)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        inspectorError.textContent = data.error;
                        inspectorError.style.display = 'block';
                        inspectorLoading.style.display = 'none';
                        return;
                    }

                    // Build tree display
                    let treeText = '';

                    function renderTree(items, indent = '') {
                        items.forEach((item, index) => {
                            const isLast = index === items.length - 1;
                            const prefix = indent + (isLast ? '└── ' : '├── ');

                            if (item.type === 'directory') {
                                const status = item.is_standard ? '✓' : '✗';
                                treeText += prefix + item.name + ' ' + status + ' [DIR]\n';

                                if (item.has_asmdef) {
                                    treeText += indent + (isLast ? '    ' : '│   ') + '  .asmdef: ' + item.asmdef_files.join(', ') + '\n';
                                }

                                if (item.children && item.children.length > 0) {
                                    renderTree(item.children, indent + (isLast ? '    ' : '│   '));
                                }
                            } else if (item.type === 'truncated') {
                                treeText += item.display + '\n';
                            } else {
                                const status = item.is_standard ? ' ✓' : '';
                                const size = (item.size / 1024).toFixed(2) + ' KB';
                                treeText += prefix + item.name + status + ' (' + size + ')\n';
                            }
                        });
                    }

                    renderTree(data.tree);
                    treeElement.textContent = treeText || '(empty)';

                    // Display package.json
                    if (data.package_json_raw) {
                        try {
                            // Try to format it nicely
                            const parsed = JSON.parse(data.package_json_raw);
                            packageJsonElement.textContent = JSON.stringify(parsed, null, 2);
                        } catch (e) {
                            packageJsonElement.textContent = data.package_json_raw;
                        }
                    } else if (data.package_json) {
                        packageJsonElement.textContent = JSON.stringify(data.package_json, null, 2);
                    } else {
                        packageJsonElement.textContent = '(package.json not found)';
                    }

                    inspectorLoading.style.display = 'none';
                    inspectorContent.style.display = 'block';
                })
                .catch(error => {
                    inspectorError.textContent = 'Failed to load tarball inspection: ' + error.message;
                    inspectorError.style.display = 'block';
                    inspectorLoading.style.display = 'none';
                });
        }
    });
</script>