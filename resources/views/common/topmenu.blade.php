@include('common.header')

<body>
    <div class="container">
        @if(auth()->check())
        {{-- Header for logged in users (database credentials) --}}
        <header class="d-flex flex-wrap align-items-center justify-content-center justify-content-md-between py-3 mb-4 border-bottom">
            <div class="col-md-3 mb-2 mb-md-0"> <a href="/" class="d-inline-flex link-body-emphasis text-decoration-none"> <svg class="bi" width="40" height="32" role="img" aria-label="Bootstrap">
                        <use xlink:href="#bootstrap"></use>
                    </svg> </a> </div>
            <ul class="nav col-5 col-md-auto mb-2 justify-content-center mb-md-0">
         
                <li><a href="{{ route('packages.index') }}" class="nav-link px-2">Packages</a></li>
                          <li><a href="https://github.com/zambari/Npm-Unity-Server?tab=readme-ov-file#npm-unity-server----php-only" class="nav-link px-2">Docs</a></li>
            </ul>
            <div class="col-md-3 text-end">
                <span class="me-2">
                    {{ Auth::user()->name ?? Auth::user()->email }}
                    @if(Auth::user()->readOnlyUser())
                        <span class="badge bg-secondary">Read-Only</span>
                    @endif
                </span>
                <form method="POST" action="{{ route('logout') }}" style="display: inline;">
                    @csrf
                    <button type="submit" class="btn btn-outline-danger">Logout</button>
                </form>
            </div>
        </header>
        @elseif(session('admin_authenticated'))
        {{-- Header for super-users (ENV credentials) --}}
        <header class="d-flex flex-wrap align-items-center justify-content-center justify-content-md-between py-3 mb-4 border-bottom">
            <div class="col-md-3 mb-2 mb-md-0"> <a href="/" class="d-inline-flex link-body-emphasis text-decoration-none"> <svg class="bi" width="40" height="32" role="img" aria-label="Bootstrap">
                        <use xlink:href="#bootstrap"></use>
                    </svg> </a> </div>
            <ul class="nav col-12 col-md-auto mb-2 justify-content-center mb-md-0">
              
                <li><a href="{{ route('packages.index') }}" class="nav-link px-2">Packages</a></li>
                <li><a href="{{ route('admin.users') }}" class="nav-link px-2">Users</a></li>
                <li><a href="{{ route('admin.scopes') }}" class="nav-link px-2">{{ config('app.scope_label') }}</a></li>
                <li><a href="{{ route('admin.databaseadmin') }}" class="nav-link px-2">Database</a></li>
            </ul>
            <div class="col-md-3 text-end">
                <span class="me-2">
                    {{ session('admin_email') }}
                    @php
                        $user = \App\Models\User::where('email', session('admin_email'))->first();
                    @endphp
                    @if($user && $user->readOnlyUser())
                        <span class="badge bg-secondary">Read-Only</span>
                    @endif
                </span>
                <form method="POST" action="{{ route('logout') }}" style="display: inline;">
                    @csrf
                    <button type="submit" class="btn btn-outline-danger">Logout</button>
                </form>
            </div>
        </header>
        @else
        {{-- Header for guests (not logged in) --}}
        <header class="d-flex flex-wrap align-items-center justify-content-center justify-content-md-between py-3 mb-4 border-bottom">
            <div class="col-md-3 mb-2 mb-md-0"> <a href="/" class="d-inline-flex link-body-emphasis text-decoration-none"> <svg class="bi" width="40" height="32" role="img" aria-label="Bootstrap">
                        <use xlink:href="#bootstrap"></use>
                    </svg> </a> </div>
            <div class="col-md-3 text-end">
                <button type="button" class="btn btn-warning" onclick="location.href='./loginform';">Login</button>
            </div>
        </header>
        @endif
    </div>

        