<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserSsoAuthenticated
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<string, mixed>  $userProfile
     */
    public function __construct(
        public array $userProfile,
    ) {}
}
