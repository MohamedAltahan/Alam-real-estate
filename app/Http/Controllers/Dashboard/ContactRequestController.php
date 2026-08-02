<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\ClientStage;
use App\Models\ClientType;
use App\Models\ContactRequest;
use App\Models\MarketingSource;
use App\Models\RequestType;
use App\Models\User;
use App\Services\ContactRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContactRequestController extends Controller
{
    public function __construct(private ContactRequestService $service) {}

    public function index(Request $request): View
    {
        $requests = ContactRequest::query()
            ->with(['requestType', 'property', 'handledBy', 'convertedClient'])
            ->when($request->type_id, fn ($q, $v) => $q->where('request_type_id', $v))
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('dashboard.requests.index', [
            'requests' => $requests,
            'types' => RequestType::where('is_active', true)->get(),
            'filters' => $request->only('type_id', 'status'),
            'unreadCount' => ContactRequest::where('is_read', false)->count(),
            // بيانات مودال التحويل لعميل
            'agents' => User::where('is_agent', true)->orderBy('name')->get(['id', 'name']),
            'sources' => MarketingSource::orderBy('name')->get(['id', 'name']),
            'stages' => ClientStage::where('is_active', true)->orderBy('sort_order')->get(),
            'clientTypes' => ClientType::where('is_active', true)->get(),
            // عميل قائم بنفس الهاتف/البريد — تحذير قبل إنشاء تكرار
            'duplicates' => $requests->getCollection()
                ->reject->isConverted()
                ->mapWithKeys(fn (ContactRequest $r) => [$r->id => $this->service->findDuplicate($r)])
                ->all(),
        ]);
    }

    public function markContacted(Request $request, ContactRequest $contactRequest): RedirectResponse
    {
        abort_unless($request->user()->can('contact_requests.edit'), 403);

        $contactRequest->markContacted($request->user()->id);

        return back()->with('success', 'تم تعليم الطلب كمتواصل معه.');
    }

    public function markRead(Request $request, ContactRequest $contactRequest): RedirectResponse
    {
        abort_unless($request->user()->can('contact_requests.view'), 403);

        $contactRequest->is_read = true;
        $contactRequest->save();

        return back();
    }

    /** تحويل الطلب إلى عميل في الـ CRM */
    public function convert(Request $request, ContactRequest $contactRequest): RedirectResponse
    {
        abort_unless($request->user()->can('clients.create'), 403);

        if ($contactRequest->isConverted()) {
            return back()->with('success', 'هذا الطلب محوَّل بالفعل إلى عميل.');
        }

        $data = $request->validate([
            'existing_client_id' => ['nullable', 'exists:clients,id'],
            'agent_id' => ['nullable', 'exists:users,id'],
            'source_id' => ['nullable', 'exists:marketing_sources,id'],
            'stage_id' => ['nullable', 'exists:client_stages,id'],
            'type_id' => ['nullable', 'exists:client_types,id'],
            'notes' => ['nullable', 'string'],
        ]);

        $client = $this->service->convertToClient($contactRequest, $data, $request->user()->id);

        return redirect()
            ->route('dashboard.clients.show', $client)
            ->with('success', 'تم تحويل الطلب إلى عميل في الـ CRM.');
    }

    public function destroy(Request $request, ContactRequest $contactRequest): RedirectResponse
    {
        abort_unless($request->user()->can('contact_requests.delete'), 403);
        $contactRequest->delete();

        return back()->with('success', 'تم حذف الطلب.');
    }
}
