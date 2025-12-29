@section('title', 'Reset Password')

<livewire:auth.reset-password :token="$request->route('token')" :email="$request->email" />
