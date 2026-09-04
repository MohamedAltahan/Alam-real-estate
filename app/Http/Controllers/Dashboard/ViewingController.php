<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\ClientViewing;
use App\Models\User;
use App\Services\ViewingService;
use App\Support\ClientFields;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** صفحة المعاينات: كل مواعيد المعاينات مع فلاتر التاريخ والمسؤول والنتيجة */
class ViewingController extends Controller
{
    public function __construct(private ViewingService $viewings) {}

    public function index(Request $request): View
    {
        $filters = $request->only(ViewingService::FILTER_KEYS);

        return view('dashboard.viewings.index', [
            'viewings' => $this->viewings->paginate($filters),
            'agents' => User::where('is_agent', true)->orderBy('name')->get(['id', 'name']),
            'outcomes' => ClientFields::OUTCOMES,
            'filters' => $filters,
        ]);
    }

    public function updateOutcome(Request $request, ClientViewing $viewing): RedirectResponse
    {
        abort_unless($request->user()->can('clients.edit'), 403);

        $data = $request->validate([
            'outcome' => ['required', Rule::in(array_keys(ClientFields::OUTCOMES))],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->viewings->updateOutcome($viewing, $data['outcome'], $data['notes'] ?? null);

        return back()->with('success', 'تم تحديث نتيجة المعاينة.');
    }
}
