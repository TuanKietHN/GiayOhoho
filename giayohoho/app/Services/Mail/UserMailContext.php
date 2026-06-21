<?php

namespace App\Services\Mail;

use App\Models\User;

readonly class UserMailContext
{
    public function __construct(
        public int $accountId,
        public string $email,
        public string $displayName,
    ) {
    }

    public static function fromUser(User $user): self
    {
        $displayName = trim(($user->first_name ?? '').' '.($user->last_name ?? ''))
            ?: $user->username
            ?: $user->email;

        return new self((int) $user->id, (string) $user->email, $displayName);
    }
}
