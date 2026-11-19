{!! $title !!}

Hello {!! $recipientName !!},

{!! $bodyMessage !!}

@foreach($fields as $label => $value)
@continue($value === null)
{!! $label !!}: {!! $value !!}
@endforeach

{!! $action !!}:
{!! $url !!}

@if($note)
{!! $note !!}

@endif
LIMCOMA Multipurpose Cooperative
Employee Attendance and Management System
