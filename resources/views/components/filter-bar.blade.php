@props([
    'id',                       // معرّف النموذج — تنضمّ إليه الحقول البعيدة بالخاصية form="..."
    'cols' => 'xl:grid-cols-5', // عدد الأعمدة على الشاشات الكبيرة
    'reset' => null,            // رابط مسح كل الفلاتر (يُطبَّق فوراً عبر live-filters)
])

{{-- شريط فلاتر موحّد: بطاقة بيضاء + شبكة حقول مسمّاة، وتُطبَّق تلقائياً بلا زر --}}
<form method="GET" id="{{ $id }}" data-live-filters
      {{ $attributes->merge(['class' => 'rounded-card bg-white border border-gray-100 shadow-sm p-4 mb-4']) }}>
    <div class="grid grid-cols-2 md:grid-cols-3 {{ $cols }} gap-3 items-end">
        {{ $slot }}
    </div>

    @if ($reset)
        <div class="flex justify-end mt-3">
            <a href="{{ $reset }}" data-filters-reset="{{ $id }}" class="text-sm text-gray-500 hover:text-danger">مسح كل الفلاتر</a>
        </div>
    @endif
</form>
