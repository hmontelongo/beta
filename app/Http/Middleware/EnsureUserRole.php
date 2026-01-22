<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        if (! $request->user()) {
            return redirect()->route('login');
        }

        $requiredRole = UserRole::from($role);

        if ($requiredRole === UserRole::Admin && ! $request->user()->isAdmin()) {
            return $this->redirectToCorrectSubdomain($request);
        }

        if ($requiredRole === UserRole::Agent && ! $request->user()->isAgent()) {
            return $this->redirectToCorrectSubdomain($request);
        }

        return $next($request);
    }

    protected function redirectToCorrectSubdomain(Request $request): Response
    {
        return redirect($request->user()->homeUrl($request->secure()));
    }
}
