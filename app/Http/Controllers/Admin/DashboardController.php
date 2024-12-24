<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\Template;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'users' => User::count(),
            'certificates' => Certificate::count(),
            'templates' => Template::count(),
            'certificates_today' => Certificate::whereDate('created_at', today())->count(),
        ];

        $recentUsers = User::latest()->take(5)->get();
        $recentCertificates = Certificate::with(['user', 'template'])->latest()->take(5)->get();

        $certificateStats = Certificate::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('COUNT(*) as count')
        )
            ->whereDate('created_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $userStats = User::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('COUNT(*) as count')
        )
            ->whereDate('created_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return view('admin.dashboard', compact(
            'stats',
            'recentUsers',
            'recentCertificates',
            'certificateStats',
            'userStats'
        ));
    }
}
