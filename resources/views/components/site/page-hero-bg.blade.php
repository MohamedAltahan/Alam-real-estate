{{--
| خلفية هيرو الصفحات الفرعية: صورة + طبقة التدرّج الكحلي الرسمية من Figma
| (#0A1046 93% ← #1A2060 82% عند 45% ← #080C37 90%) عبر الكلاس المشترك
| navy-gradient — نفسه المستخدم في كارت "عروض عقارية مميزة" والنافبار عند التمرير.
| يوضع كأول عنصر داخل <section class="relative isolate ... overflow-hidden">.
--}}
<img src="{{ asset('images/page-hero.jpg') }}" alt="" aria-hidden="true"
     class="absolute inset-0 -z-10 w-full h-full object-cover">
<div class="absolute inset-0 -z-10 navy-gradient"></div>
