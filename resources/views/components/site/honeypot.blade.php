{{--
    مصيدة السبام: حقل مخفي بصرياً + ختم وقت موقَّع.
    الإخفاء بالإزاحة خارج الشاشة لا بـ display:none، لأن بعض البوتات تتخطى المخفي بالعرض.
    aria-hidden و tabindex=-1 يبقيان الحقل بعيداً عن قارئات الشاشة ومسار التنقّل بلوحة المفاتيح.
--}}
@use('App\Support\Honeypot')

<div aria-hidden="true" style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden">
    <label for="{{ Honeypot::FIELD }}-field">لا تملأ هذا الحقل</label>
    <input type="text" id="{{ Honeypot::FIELD }}-field" name="{{ Honeypot::FIELD }}" value="" tabindex="-1" autocomplete="off">
</div>
<input type="hidden" name="{{ Honeypot::TIME_FIELD }}" value="{{ Honeypot::stamp() }}">
