{{--
    مصيدة السبام: حقل مخفي بصرياً + ختم وقت موقَّع.
    الإخفاء بالقصّ (clip) لا بـ display:none، لأن بعض البوتات تتخطى المخفي بالعرض.
    ملاحظة: الإزاحة بـ left:-9999px كانت تمدّ عرض الصفحة في الاتجاه RTL وتكسر التخطيط على الموبايل.
    aria-hidden و tabindex=-1 يبقيان الحقل بعيداً عن قارئات الشاشة ومسار التنقّل بلوحة المفاتيح.
--}}
@use('App\Support\Honeypot')

<div aria-hidden="true" style="position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0 0 0 0);clip-path:inset(50%);white-space:nowrap;border:0">
    <label for="{{ Honeypot::FIELD }}-field">لا تملأ هذا الحقل</label>
    <input type="text" id="{{ Honeypot::FIELD }}-field" name="{{ Honeypot::FIELD }}" value="" tabindex="-1" autocomplete="off">
</div>
<input type="hidden" name="{{ Honeypot::TIME_FIELD }}" value="{{ Honeypot::stamp() }}">
