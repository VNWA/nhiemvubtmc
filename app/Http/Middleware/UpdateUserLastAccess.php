<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class UpdateUserLastAccess
{
    private const CACHE_PREFIX = 'user.last_access.';

    /**
     * Throttled "last access" (last_login_at / last_login_ip) for authenticated web
     * requests. Throttle is per user via Cache, not the session, to avoid extra
     * session table writes; interval defaults to 30 min (config).
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->isIgnoredForLastAccess($request)) {
            return $response;
        }

        $user = $request->user();

        if (! $user instanceof User) {
            return $response;
        }

        $interval = max(1, (int) config('app.user_last_access_min_interval_seconds', 1800));
        $cacheKey = self::CACHE_PREFIX.$user->getKey();

        if (Cache::has($cacheKey)) {
            return $response;
        }

        $at = now();
        $ip = $request->ip();

        $user->forceFill([
            'last_login_at' => $at,
            'last_login_ip' => $ip,
        ])->saveQuietly();

        Cache::put(
            $cacheKey,
            $at->getTimestamp(),
            $interval,
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
