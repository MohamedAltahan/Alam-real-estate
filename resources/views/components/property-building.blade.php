@props(['property'])
{{-- « — مبنى: برج السالمية · المالك: سالم» بجانب الرقم المرجعي (لوحة التحكم فقط — اسم المالك لا يظهر في الموقع) --}}
@if ($property?->buildingLabel())<span {{ $attributes->merge(['class' => 'text-xs font-normal text-gray-500']) }}> — {{ $property->buildingLabel() }}</span>@endif
