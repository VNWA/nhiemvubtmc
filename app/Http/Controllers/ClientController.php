<?php

namespace App\Http\Controllers;

use Inertia\Inertia;

class ClientController extends Controller
{
    public function index()
    {
        return Inertia::render('Home');
    }
}
