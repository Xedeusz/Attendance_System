@php
    $messages = [];
    foreach (['error', 'warning', 'success', 'info', 'status'] as $category) {
        $message = session($category);
        if (is_string($message) && $message !== '') {
            $messages[] = ['type' => $category === 'status' ? 'success' : $category, 'text' => $message];
        }
    }
    $notificationData = [
        'messages' => $messages,
        'validation' => $errors->any() || $errors->updatePassword->any(),
        'duplicateBranchCode' => $errors->has('branch_code') && str_contains($errors->first('branch_code'), 'already been taken'),
    ];
@endphp
<div hidden data-notifications="{{ json_encode($notificationData) }}"></div>
<noscript>@foreach($messages as $message)<x-alert :variant="$message['type'] === 'error' ? 'error' : 'info'">{{ $message['text'] }}</x-alert>@endforeach</noscript>
