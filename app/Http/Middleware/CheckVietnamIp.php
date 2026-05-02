<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Stevebauman\Location\Facades\Location;
use Symfony\Component\HttpFoundation\Response;

class CheckVietnamIp
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->runningUnitTests()) {
            return $next($request);
        }

        $position = Location::get($request->ip());

        if ($position === false || $position->countryCode === null) {
            abort(403, 'Lỗi');
        }

        if (strtoupper($position->countryCode) !== 'VN') {
            abort(403, 'Lỗi');
        }

        return $next($request);
    }
}
