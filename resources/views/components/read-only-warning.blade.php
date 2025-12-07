@php
    $isReadOnly = false;
    
    // Super-users (ENV credentials) always have write access
    if (!session('super_user')) {
        // Check if authenticated user (database user) is read-only
        if (Auth::check() && Auth::user()) {
            $isReadOnly = Auth::user()->readOnlyUser();
        }
    }
@endphp

@if($isReadOnly)
    <div class="alert alert-warning alert-dismissible fade show p-2" role="alert">
        <strong>⚠️ Read-Only Mode:</strong> You are currently logged in as a read-only user. You cannot make changes to the database.
        <button type="button" class="btn-close p-3" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif
