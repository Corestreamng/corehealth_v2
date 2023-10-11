@if(Session::has('message'))
    <div class="alert alert-{{ Session::get('message_type','danger') }} alert-dismissible fade show" role="alert">
        <strong>{{ Session::get('message') }}</strong>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif
