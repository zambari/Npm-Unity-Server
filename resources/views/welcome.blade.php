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


@php
    $readOnlyToken = config('app.read_only_privilege_token', 'NONE');
    $guestUser = \App\Models\User::where('email', 'guest@guest.com')
        ->where('privileges', 'LIKE', '%' . $readOnlyToken . '%')
        ->where('disabled', false)
        ->first();
@endphp
@if($guestUser)
    <div >
      This server allows guest user access. Please log in as user <strong>guest@guest.com</strong> with password <strong>guest</strong> to browse packages in read only mode:
    </div><br>
    <div>
<a href="{{ route('packages.index') }}" class="btn btn-primary">Browse Packages</a>
</div>
@endif

</div>
</div>

</div>
</main>

@include('common.footer')
