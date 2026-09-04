{{-- تبويبات المواقع الإلكترونية / السوشال ميديا (شاشتان تتشاركان الصلاحيات) --}}
@php $current = $current ?? 'website'; @endphp
<div class="inline-flex items-center gap-1 rounded-full bg-gray-100/70 border border-gray-100 p-1 mb-5">
    @foreach (['website' => ['المواقع الإلكترونية', route('dashboard.websites.index')], 'social' => ['السوشال ميديا', route('dashboard.social-channels.index')]] as $key => [$label, $url])
        <a href="{{ $url }}" class="inline-flex items-center gap-2 rounded-full h-9 px-4 text-sm font-semibold whitespace-nowrap transition {{ $current === $key ? 'bg-primary-900 text-white' : 'text-gray-600 hover:bg-white hover:text-ink' }}">{{ $label }}</a>
    @endforeach
</div>
