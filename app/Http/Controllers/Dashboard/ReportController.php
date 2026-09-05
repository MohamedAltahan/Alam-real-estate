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

    /** تقرير واتساب المعاينات: هل أُبلغ المالك ببيانات العميل وأُرسلت المتابعة للعميل؟ */
    public function viewings(Request $request): View
    {
        $filters = $request->only('from', 'to', 'agent_id', 'state');

        return view('dashboard.reports.viewings', [
            'report' => $this->viewings->whatsappReport($filters),
            'agents' => User::where('is_agent', true)->orderBy('name')->get(['id', 'name']),
            'states' => ViewingService::WA_STATES,
            'filters' => $filters,
        ]);
    }
}
