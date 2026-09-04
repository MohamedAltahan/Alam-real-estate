<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\LogInteractionRequest;
use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\UpdateClientRequest;
use App\Models\Area;
use App\Models\City;
use App\Models\Client;
use App\Models\Property;
use App\Models\UnitType;
use App\Models\User;
use App\Services\ClientService;
use App\Support\ClientAuditPresenter;
use App\Support\ClientFields;
use App\Support\ClientFormData;
use App\Support\PropertyLookup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClientController extends Controller
{
    public function __construct(private ClientService $clients) {}

    public function index(Request $request): View
    {
        $filters = $request->only(ClientService::FILTER_KEYS);

        return view('dashboard.clients.index', [
            'clients' => $this->clients->paginate($filters),
            'stageCounts' => $this->clients->stageCounts($filters),
            'stages' => $this->clients->stages(),
            'agents' => $this->agents(),
            'cities' => City::where('is_active', true)->orderBy('sort_order')->orderBy('id')->get(),
            'areas' => Area::where('is_active', true)->orderBy('sort_order')->orderBy('id')->get(['id', 'name', 'city_id']),
            'unitTypes' => UnitType::where('is_active', true)->orderBy('sort_order')->get(),
            'nationalities' => Client::query()->whereNotNull('nationality')->distinct()->orderBy('nationality')->pluck('nationality'),
            'filters' => $filters,
            'form' => ClientFormData::for(null),
        ]);
    }

    public function store(StoreClientRequest $request): RedirectResponse
    {
        $this->clients->create($request->validated(), $request);

        return back()->with('success', 'تم إضافة العميل بنجاح.');
    }

    public function show(Client $client): View
    {
        $client = $this->clients->load($client);

        return view('dashboard.clients.show', [
            'client' => $client,
            'stages' => $this->clients->stages(),
            'agents' => $this->agents(),
            'auditLogs' => ClientAuditPresenter::present($client->auditLogs),
            'form' => ClientFormData::for($client),
            'linkable' => Property::with(['status', 'area', 'unitType', 'clients', 'media'])
                ->latest()->take(100)->get(),
        ]);
    }

    public function update(UpdateClientRequest $request, Client $client): RedirectResponse
    {
        $this->clients->update($client, $request->validated(), $request);

        return back()->with('success', 'تم تحديث بيانات العميل.');
    }

    public function destroy(Client $client): RedirectResponse
    {
        abort_unless(auth()->user()->can('clients.delete'), 403);

        $this->clients->delete($client);

        return redirect()
            ->route('dashboard.clients.index')
            ->with('success', 'تم حذف العميل.');
    }

    public function logInteraction(LogInteractionRequest $request, Client $client): RedirectResponse
    {
        $this->clients->logInteraction($client, $request->validated());

        return back()->with('success', 'تم تسجيل التواصل وتحديث الحالة.');
    }

    public function attachProperty(Request $request, Client $client): RedirectResponse
    {
        abort_unless(auth()->user()->can('clients.edit'), 403);

        $data = $request->validate([
            'property_id' => ['required', 'exists:properties,id'],
            'relation' => ['nullable', 'in:interested,viewed'],
            'notes' => ['nullable', 'string'],
        ]);

        $this->clients->attachProperty($client, (int) $data['property_id'], $data['relation'] ?? null, $data['notes'] ?? null);

        return back()->with('success', 'تم ربط العقار بالعميل.');
    }

    public function detachProperty(Client $client, Property $property): RedirectResponse
    {
        abort_unless(auth()->user()->can('clients.edit'), 403);

        $this->clients->detachProperty($client, $property->id);

        return back()->with('success', 'تم إلغاء ربط العقار.');
    }

    /** بحث العقارات لحقل المعاينة (بالرقم المرجعي أو العنوان) — JSON لأعلى 20 نتيجة */
    public function propertyLookup(Request $request): JsonResponse
    {
        return response()->json(PropertyLookup::search((string) $request->query('q', '')));
    }

    private function agents()
    {
        return User::where('is_agent', true)->orderBy('name')->get(['id', 'name']);
    }

    /** يُستخدم في الواجهة لتسميات القيم الثابتة */
    public static function labels(): array
    {
        return [
            'contact' => ClientFields::CONTACT_METHODS,
            'social' => ClientFields::SOCIAL_STATUSES,
            'outcomes' => ClientFields::OUTCOMES,
        ];
    }
}
