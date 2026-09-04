<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\UnitType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UnitTypeController extends Controller
{
    public function index(Request $request): View
    {
        $unitTypes = UnitType::query()
            ->withCount(['properties', 'needs'])
            ->when($request->input('search'), function ($query, $search) {
                $term = '%'.mb_strtolower(trim($search)).'%';
                $query->whereRaw('LOWER(name) LIKE ?', [$term]);
            })
            ->when($request->input('category'), fn ($query, $category) => $query->where('category', $category))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString();

        return view('dashboard.unit-types.index', [
            'unitTypes' => $unitTypes,
            'filters' => $request->only('search', 'category'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('unit_types.create'), 403);

        $data = $this->validated($request);
        $this->ensureUniqueNames($data['name']);
        // الترتيب يُضبط تلقائيًا (آخر نوع + 1)
        UnitType::create($data + ['sort_order' => ((int) UnitType::max('sort_order')) + 1]);

        return back()->with('success', 'تمت إضافة نوع العقار بنجاح.');
    }

    public function update(Request $request, UnitType $unitType): RedirectResponse
    {
        abort_unless($request->user()->can('unit_types.edit'), 403);

        $data = $this->validated($request);
        $this->ensureUniqueNames($data['name'], $unitType);
        $unitType->update($data);

        return back()->with('success', 'تم تحديث نوع العقار بنجاح.');
    }

    public function destroy(Request $request, UnitType $unitType): RedirectResponse
    {
        abort_unless($request->user()->can('unit_types.delete'), 403);

        $unitType->loadCount(['properties', 'needs']);

        if ($unitType->properties_count + $unitType->needs_count > 0) {
            return back()->with('error', 'لا يمكن حذف هذا النوع لأنه مستخدم حاليًا. يمكنك تعطيله بدلًا من الحذف.');
        }

        $unitType->delete();

        return back()->with('success', 'تم حذف نوع العقار بنجاح.');
    }

    /** @return array{name: array<string, string>, category: string, is_active: bool} */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'name.ar' => ['required', 'string', 'max:255'],
            'name.en' => ['nullable', 'string', 'max:255'],
            'category' => ['required', Rule::in(array_keys(UnitType::CATEGORIES))],
            'is_active' => ['nullable', 'boolean'],
        ], [], [
            'name.ar' => 'اسم النوع بالعربية',
            'name.en' => 'اسم النوع بالإنجليزية',
            'category' => 'التصنيف',
            'is_active' => 'الحالة',
        ]);

        $names = ['ar' => trim($validated['name']['ar'])];
        if ($english = trim($validated['name']['en'] ?? '')) {
            $names['en'] = $english;
        }

        return [
            'name' => $names,
            'category' => $validated['category'],
            'is_active' => $request->boolean('is_active'),
        ];
    }

    /** @param array<string, string> $names */
    private function ensureUniqueNames(array $names, ?UnitType $current = null): void
    {
        $normalized = collect($names)
            ->map(fn (string $name) => mb_strtolower(trim($name)))
            ->filter();

        $duplicate = UnitType::query()
            ->when($current, fn ($query) => $query->where('id', '<>', $current->id))
            ->get(['id', 'name'])
            ->contains(function (UnitType $unitType) use ($normalized) {
                return collect($unitType->getTranslations('name'))
                    ->map(fn (string $name) => mb_strtolower(trim($name)))
                    ->intersect($normalized)
                    ->isNotEmpty();
            });

        if ($duplicate) {
            throw ValidationException::withMessages([
                'name.ar' => 'يوجد نوع عقار مسجل بالفعل بنفس الاسم.',
            ]);
        }
    }
}
