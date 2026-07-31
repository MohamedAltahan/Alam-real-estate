<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\MarketingSource;
use App\Models\MarketingSourceType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MarketingSourceController extends Controller
{
    public function index(Request $request): View
    {
        $sources = MarketingSource::query()
            ->with('type')
            ->withCount('clients')
            ->when($request->search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('dashboard.sources.index', [
            'sources' => $sources,
            'types' => MarketingSourceType::where('is_active', true)->get(),
            'filters' => $request->only('search'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('marketing_sources.create'), 403);
        MarketingSource::create($this->validated($request));

        return back()->with('success', 'تم إضافة المصدر بنجاح.');
    }

    public function update(Request $request, MarketingSource $source): RedirectResponse
    {
        abort_unless($request->user()->can('marketing_sources.edit'), 403);
        $source->update($this->validated($request));

        return back()->with('success', 'تم تحديث المصدر.');
    }

    public function destroy(Request $request, MarketingSource $source): RedirectResponse
    {
        abort_unless($request->user()->can('marketing_sources.delete'), 403);
        $source->delete();

        return back()->with('success', 'تم حذف المصدر.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type_id' => ['nullable', 'exists:marketing_source_types,id'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', 'in:active,inactive'],
        ], [], ['name' => 'الاسم', 'cost' => 'التكلفة', 'status' => 'الحالة']);
    }
}
