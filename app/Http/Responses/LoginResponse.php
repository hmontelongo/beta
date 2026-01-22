<?php

namespace App\Http\Responses;

use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request): Response
    {
        if ($request->wantsJson()) {
            return response()->json(['two_factor' => false]);
        }

        return redirect($this->redirectUrl($request));
    }

    protected function redirectUrl(Request $request): string
    {
        $user = $request->user();
        $scheme = $request->secure() ? 'https' : 'http';

        if ($user->isAdmin()) {
            $domain = config('domains.admin');
            $path = '/platforms';
        } else {
            $domain = config('domains.agents');
            $path = '/properties';
        }

        return "{$scheme}://{$domain}{$path}";
    }
}
