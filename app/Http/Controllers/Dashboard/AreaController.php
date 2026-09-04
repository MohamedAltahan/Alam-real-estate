<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\PageSection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AreaController extends Controller
{
    public function index(Request $request): View
    {
        $areas = Area::query()
            ->withCount(['properties', 'clients', 'owners'])
            ->when($request->input('search'), function ($query, $search) {
                $term = '%'.mb_strtolower(trim($search)).'%';
                $query->whereRaw('LOWER(name) LIKE ?', [$term]);
            })
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString();

        return view('dashboard.areas.index', [
            'areas' => $areas,
            'filters' => $request->only('search'),
            'nextSortOrder' => ((int) Area::max('sort_order')) + 1,
            'homepageAreaIds' => $this->homepageAreaIds(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('areas.create'), 403);

        $data = $this->validated($request);
        $this->ensureUniqueNames($data['name']);
        Area::create($data);

        return back()->with('success', 'تمت إضافة المنطقة بنجاح.');
    }

    public function update(Request $request, Area $area): RedirectResponse
    {
        abort_unless($request->user()->can('areas.edit'), 403);

        $data = $this->validated($request);
        $this->ensureUniqueNames($data['name'], $area);
        $area->update($data);

        return back()->with('success', 'تم تحديث المنطقة بنجاح.');
    }

    public function destroy(Request $request, Area $area): RedirectResponse
    {
        abort_unless($request->user()->can('areas.delete'), 403);

        $area->loadCount(['properties', 'clients', 'owners']);
        $recordsCount = $area->properties_count + $area->clients_count + $area->owners_count;

        if ($recordsCount > 0 || $this->homepageAreaIds()->contains($area->id)) {
            return back()->with('error', 'لا يمكن حذف هذه المنطقة لأنها مستخدمة حاليًا. يمكنك تعطيلها بدلًا من الحذف.');
        }

        $area->delete();

        return back()->with('success', 'تم حذف المنطقة بنجاح.');
    }

    /** @return array{name: array<string, string>, sort_order: int, is_active: bool} */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'name.ar' => ['required', 'string', 'max:255'],
            'name.en' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999999'],
            'is_active' => ['nullable', 'boolean'],
        ], [], [
            'name.ar' => 'اسم المنطقة بالعربية',
            'name.en' => 'اسم المنطقة بالإنجليزية',
            'sort_order' => 'الترتيب',
            'is_active' => 'الحالة',
        ]);

        $names = ['ar' => trim($validated['name']['ar'])];
        if ($english = trim($validated['name']['en'] ?? '')) {
            $names['en'] = $english;
        }

        return [
            'name' => $names,
            'sort_order' => (int) $validated['sort_order'],
            'is_active' => $request->boolean('is_active'),
        ];
    }

    /** @param array<string, string> $names */
    private function ensureUniqueNames(array $names, ?Area $current = null): void
    {
        $normalized = collect($names)
            ->map(fn (string $name) => mb_strtolower(trim($name)))
            ->filter();

        $duplicate = Area::query()
            ->when($current, fn ($query) => $query->where('id', '<>', $current->id))
            ->get(['id', 'name'])
            ->contains(function (Area $area) use ($normalized) {
                return collect($area->getTranslations('name'))
                    ->map(fn (string $name) => mb_strtolower(trim($name)))
                    ->intersect($normalized)
                    ->isNotEmpty();
            });

        if ($duplicate) {
            throw ValidationException::withMessages([
                'name.ar' => 'توجد منطقة مسجلة بالفعل بنفس الاسم.',
            ]);
        }
    }

    /** @return Collection<int, int> */
    private function homepageAreaIds(): Collection
    {
        return PageSection::query()
            ->where('key', 'areas')
            ->get()
            ->flatMap(function (PageSection $section) {
                return collect($section->getTranslations('content'))
                    ->flatMap(fn ($content) => data_get($content, 'items', []))
                    ->pluck('area_id');
            })
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }
}
