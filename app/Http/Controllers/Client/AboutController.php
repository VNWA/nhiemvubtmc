<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Appearance;
use Inertia\Inertia;
use Inertia\Response;

class AboutController extends Controller
{
    public function show(): Response
    {
        $data = Appearance::getValue('About', []);
        $content = is_array($data) && isset($data['content']) && is_string($data['content'])
            ? $data['content']
            : '';

        return Inertia::render('About', [
            'content' => $content,
        ]);
    }
}
