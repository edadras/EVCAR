<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminLiveService;
use Illuminate\View\View;

class ChargingSessionController extends Controller
{
    public function index(AdminLiveService $live): View
    {
        return view('admin.sessions.index', ['live' => $live->sessions()]);
    }
}
