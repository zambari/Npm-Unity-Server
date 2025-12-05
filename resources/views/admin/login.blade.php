@include('common.header')


<link href="{{ asset('css/sign-in.css') }}" rel="stylesheet" />

<main class="container"></main>

    <main class="form-signin w-100 m-auto">
        <div class="p-4 p-md-5 mb-4 rounded text-body-emphasis bg-body-secondary">
            <form method="POST" action="{{ route('login') }}">
                @csrf
                <h1 class="h3 mb-3 fw-normal">Please sign in</h1>
                <div class="form-floating">
                    <input type="text" class="form-control" id="floatingInput" name="username" placeholder="Username" required>
                    <label for="floatingInput">Username</label>
                </div>
                <div class="form-floating">
                    <input type="password" class="form-control" id="floatingPassword" name="password" placeholder="Password" required>
                    <label for="floatingPassword">Password</label>
                </div>
             
                <button class="btn btn-primary w-100 py-2" type="submit">
                    Sign in
                </button>
                <p class="mt-5 mb-3 text-body-secondary">Contact admin to add users.</p>
            </form>
            
            @if($errors->any())
            <div class="alert alert-danger alert-dismissible fade show mt-3" role="alert">
                <strong>Error:</strong> {{ $errors->first() }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            @endif
        </div>
    </main>
    @include('common.footer')