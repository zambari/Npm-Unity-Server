@include('common.topmenu')

<main class="container">
    <div class="p-4 p-md-5 mb-4 rounded text-body-emphasis bg-body-secondary">
        <div class="mb-4">
            <a href="{{ route('admin.scopes') }}" class="btn btn-outline-secondary">← Back to Scopes</a>
        </div>

        <h1>Edit  {{config('app.scope_label')}}</h1>

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

        <form method="POST" action="{{ route('admin.scopes.update', $scope->id) }}">
            @csrf
            @method('PUT')
            
            <div class="mb-3">
                <label for="scope" class="form-label">  {{config('app.scope_label')}}</label>
                <input type="text" class="form-control" id="scope" name="scope" value="{{ old('scope', $scope->scope) }}" required maxlength="45" placeholder="com.example">
                <div class="form-text">Must contain at least one dot (e.g., com.example, com.company.app)</div>
            </div>

            <div class="mb-3">
                <label for="display_name" class="form-label">Display Name</label>
                <input type="text" class="form-control" id="display_name" name="display_name" value="{{ old('display_name', $scope->display_name) }}" maxlength="255" placeholder="Example Company">
                <div class="form-text">Optional friendly name for this scope</div>
            </div>

            <div class="mb-3">
                <button type="submit" class="btn btn-primary">Update   {{config('app.scope_label')}}</button>
                <a href="{{ route('admin.scopes') }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>

        <hr class="my-4">

        <x-packages-list :scopeId="$scope->id" :allowEditing="true" />
    </div>
</main>

@include('common.footer')
