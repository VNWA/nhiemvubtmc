<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NormalizeUsername
{
    /**
     * Strip every whitespace character from the `username` input so that
     * trailing/leading or internal spaces never reach validation or the
     * authentication layer.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->has('username')) {
            $value = $request->input('username');

            if (is_string($value)) {
                $request->merge([
                    'username' => preg_replace('/\s+/u', '', $value) ?? '',
                ]);
            }
        }

        return $next($request);
    }
}
