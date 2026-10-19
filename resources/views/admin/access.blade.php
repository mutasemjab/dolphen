@extends('admin.layouts.factory')
@section('title', __('erp.users'))
@section('content')
<section class="panel form-panel">
    @foreach($users as $user)
        <details class="access-user"><summary>{{ $user->name }} · {{ $user->username }}</summary>
        @if(!$user->is_super && $user->id !== auth('admin')->id())
            @include('admin.user-form',['user'=>$user])
        @else
            <p class="hint">{{ __('erp.admin_cli_only') }}</p>
        @endif
        </details>
    @endforeach
    {{ $users->links('admin.pagination') }}
</section>
<section class="panel form-panel"><h2>{{ __('erp.new_user') }}</h2>
    @include('admin.user-form',['user'=>new \App\Models\Admin])
</section>
@endsection
