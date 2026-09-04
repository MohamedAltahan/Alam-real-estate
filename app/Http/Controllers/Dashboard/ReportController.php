<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ViewingService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function __construct(private ViewingService $viewings) {}

    /** تقرير معدل التحول: المعاينات التي انتهت باختيار العقار مقابل الرفض */
    public function conversion(Request $request): View
    {
        $filters = $request->only('from', 'to', 'agent_id');

        return view('dashboard.reports.conversion', [
            'report' => $this->viewings->conversionReport($filters),
            'agents' => User::where('is_agent', true)->orderBy('name')->get(['id', 'name']),
            'filters' => $filters,
        ]);
    }
}
