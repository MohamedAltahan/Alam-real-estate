<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ClientConversionReport;
use App\Services\ViewingService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function __construct(private ViewingService $viewings, private ClientConversionReport $clients) {}

    /** تقرير تحول المعاينات: المعاينات التي انتهت باهتمام العميل مقابل عدم الاهتمام */
    public function conversion(Request $request): View
    {
        $filters = $request->only('from', 'to', 'agent_id');

        return view('dashboard.reports.conversion', [
            'report' => $this->viewings->conversionReport($filters),
            'agents' => $this->agents(),
            'filters' => $filters,
        ]);
    }

    /** تقرير تحول العملاء: نسبة العملاء الذين ربحهم كل مندوب من كل عملائه المسجّلين في الفترة */
    public function clientsConversion(Request $request): View
    {
        $filters = $request->only('from', 'to', 'agent_id');

        return view('dashboard.reports.clients-conversion', [
            'report' => $this->clients->build($filters),
            'agents' => $this->agents(),
            'filters' => $filters,
        ]);
    }

    private function agents()
    {
        return User::where('is_agent', true)->orderBy('name')->get(['id', 'name']);
    }

    /** تقرير واتساب المعاينات: هل أُبلغ مسؤول العقار ببيانات العميل وأُرسلت له نتيجة المعاينة؟ */
    public function viewings(Request $request): View
    {
        $filters = $request->only('from', 'to', 'agent_id', 'state');

        return view('dashboard.reports.viewings', [
            'report' => $this->viewings->whatsappReport($filters),
            'agents' => $this->agents(),
            'states' => ViewingService::WA_STATES,
            'filters' => $filters,
        ]);
    }
}
