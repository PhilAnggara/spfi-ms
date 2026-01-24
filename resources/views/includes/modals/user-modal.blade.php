<div class="modal fade text-left modal-borderless" id="create-modal" tabindex="-1"
    role="dialog" aria-labelledby="myModalLabel1" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create User</h5>
                <button type="button" class="close rounded-pill" data-bs-dismiss="modal"
                    aria-label="Close">
                    <i data-feather="x"></i>
                </button>
            </div>

            <form action="{{ route('user.store') }}" method="POST" class="form form-horizontal">
                @csrf
                <div class="modal-body">
                    <div class="form-body">
                        <div class="row">

                            <div class="col-md-4">
                                <label for="name">Name</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <input type="text" id="name" class="form-control {{ ($errors->any() && !session('editing_user_id')) ? ($errors->has('name') ? 'is-invalid' : '') : '' }}" value="{{ ($errors->any() && !session('editing_user_id')) ? old('name') : '' }}" name="name" placeholder="Full Name" required>
                                @if ($errors->any() && !session('editing_user_id'))
                                    @error('name')
                                        <div class="invalid-feedback">
                                            {{ $message }}
                                        </div>
                                    @enderror
                                @endif
                            </div>

                            <div class="col-md-4">
                                <label for="username">Username</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <input type="text" id="username" class="form-control {{ ($errors->any() && !session('editing_user_id')) ? ($errors->has('username') ? 'is-invalid' : '') : '' }}" value="{{ ($errors->any() && !session('editing_user_id')) ? old('username') : '' }}" name="username" placeholder="Username" required>
                                @if ($errors->any() && !session('editing_user_id'))
                                    @error('username')
                                        <div class="invalid-feedback">
                                            {{ $message }}
                                        </div>
                                    @enderror
                                @endif
                            </div>

                            <div class="col-md-4">
                                <label for="email">Email</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <input type="email" id="email" class="form-control {{ ($errors->any() && !session('editing_user_id')) ? ($errors->has('email') ? 'is-invalid' : '') : '' }}" value="{{ ($errors->any() && !session('editing_user_id')) ? old('email') : '' }}" name="email" placeholder="Email" required>
                                @if ($errors->any() && !session('editing_user_id'))
                                    @error('email')
                                        <div class="invalid-feedback">
                                            {{ $message }}
                                        </div>
                                    @enderror
                                @endif
                            </div>

                            <div class="col-md-4">
                                <label for="department">Department</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <select class="choices form-select {{ ($errors->any() && !session('editing_user_id')) ? ($errors->has('department_id') ? 'is-invalid' : '') : '' }}" id="department" name="department_id" required>
                                    <option value="" {{ ($errors->any() && !session('editing_user_id') && old('department_id')) ? '' : 'selected' }} disabled>-- Select Department --</option>
                                    @foreach ($departments as $department)
                                        <option value="{{ $department->id }}" {{ ($errors->any() && !session('editing_user_id') && old('department_id') == $department->id) ? 'selected' : '' }}>{{ $department->name }}</option>
                                    @endforeach
                                </select>
                                @if ($errors->any() && !session('editing_user_id'))
                                    @error('department_id')
                                        <div class="invalid-feedback">
                                            {{ $message }}
                                        </div>
                                    @enderror
                                @endif
                            </div>

                            <div class="col-md-4">
                                <label for="role">Role</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <select class="choices form-select {{ ($errors->any() && !session('editing_user_id')) ? ($errors->has('role') ? 'is-invalid' : '') : '' }}" id="role" name="role" required>
                                    <option value="" {{ ($errors->any() && !session('editing_user_id') && old('role')) ? '' : 'selected' }} disabled>-- Select Role --</option>
                                    <option value="Administrator" {{ ($errors->any() && !session('editing_user_id') && old('role') == 'Administrator') ? 'selected' : '' }}>Administrator</option>
                                    <option value="Purchasing Manager" {{ ($errors->any() && !session('editing_user_id') && old('role') == 'Purchasing Manager') ? 'selected' : '' }}>Purchasing Manager</option>
                                    <option value="Purchasing Staff" {{ ($errors->any() && !session('editing_user_id') && old('role') == 'Purchasing Staff') ? 'selected' : '' }}>Purchasing Staff</option>
                                </select>
                                @if ($errors->any() && !session('editing_user_id'))
                                    @error('role')
                                        <div class="invalid-feedback">
                                            {{ $message }}
                                        </div>
                                    @enderror
                                @endif
                            </div>

                            <div class="col-md-4">
                                <label for="password">Password</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <input type="password" id="password" class="form-control {{ ($errors->any() && !session('editing_user_id')) ? ($errors->has('password') ? 'is-invalid' : '') : '' }}" name="password" placeholder="Password" required>
                                @if ($errors->any() && !session('editing_user_id'))
                                    @error('password')
                                        <div class="invalid-feedback">
                                            {{ $message }}
                                        </div>
                                    @enderror
                                @endif
                            </div>

                            <div class="col-md-4">
                                <label for="password_confirmation">Confirm Password</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <input type="password" id="password_confirmation" class="form-control {{ ($errors->any() && !session('editing_user_id')) ? ($errors->has('password_confirmation') ? 'is-invalid' : '') : '' }}" name="password_confirmation" placeholder="Confirm Password" required>
                                @if ($errors->any() && !session('editing_user_id'))
                                    @error('password_confirmation')
                                        <div class="invalid-feedback">
                                            {{ $message }}
                                        </div>
                                    @enderror
                                @endif
                            </div>

                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="reset" class="btn icon icon-left btn-light-secondary">
                        <i class="fa-thin fa-rotate-left"></i>
                        Reset
                    </button>
                    <button type="submit" class="btn icon icon-left btn-primary ms-1">
                        <i class="fa-thin fa-file-plus me-1"></i>
                        Save
                    </button>
                </div>
            </form>

        </div>
    </div>
