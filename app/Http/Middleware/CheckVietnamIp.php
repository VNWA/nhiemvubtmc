<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Stevebauman\Location\Facades\Location;
use Symfony\Component\HttpFoundation\Response;

class CheckVietnamIp
{
    /**
     * Chỉ giới hạn IP Việt Nam cho khách (role `user`). Admin / nhân viên truy cập bình thường.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->runningUnitTests()) {
            return $next($request);
        }

        $user = $request->user();
        if ($user instanceof User && $user->hasAnyRole(['admin', 'staff'])) {
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
