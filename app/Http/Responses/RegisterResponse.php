<?php

namespace App\Http\Responses;

use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Symfony\Component\HttpFoundation\Response;

class RegisterResponse implements RegisterResponseContract
{
    public function toResponse($request): Response
    {
        return redirect($this->redirectUrl($request));
    }

    protected function redirectUrl(Request $request): string
    {
        return $request->user()->homeUrl($request->secure());
    }
}
