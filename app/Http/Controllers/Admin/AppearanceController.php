<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appearance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AppearanceController extends Controller
{
    /**
     * Render an Inertia appearance page (e.g. About, Banner, Footer).
     * Page name doubles as the storage key so the view receives its
     * persisted content as a prop and can hydrate immediately.
     */
    public function view(string $name): Response
    {
        return Inertia::render('admin/appearance/'.$name, [
            'appearanceKey' => $name,
            'data' => Appearance::getValue($name, []),
        ]);
    }

    public function load(string $key): JsonResponse
    {
        return response()->json([
            'data' => Appearance::getValue($key, []),
            'message' => 'success',
        ]);
    }

    public function update(Request $request, string $key): JsonResponse
    {
        $data = $request->except(['_token', '_method']);

        Appearance::setValue($key, $data);

        return response()->json([
            'message' => 'Cập nhật thành công',
            'key' => $key,
            'data' => $data,
        ]);
    }
}
