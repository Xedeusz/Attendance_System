<?php

namespace App\Mail;

use App\Models\User;

class AccountInvitationMail extends EssentialNotificationMail
{
    public function __construct(#[\SensitiveParameter] string $activationUrl, ?User $recipient = null)
    {
        $welcome = $recipient?->isEmployee();
        parent::__construct(
            subject: $welcome ? 'Set up your LIMCOMA employee account' : 'Activate your LIMCOMA account',
            name: $recipient?->name ?? 'there',
            message: $welcome
                ? 'LIMCOMA HR created your employee account. Set your password to activate it and access your attendance and leave records.'
                : 'Set your password to activate your LIMCOMA account.',
            action: 'Set up your account',
            url: $activationUrl,
            fields: array_filter(['Registered email' => $recipient?->email, 'Employee ID' => $recipient?->employee?->employee_number]),
            note: 'This single-use setup link expires in 24 hours. If you were not expecting this invitation, contact HR.',
        );
    }
}
