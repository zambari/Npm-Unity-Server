@include('common.topmenu')

<main class="container">
    <div class="p-4 p-md-5 mb-4 rounded text-body-emphasis bg-body-secondary">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1> {{config('app.scope_label')}} Management</h1>
            <div class="d-flex gap-2">
                <a href="{{ route('packages.create') }}" class="btn btn-primary">+ Add New Package</a>
                <button class="btn btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#addScopeForm" aria-expanded="false" aria-controls="addScopeForm">
                    + Add New   {{config('app.scope_label')}}
                </button>
            </div>
        </div>

        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <ul class="mb-0">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <!-- Collapsed Add Scope Form -->
        <div class="collapse mb-4" id="addScopeForm">
            <div class="card card-body">
                <h5 class="card-title">Add New  {{config('app.scope_label')}}</h5>
                <form method="POST" action="{{ route('admin.scopes.store') }}">
                    @csrf
                    <div class="mb-3">
                        <label for="scope" class="form-label"> {{config('app.scope_label')}}</label>
                        <input type="text" class="form-control" id="scope" name="scope" required maxlength="45" placeholder="com.example">
                        <div class="form-text">Must contain at least one dot (e.g., com.example, com.company.app)</div>
                    </div>
                    <div class="mb-3">
                        <label for="display_name" class="form-label">Display Name</label>
                        <input type="text" class="form-control" id="display_name" name="display_name" maxlength="255" placeholder="Example Company">
                        <div class="form-text">Optional friendly name for this scope</div>
                    </div>
                    <button type="submit" class="btn btn-success">Create Scope</button>
                    <button type="button" class="btn btn-secondary" data-bs-toggle="collapse" data-bs-target="#addScopeForm">Cancel</button>
                </form>
            </div>
        </div>

        <!-- Scopes List -->
        @if($scopes->count() > 0)
            <div class="list-group">
                @foreach($scopes as $scope)
                    <x-scope-row :scope="$scope" />
                @endforeach
            </div>
        @else
            <div class="mt-3">
                <p class="text-muted">No {{config('app.scope_label')}} found.</p>
            </div>
        @endif
    </div>
</main>

@include('common.footer')

