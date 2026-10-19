<form method="post" action="{{ $user->exists ? route('admin.access.update',$user) : route('admin.access.store') }}" class="mt-3">
    @csrf
    @if($user->exists)
    @method('put')
    @endif
    <div class="form-grid">
        @include('admin.field',['field'=>'name','value'=>$user->name,'rule'=>'required'])
        @include('admin.field',['field'=>'username','value'=>$user->username,'rule'=>'required'])
        @include('admin.field',['field'=>'role','value'=>$user->roles->first()?->name,'rule'=>'required','choices'=>['erp_staff'=>__('erp.role_erp_staff'),'erp_hr'=>__('erp.role_erp_hr')]])
        <div class="field"><label>{{ __('erp.password') }}</label><input type="password" name="password" minlength="6" autocomplete="new-password" {{ $user->exists ? '' : 'required' }}></div>
        <div class="field"><label>{{ __('erp.password_confirmation') }}</label><input type="password" name="password_confirmation" autocomplete="new-password" {{ $user->exists ? '' : 'required' }}></div>
    </div>
    <button class="btn primary">{{ __('erp.save') }}</button>
</form>
