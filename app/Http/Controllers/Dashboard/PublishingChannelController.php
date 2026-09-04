<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\PublishingChannel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * قنوات النشر: شاشتان (المواقع الإلكترونية · السوشال ميديا) بنفس المنطق،
 * النوع يُستنتج من اسم المسار (websites / social-channels).
 */
class PublishingChannelController extends Controller
{
    public function index(Request $request): View
    {
        $kind = $this->kind($request);

        $channels = PublishingChannel::query()
            ->kind($kind)
            ->withCount('properties')
            ->with('media')
            ->when($request->input('search'), function ($query, $search) {
                $term = '%'.mb_strtolower(trim($search)).'%';
                $query->where(fn ($q) => $q->whereRaw('LOWER(name) LIKE ?', [$term])->orWhereRaw('LOWER(url) LIKE ?', [$term]));
            })
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString();

        return view('dashboard.channels.index', [
            'kind' => $kind,
            'channels' => $channels,
            'filters' => $request->only('search'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('publishing_channels.create'), 403);

        $kind = $this->kind($request);
        $data = $this->validated($request);

        $channel = PublishingChannel::create($data + [
            'kind' => $kind,
            'sort_order' => ((int) PublishingChannel::kind($kind)->max('sort_order')) + 1,
        ]);
        $this->syncIcon($request, $channel);

        return back()->with('success', $kind === PublishingChannel::KIND_WEBSITE ? 'تمت إضافة الموقع بنجاح.' : 'تمت إضافة القناة بنجاح.');
    }

    public function update(Request $request, PublishingChannel $channel): RedirectResponse
    {
        abort_unless($request->user()->can('publishing_channels.edit'), 403);
        abort_unless($channel->kind === $this->kind($request), 404);

        $channel->update($this->validated($request));
        $this->syncIcon($request, $channel);

        return back()->with('success', 'تم حفظ التعديلات.');
    }

    public function destroy(Request $request, PublishingChannel $channel): RedirectResponse
    {
        abort_unless($request->user()->can('publishing_channels.delete'), 403);
        abort_unless($channel->kind === $this->kind($request), 404);

        if ($channel->properties()->exists()) {
            return back()->with('error', 'لا يمكن الحذف لأن هناك عقارات منشورة على هذه القناة. عطّلها بدلًا من الحذف.');
        }

        $channel->delete();

        return back()->with('success', 'تم الحذف بنجاح.');
    }

    /** النوع من اسم المسار: dashboard.websites.* أو dashboard.social-channels.* */
    private function kind(Request $request): string
    {
        return str_contains((string) $request->route()?->getName(), 'social')
            ? PublishingChannel::KIND_SOCIAL
            : PublishingChannel::KIND_WEBSITE;
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'url' => ['nullable', 'url', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
            'icon' => ['nullable', 'file', 'mimetypes:'.implode(',', PublishingChannel::ICON_MIMES), 'extensions:jpg,jpeg,png,webp,svg', 'max:'.PublishingChannel::ICON_MAX_KB],
            'icon_removed' => ['nullable', 'boolean'],
        ], [
            'icon.mimetypes' => 'الأيقونة يجب أن تكون صورة (JPG، PNG، WebP أو SVG).',
            'icon.extensions' => 'الأيقونة يجب أن تكون صورة (JPG، PNG، WebP أو SVG).',
        ], [
            'name' => 'الاسم', 'url' => 'الرابط', 'icon' => 'الأيقونة',
        ]);

        return [
            'name' => trim($data['name']),
            'url' => filled($data['url'] ?? null) ? trim($data['url']) : null,
            'is_active' => $request->boolean('is_active', true),
        ];
    }

    private function syncIcon(Request $request, PublishingChannel $channel): void
    {
        if ($request->boolean('icon_removed')) {
            $channel->clearMediaCollection('icon');
        }

        if ($request->hasFile('icon')) {
            $channel->clearMediaCollection('icon');
            $channel->addMediaFromRequest('icon')->toMediaCollection('icon');
        }
    }
}
