<div class="modal fade text-left" id="change-password-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="myModalLabel1">Change Password</h5>
                <button type="button" class="close rounded-pill" data-bs-dismiss="modal"
                    aria-label="Close">
                    <i data-feather="x"></i>
                </button>
            </div>

            <form action="{{ route('password.change') }}" method="POST" class="form form-vertical">
                @csrf
                <div class="modal-body">
                    <div class="form-body">
                        <div class="row">
                            <div class="col-12">
                                <div class="form-group">
                                    <label for="current_password">Current Password</label>
                                    <input type="password" id="current_password" class="form-control {{ ($errors->any() && session('change_password') && $errors->has('current_password')) ? 'is-invalid' : '' }}" name="current_password" placeholder="Current Password" required>
                                    @if ($errors->any() && session('change_password'))
                                        @error('current_password')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    @endif
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-group">
                                    <label for="new_password">Password</label>
                                    <input type="password" id="new_password" class="form-control {{ ($errors->any() && session('change_password') && $errors->has('password')) ? 'is-invalid' : '' }}" name="password" placeholder="Password" required>
                                    @if ($errors->any() && session('change_password'))
                                        @error('password')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    @endif
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-group">
                                    <label for="new_password_confirmation">Password</label>
                                    <input type="password" id="new_password_confirmation" class="form-control {{ ($errors->any() && session('change_password') && $errors->has('password_confirmation')) ? 'is-invalid' : '' }}" name="password_confirmation" placeholder="Confirm Password" required>
                                    @if ($errors->any() && session('change_password'))
                                        @error('password_confirmation')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    @endif
                                </div>
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
                        <i class="fa-thin fa-shield-keyhole me-1"></i>
                        Change Password
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@push('addon-script')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        @if ($errors->any() && session('change_password'))
            const changePwdModal = new bootstrap.Modal(document.getElementById('change-password-modal'));
            changePwdModal.show();
        @endif
    });
</script>
@endpush
