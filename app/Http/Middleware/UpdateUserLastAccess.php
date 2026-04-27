<?php

namespace App\Http\Middleware;

use App\Jobs\UpdateUserLastAccessJob;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UpdateUserLastAccess
{
    /**
     * Mỗi request (sau khi xử lý) qua route có middleware này: dispatch job cập nhật
     * last_login_at / last_login_ip — không chặn theo khoảng thời gian.
     * Một số route (poll/JSON) có thể loại trừ trong config để tránh spam queue.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $user = $request->user();

        if (! $user instanceof User) {
            return $response;
        }

        if ($this->isIgnoredForLastAccess($request)) {
            return $response;
        }

        $ip = $request->ip();

        UpdateUserLastAccessJob::dispatch(
            userId: (int) $user->getKey(),
            ip: is_string($ip) ? $ip : null,
        );

        return $response;
    }

    private function isIgnoredForLastAccess(Request $request): bool
    {
        $ignoredNames = config('app.user_last_access_ignored_routes', []);
        if ($ignoredNames !== [] && $request->route() !== null && $request->routeIs(...$ignoredNames)) {
            return true;
        }

        $decodedPath = $request->decodedPath();

        foreach (config('app.user_last_access_ignored_path_patterns', []) as $pattern) {
            if (str_contains($pattern, '*')) {
                if ($request->is($pattern)) {
                    return true;
                }
            } elseif ($decodedPath === $pattern || $decodedPath === ltrim($pattern, '/')) {
                return true;
            }
        }

        return false;
    }
}