</div>

@foreach ($users as $user)
<div class="modal fade text-left modal-borderless" id="edit-modal-{{ $user->id }}" tabindex="-1" role="dialog" aria-labelledby="myModalLabel1" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit User - {{ $user->name }}</h5>
                <button type="button" class="close rounded-pill" data-bs-dismiss="modal"
                    aria-label="Close">
                    <i data-feather="x"></i>
                </button>
            </div>

            <form action="{{ route('user.update', $user->id) }}" method="POST" class="form form-horizontal">
                @csrf
                @method('PUT')
                <div class="modal-body">
                    <div class="form-body">
                        <div class="row">

                            <div class="col-md-4">
                                <label for="edit-name-{{ $user->id }}">Name</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <input type="text" id="edit-name-{{ $user->id }}" class="form-control {{ ($errors->any() && session('editing_user_id') == $user->id) ? ($errors->has('name') ? 'is-invalid' : '') : '' }}" value="{{ ($errors->any() && session('editing_user_id') == $user->id) ? old('name') : $user->name }}" name="name" placeholder="Full Name" required>
                                @if ($errors->any() && session('editing_user_id') == $user->id)
                                    @error('name')
                                        <div class="invalid-feedback">
                                            {{ $message }}
                                        </div>
                                    @enderror
                                @endif
                            </div>

                            <div class="col-md-4">
                                <label for="edit-username-{{ $user->id }}">Username</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <input type="text" id="edit-username-{{ $user->id }}" class="form-control {{ ($errors->any() && session('editing_user_id') == $user->id) ? ($errors->has('username') ? 'is-invalid' : '') : '' }}" value="{{ ($errors->any() && session('editing_user_id') == $user->id) ? old('username') : $user->username }}" name="username" placeholder="Username" required>
                                @if ($errors->any() && session('editing_user_id') == $user->id)
                                    @error('username')
                                        <div class="invalid-feedback">
                                            {{ $message }}
                                        </div>
                                    @enderror
                                @endif
                            </div>

                            <div class="col-md-4">
                                <label for="edit-email-{{ $user->id }}">Email</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <input type="email" id="edit-email-{{ $user->id }}" class="form-control {{ ($errors->any() && session('editing_user_id') == $user->id) ? ($errors->has('email') ? 'is-invalid' : '') : '' }}" value="{{ ($errors->any() && session('editing_user_id') == $user->id) ? old('email') : $user->email }}" name="email" placeholder="Email" required>
                                @if ($errors->any() && session('editing_user_id') == $user->id)
                                    @error('email')
                                        <div class="invalid-feedback">
                                            {{ $message }}
                                        </div>
                                    @enderror
                                @endif
                            </div>

                            <div class="col-md-4">
                                <label for="edit-department-{{ $user->id }}">Department</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <select class="choices form-select {{ ($errors->any() && session('editing_user_id') == $user->id) ? ($errors->has('department_id') ? 'is-invalid' : '') : '' }}" id="edit-department-{{ $user->id }}" name="department_id" required>
                                    <option value="" disabled>-- Select Department --</option>
                                    @foreach ($departments as $department)
                                        <option value="{{ $department->id }}" {{ (($errors->any() && session('editing_user_id') == $user->id) ? old('department_id') : $user->department_id) == $department->id ? 'selected' : '' }}>{{ $department->name }}</option>
                                    @endforeach
                                </select>
                                @if ($errors->any() && session('editing_user_id') == $user->id)
                                    @error('department_id')
                                        <div class="invalid-feedback">
                                            {{ $message }}
                                        </div>
                                    @enderror
                                @endif
                            </div>

                            <div class="col-md-4">
                                <label for="edit-role-{{ $user->id }}">Role</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <select class="choices form-select {{ ($errors->any() && session('editing_user_id') == $user->id) ? ($errors->has('role') ? 'is-invalid' : '') : '' }}" id="edit-role-{{ $user->id }}" name="role" required>
                                    <option value="" disabled>-- Select Role --</option>
                                    <option value="Administrator" {{ (($errors->any() && session('editing_user_id') == $user->id) ? old('role') : $user->role) == 'Administrator' ? 'selected' : '' }}>Administrator</option>
                                    <option value="Purchasing Manager" {{ (($errors->any() && session('editing_user_id') == $user->id) ? old('role') : $user->role) == 'Purchasing Manager' ? 'selected' : '' }}>Purchasing Manager</option>
                                    <option value="Purchasing Staff" {{ (($errors->any() && session('editing_user_id') == $user->id) ? old('role') : $user->role) == 'Purchasing Staff' ? 'selected' : '' }}>Purchasing Staff</option>
                                </select>
                                @if ($errors->any() && session('editing_user_id') == $user->id)
                                    @error('role')
                                        <div class="invalid-feedback">
                                            {{ $message }}
                                        </div>
                                    @enderror
                                @endif
                            </div>

                            <div class="col-md-4">
                                <label for="edit-password-{{ $user->id }}">Password</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <input type="password" id="edit-password-{{ $user->id }}" class="form-control {{ ($errors->any() && session('editing_user_id') == $user->id) ? ($errors->has('password') ? 'is-invalid' : '') : '' }}" name="password" placeholder="Leave blank to keep current password">
                                @if ($errors->any() && session('editing_user_id') == $user->id)
                                    @error('password')
                                        <div class="invalid-feedback">
                                            {{ $message }}
                                        </div>
                                    @enderror
                                @endif
                                <small class="text-muted">Leave blank if you don't want to change the password</small>
                            </div>

                            <div class="col-md-4">
                                <label for="edit-password-confirmation-{{ $user->id }}">Confirm Password</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <input type="password" id="edit-password-confirmation-{{ $user->id }}" class="form-control {{ ($errors->any() && session('editing_user_id') == $user->id) ? ($errors->has('password_confirmation') ? 'is-invalid' : '') : '' }}" name="password_confirmation" placeholder="Confirm Password">
                                @if ($errors->any() && session('editing_user_id') == $user->id)
                                    @error('password_confirmation')
                                        <div class="invalid-feedback">
                                            {{ $message }}
                                        </div>
                                    @enderror
                                @endif
                            </div>

                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn icon icon-left btn-light-primary" data-bs-dismiss="modal">
                        <i class="fa-thin fa-xmark"></i>
                        Cancel
                    </button>
                    <button type="submit" class="btn icon icon-left btn-primary ms-1">
                        <i class="fa-thin fa-file-pen me-1"></i>
                        Update
                    </button>
                </div>
            </form>

        </div>
    </div>
</div>

@endforeach
