<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\LogInteractionRequest;
use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\UpdateClientRequest;
use App\Models\Area;
use App\Models\Client;
use App\Models\ClientType;
use App\Models\MarketingSource;
use App\Models\Property;
use App\Models\User;
use App\Services\ClientService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClientController extends Controller
{
    public function __construct(private ClientService $clients) {}

    public function index(Request $request): View
    {
        $filters = $request->only('search', 'stage_id', 'agent_id', 'type_id');

        return view('dashboard.clients.index', [
            'clients' => $this->clients->paginate($filters),
            'stageCounts' => $this->clients->stageCounts($filters),
            'stages' => $this->clients->stages(),
            'agents' => User::where('is_agent', true)->orderBy('name')->get(['id', 'name']),
            'types' => ClientType::where('is_active', true)->get(),
            'areas' => Area::where('is_active', true)->orderBy('sort_order')->get(),
            'sources' => MarketingSource::orderBy('name')->get(['id', 'name']),
            'filters' => $request->only('search', 'stage_id', 'agent_id', 'type_id'),
        ]);
    }

    public function store(StoreClientRequest $request): RedirectResponse
    {
        $this->clients->create($request->validated());

        return back()->with('success', 'تم إضافة العميل بنجاح.');
    }

    public function show(Client $client): View
    {
        return view('dashboard.clients.show', [
            'client' => $this->clients->load($client),
            'stages' => $this->clients->stages(),
            'agents' => User::where('is_agent', true)->orderBy('name')->get(['id', 'name']),
            'types' => ClientType::where('is_active', true)->get(),
            'areas' => Area::where('is_active', true)->orderBy('sort_order')->get(),
            'sources' => MarketingSource::orderBy('name')->get(['id', 'name']),
            'linkable' => Property::latest()->take(50)->get(['id', 'reference_code']),
        ]);
    }

    public function update(UpdateClientRequest $request, Client $client): RedirectResponse
    {
        $this->clients->update($client, $request->validated());

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
            'relation' => ['nullable', 'string', 'max:40'],
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
}
