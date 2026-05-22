<?php

namespace App\Http\Controllers;

use App\Models\Clinic;
use App\Services\ImpersonationService;
use Illuminate\Support\Facades\Auth;

class ImpersonationController extends Controller
{
    public function start(Clinic $clinic, ImpersonationService $service)
    {
        $admin = Auth::guard('admin')->user();
        abort_unless($admin, 403);

        $service->start($admin, $clinic);

        return redirect('/clinic-dashboard');
    }

    public function stop(ImpersonationService $service)
    {
        $service->stop();
        return redirect('/admin');
    }
}
