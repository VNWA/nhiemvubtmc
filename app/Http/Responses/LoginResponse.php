<?php

namespace App\Http\Responses;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request): RedirectResponse|JsonResponse
    {
        if ($request->wantsJson()) {
            return new JsonResponse('', 204);
        }

        /** @var Request $request */
        /** @var User|null $user */
        $user = $request->user();

        $intended = $user !== null && $user->hasAnyRole(['admin', 'staff'])
            ? route('admin.users.index')
            : route('home');

        return redirect()->intended($intended);
    }
}
