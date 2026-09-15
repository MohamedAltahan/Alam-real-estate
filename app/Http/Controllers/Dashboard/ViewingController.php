<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\City;
use App\Models\ClientViewing;
use App\Models\UnitType;
use App\Models\User;
use App\Services\ViewingService;
use App\Services\WhatsApp\WhatsAppService;
use App\Support\ClientFields;
use App\Support\WhatsAppTemplates;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** صفحة المعاينات: كل مواعيد المعاينات مع فلاتر التاريخ ومندوب المبيعات والنتيجة */
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
            'cities' => City::where('is_active', true)->orderBy('sort_order')->orderBy('id')->get(['id', 'name']),
            'areas' => Area::where('is_active', true)->orderBy('sort_order')->orderBy('id')->get(['id', 'name', 'city_id']),
            'unitTypes' => UnitType::where('is_active', true)->orderBy('sort_order')->get(['id', 'name']),
            'waStates' => ViewingService::WA_STATES,
            'filters' => $filters,
        ]);
    }

    /** تعديل ملاحظة المعاينة من الجداول دون الدخول إليها (JSON للنافذة، أو رجوع مع رسالة) */
    public function updateNotes(Request $request, ClientViewing $viewing): JsonResponse|RedirectResponse
    {
        abort_unless($request->user()->can('clients.edit'), 403);

        $data = $request->validate(['notes' => ['nullable', 'string', 'max:2000']], [], ['notes' => 'الملاحظة']);

        $viewing = $this->viewings->updateNotes($viewing, $data['notes'] ?? null);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'notes' => $viewing->notes]);
        }

        return back()->with('success', 'تم حفظ ملاحظة المعاينة.');
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

    /** إرسال رسالة معاينة عبر واتساب: تفاصيل العميل لأحد أرقام المالك، أو المتابعة للعميل */
    public function sendWhatsApp(Request $request, ClientViewing $viewing, WhatsAppService $whatsapp): RedirectResponse
    {
        abort_unless($request->user()->can('clients.edit'), 403);

        $data = $request->validate([
            'kind' => ['required', Rule::in(array_keys(WhatsAppTemplates::KINDS))],
            'to' => ['required', 'string', 'max:40'],
            'body' => ['required', 'string', 'max:4000'],
        ], [], ['kind' => 'نوع الرسالة', 'to' => 'المستلم', 'body' => 'نص الرسالة']);

        $viewing->load(['client.agent', 'property.contacts', 'property.agent', 'property.area']);

        $message = $whatsapp->send($viewing, $data['kind'], $data['to'], str_replace("\r\n", "\n", $data['body']), $request->user());

        return $message->succeeded()
            ? back()->with('success', 'أُرسلت الرسالة عبر واتساب إلى '.$message->to_label.'.')
            : back()->with('error', 'لم تُرسل الرسالة: '.$message->error);
    }
}
