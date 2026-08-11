<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;

class AdminPanelController extends Controller
{
    public function login()
    {
        return view('admin.login');
    }

    public function dashboard()
    {
        return view('admin.dashboard');
    }
}
