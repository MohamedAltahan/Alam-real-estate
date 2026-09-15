<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Support\ActivityPresenter;
use App\Support\ActivitySubjects;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** شاشة سجل النشاط: كل إضافة/تعديل/حذف على الوحدات الأساسية مع فلاتر المستخدم والوحدة والتاريخ */
class ActivityLogController extends Controller
{
    public function __construct(private ActivityLogService $activity) {}

    public function index(Request $request): View
    {
        $filters = $request->only(ActivityLogService::FILTER_KEYS);
        $logs = $this->activity->paginate($filters);

        return view('dashboard.activity.index', [
            'logs' => $logs,
            'rows' => ActivityPresenter::present($logs->getCollection()),
            'eventCounts' => $this->activity->eventCounts($filters),
            'users' => User::orderBy('name')->get(['id', 'name']),
            'modules' => ActivitySubjects::MODULES,
            'events' => ActivitySubjects::EVENTS,
            'filters' => $filters,
        ]);
    }
}
