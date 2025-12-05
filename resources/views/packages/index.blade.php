@include('common.topmenu')

<main class="container">
<div class="{{ config('app.main_div_style') }}">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Packages</h1>
            <a href="{{ route('packages.create') }}" class="btn btn-primary">+ Add New Package</a>
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

        <!-- Packages List -->
        <x-packages-list :packages="$packages" :scopeId="null" :allowEditing="true" />
    </div>
</main>

@include('common.footer')

