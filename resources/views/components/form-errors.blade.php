@if($errors->any())
<div class="form-errors"><x-alert variant="error"><ul>@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></x-alert></div>
@endif
