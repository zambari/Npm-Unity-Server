@include('common.topmenu')

<main class="container">
<div class="p-4 p-md-5 mb-4 rounded text-body-emphasis bg-body-secondary">
<div></div>Hello, this is a landing page for a PHP only Unity3D NPM PackageRegistry Server. <div>
To list packages, open your Unity Project Preferences, go to Package Manager, add a custom registry,
add following URL: 
<br>
Name: <code>custom</code>/any</code><br>
Url: <code>{{ url('/') }}</code><br>
Scope: any of below, or custom:<br>

<ul>
    @forelse ($scopes as $scope)
        <li><code>  {{ $scope->scope }}</code> <small class="text-muted">({{ $scope->packages_count }} available packages)</small></li>
    @empty
        <li><em>No scopes available</em></li>
    @endforelse
</ul>
<p>Total available packages: {{ $totalPackages }}</p>
<div>
    <p>Client IP address: {{ request()->ip() }}</p>
    @php
        $interfaces = [];
        if (function_exists('net_get_interfaces')) {
            $netInterfaces = net_get_interfaces();
            if ($netInterfaces !== false) {
                foreach ($netInterfaces as $name => $interface) {
                    $ips = [];
                    if (isset($interface['unicast'])) {
                        foreach ($interface['unicast'] as $addr) {
                            if (isset($addr['address'])) {
                                $ips[] = $addr['address'];
                            }
                        }
                    }
                    if (!empty($ips)) {
                        $interfaces[$name] = $ips;
                    }
                }
            }
        }
    @endphp
    @if (!empty($interfaces))
        <p><strong>Server Network Interfaces:</strong></p>
        <ul>
            @foreach ($interfaces as $name => $ips)
                <li><strong>{{ $name }}:</strong> {{ implode(', ', $ips) }}</li>
            @endforeach
        </ul>
    @else
        <p><em>Network interface information not available</em></p>
    @endif
</div>

</div>

</div>
</main>

@include('common.footer')
