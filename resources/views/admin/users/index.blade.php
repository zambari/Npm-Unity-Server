@include('common.topmenu')

<main class="container">
    <div class="p-4 p-md-5 mb-4 rounded text-body-emphasis bg-body-secondary">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>User Management</h1>
            <button class="btn btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#addUserForm" aria-expanded="false" aria-controls="addUserForm">
                + Add New User
            </button>
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

        <!-- Collapsed Add User Form -->
        <div class="collapse mb-4" id="addUserForm">
            <div class="card card-body">
                <h5 class="card-title">Add New User</h5>
                <form method="POST" action="{{ route('admin.users.store') }}">
                    @csrf
                    <div class="mb-3">
                        <label for="name" class="form-label">Name</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required minlength="5">
                        <div class="form-text">Minimum 5 characters</div>
                    </div>
                    <button type="submit" class="btn btn-success">Create User</button>
                    <button type="button" class="btn btn-secondary" data-bs-toggle="collapse" data-bs-target="#addUserForm">Cancel</button>
                </form>
            </div>
        </div>

        <!-- Users List -->
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Edit</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                        <tr>
                            <td>{{ $user->id }}</td>
                            <td>{{ $user->name }}</td>
                            <td>{{ $user->email }}</td>
                            <td>
                                @if($user->disabled)
                                    <span class="badge bg-danger">Disabled</span>
                                @else
                                    <span class="badge bg-success">Active</span>
                                @endif
                            </td>
                            <td>
                                <form method="POST" action="{{ route('admin.users.toggle-edit-privilege', $user->id) }}" style="display: inline;">
                                    @csrf
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" role="switch" id="editToggle{{ $user->id }}" 
                                               {{ !$user->readOnlyUser() ? 'checked' : '' }} 
                                               onchange="this.form.submit()">
                                        <label class="form-check-label" for="editToggle{{ $user->id }}">
                                            {{ $user->readOnlyUser() ? 'Read-only' : 'Edit' }}
                                        </label>
                                    </div>
                                </form>
                            </td>
                            <td>{{ $user->created_at->format('Y-m-d H:i') }}</td>
                            <td>
                                <div class="btn-group" role="group">
                                    <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#resetPasswordModal{{ $user->id }}">
                                        Reset Password
                                    </button>
                                    <form method="POST" action="{{ route('admin.users.toggle-disabled', $user->id) }}" style="display: inline;">
                                        @csrf
                                        <button type="submit" class="btn btn-sm {{ $user->disabled ? 'btn-success' : 'btn-danger' }}">
                                            {{ $user->disabled ? 'Enable' : 'Disable' }}
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>

                        <!-- Reset Password Modal -->
                        <div class="modal fade" id="resetPasswordModal{{ $user->id }}" tabindex="-1" aria-labelledby="resetPasswordModalLabel{{ $user->id }}" aria-hidden="true">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="resetPasswordModalLabel{{ $user->id }}">Reset Password for {{ $user->name }}</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <form method="POST" action="{{ route('admin.users.reset-password', $user->id) }}">
                                        @csrf
                                        <div class="modal-body">
                                            <div class="mb-3">
                                                <label for="new_password{{ $user->id }}" class="form-label">New Password</label>
                                                <input type="password" class="form-control" id="new_password{{ $user->id }}" name="new_password" required minlength="5">
                                                <div class="form-text">Minimum 5 characters</div>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-primary">Reset Password</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center">No users found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</main>

@include('common.footer')

