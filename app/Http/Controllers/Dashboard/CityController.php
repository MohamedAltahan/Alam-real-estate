<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\City;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** إدارة المدن (المحافظات) — تستخدم صلاحيات المناطق نفسها */
class CityController extends Controller
{
    public function index(Request $request): View
    {
        $cities = City::query()
            ->withCount('areas')
            ->when($request->input('search'), function ($query, $search) {
                $term = '%'.mb_strtolower(trim($search)).'%';
                $query->whereRaw('LOWER(name) LIKE ?', [$term]);
            })
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString();

        return view('dashboard.cities.index', [
            'cities' => $cities,
            'filters' => $request->only('search'),
            'nextSortOrder' => ((int) City::max('sort_order')) + 1,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('areas.create'), 403);

        $data = $this->validated($request);
        $this->ensureUniqueNames($data['name']);
        City::create($data);

        return back()->with('success', 'تمت إضافة المدينة بنجاح.');
    }

    public function update(Request $request, City $city): RedirectResponse
    {
        abort_unless($request->user()->can('areas.edit'), 403);

        $data = $this->validated($request);
        $this->ensureUniqueNames($data['name'], $city);
        $city->update($data);

        return back()->with('success', 'تم تحديث المدينة بنجاح.');
    }

    public function destroy(Request $request, City $city): RedirectResponse
    {
        abort_unless($request->user()->can('areas.delete'), 403);

        if ($city->areas()->exists()) {
            return back()->with('error', 'لا يمكن حذف المدينة لأنها تحتوي على مناطق. انقل المناطق أو عطّل المدينة بدلًا من الحذف.');
        }

        $city->delete();

        return back()->with('success', 'تم حذف المدينة بنجاح.');
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
            'name.ar' => 'اسم المدينة بالعربية',
            'name.en' => 'اسم المدينة بالإنجليزية',
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
    private function ensureUniqueNames(array $names, ?City $current = null): void
    {
        $normalized = collect($names)
            ->map(fn (string $name) => mb_strtolower(trim($name)))
            ->filter();

        $duplicate = City::query()
            ->when($current, fn ($query) => $query->where('id', '<>', $current->id))
            ->get(['id', 'name'])
            ->contains(function (City $city) use ($normalized) {
                return collect($city->getTranslations('name'))
                    ->map(fn (string $name) => mb_strtolower(trim($name)))
                    ->intersect($normalized)
                    ->isNotEmpty();
            });

        if ($duplicate) {
            throw ValidationException::withMessages([
                'name.ar' => 'توجد مدينة مسجلة بالفعل بنفس الاسم.',
            ]);
        }
    }
}
