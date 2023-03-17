@if (Session::has('message'))
<div class="alert alert-{{ Session::get('message_type', 'danger') }} alert-dismissable">
  <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
  <strong>Message &nbsp;</strong> {{ Session::get('message') }}
</div>
@endif