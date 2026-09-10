<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientStage;
use App\Models\ClientType;
use App\Models\ContactRequest;
use App\Models\MarketingSource;
use App\Models\RequestType;
use App\Models\User;
use App\Services\ClientService;
use App\Services\ContactRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContactRequestController extends Controller
{
    public function __construct(private ContactRequestService $service, private ClientService $clients) {}

    public function index(Request $request): View
    {
        $requests = ContactRequest::query()
            ->with(['requestType', 'property', 'handledBy', 'convertedClient'])
            ->withReadBy($request->user())
            ->when($request->type_id, fn ($q, $v) => $q->where('request_type_id', $v))
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('dashboard.requests.index', [
            'requests' => $requests,
            'types' => RequestType::where('is_active', true)->get(),
            'filters' => $request->only('type_id', 'status'),
            'unreadCount' => ContactRequest::unreadFor($request->user())->count(),
            'tabCounts' => $this->tabCounts(),
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

    /** تبويب «الطلبات المميزة»: العملاء المعلَّمون «طلب مميز» من فورم العميل */
    public function featured(Request $request): View
    {
        $filters = $request->only(ClientService::FEATURED_FILTER_KEYS);

        return view('dashboard.requests.featured', [
            'clients' => $this->clients->paginateFeatured($filters),
            'stages' => $this->clients->stages(),
            'agents' => User::where('is_agent', true)->orderBy('name')->get(['id', 'name']),
            'filters' => $filters,
            'tabCounts' => $this->tabCounts(),
        ]);
    }

    /** أعداد التبويبين (صندوق الوارد · الطلبات المميزة) بلا فلاتر */
    private function tabCounts(): array
    {
        return [
            'inbox' => ContactRequest::count(),
            'featured' => Client::featured()->count(),
        ];
    }

    public function markContacted(Request $request, ContactRequest $contactRequest): RedirectResponse
    {
        abort_unless($request->user()->can('contact_requests.edit'), 403);

        $contactRequest->markContacted($request->user()->id);

        return back()->with('success', 'تم تعليم الطلب كمتواصل معه.');
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
