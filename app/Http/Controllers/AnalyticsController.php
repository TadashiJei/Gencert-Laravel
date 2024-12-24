<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Models\Template;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class AnalyticsController extends Controller
{
    public function index()
    {
        // Cache analytics data for 1 hour
        return view('dashboard.analytics', [
            'totalCertificates' => $this->getTotalCertificates(),
            'certificateGrowth' => $this->getCertificateGrowth(),
            'certificatesThisMonth' => $this->getCertificatesThisMonth(),
            'activeTemplates' => $this->getActiveTemplates(),
            'deliveryRate' => $this->getDeliveryRate(),
            'trends' => $this->getGenerationTrends(),
            'templateUsage' => $this->getTemplateUsage(),
            'recentActivity' => $this->getRecentActivity(),
        ]);
    }

    protected function getTotalCertificates()
    {
        return Cache::remember('analytics.total_certificates', 3600, function () {
            return Certificate::count();
        });
    }

    protected function getCertificateGrowth()
    {
        return Cache::remember('analytics.certificate_growth', 3600, function () {
            $thisMonth = Certificate::whereMonth('created_at', now()->month)->count();
            $lastMonth = Certificate::whereMonth('created_at', now()->subMonth()->month)->count();
            
            if ($lastMonth === 0) return 100;
            return round((($thisMonth - $lastMonth) / $lastMonth) * 100, 1);
        });
    }

    protected function getCertificatesThisMonth()
    {
        return Cache::remember('analytics.certificates_this_month', 3600, function () {
            return Certificate::whereMonth('created_at', now()->month)->count();
        });
    }

    protected function getActiveTemplates()
    {
        return Cache::remember('analytics.active_templates', 3600, function () {
            return Template::whereHas('certificates', function ($query) {
                $query->where('created_at', '>=', now()->subDays(30));
            })->count();
        });
    }

    protected function getDeliveryRate()
    {
        return Cache::remember('analytics.delivery_rate', 3600, function () {
            $total = Certificate::count();
            if ($total === 0) return 0;
            
            $delivered = Certificate::where('status', 'sent')->count();
            return round(($delivered / $total) * 100, 1);
        });
    }

    protected function getGenerationTrends()
    {
        return Cache::remember('analytics.generation_trends', 3600, function () {
            return Certificate::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as count')
            )
                ->where('created_at', '>=', now()->subDays(30))
                ->groupBy('date')
                ->orderBy('date')
                ->get();
        });
    }

    protected function getTemplateUsage()
    {
        return Cache::remember('analytics.template_usage', 3600, function () {
            return Template::select('templates.name')
                ->selectRaw('COUNT(certificates.id) as count')
                ->leftJoin('certificates', 'templates.id', '=', 'certificates.template_id')
                ->groupBy('templates.id', 'templates.name')
                ->orderByDesc('count')
                ->limit(5)
                ->get();
        });
    }

    protected function getRecentActivity()
    {
        return Cache::remember('analytics.recent_activity', 300, function () {
            return Certificate::with('template')
                ->select('certificates.*')
                ->selectRaw('COUNT(*) OVER (PARTITION BY template_id, DATE(created_at)) as recipient_count')
                ->orderByDesc('created_at')
                ->limit(10)
                ->get();
        });
    }
}
