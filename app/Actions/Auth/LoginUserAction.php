<?php

namespace App\Actions\Auth;

class LoginUserAction
{
    /**
     * @param  array{email: string, password: string}  $credentials
     */
    public function execute(array $credentials): ?string
    {
        /** @var string|false $token */
        $token = auth('api')->attempt($credentials);

        return $token === false ? null : $token;
    }
}
