<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\WhatsappMessage;
use App\Models\WhatsappTemplate;
use App\Services\WhatsApp\KhabeerSoftClient;
use App\Services\WhatsApp\WhatsAppService;
use App\Support\WhatsAppTemplates;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** شاشة واتساب: ربط رقم المكتب بالـ QR، قوالب رسائل المعاينات، وسجل الرسائل المرسلة */
class WhatsAppController extends Controller
{
    public const TABS = ['connection', 'templates', 'messages'];

    public function __construct(private WhatsAppService $whatsapp) {}

    public function index(Request $request): View
    {
        $tab = in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : 'connection';

        return view('dashboard.whatsapp.index', [
            'tab' => $tab,
            'instance' => $this->whatsapp->instance(),
            'status' => $this->whatsapp->status(),
            'templates' => WhatsappTemplate::query()->get()->keyBy('key'),
            'kinds' => WhatsAppTemplates::KINDS,
            'placeholders' => WhatsAppTemplates::PLACEHOLDERS,
            'messages' => WhatsappMessage::with(['client', 'viewing.property', 'sender'])
                ->latest('id')->paginate(20, ['*'], 'page')->withQueryString(),
            // رصيد الباقة كما يظهر في لوحة تحكم البوابة (المُرسل والمتبقي)
            'usage' => $this->whatsapp->usage(),
            // رابط الويب هوك الذي يُسجَّل في لوحة البوابة لتحديث حالات الرسائل لحظياً
            'webhook' => [
                'url' => route('webhooks.khabeersoft'),
                'configured' => filled(config('services.khabeersoft.webhook_secret')),
            ],
        ]);
    }

    /** زر «تحديث الحالات» في سجل الرسائل: يسأل البوابة عن الرسائل المعلّقة والرصيد الآن */
    public function refreshStatuses(): RedirectResponse
    {
        $updated = $this->whatsapp->refreshMessageStatuses(force: true);
        $this->whatsapp->usage(fresh: true);

        return redirect()
            ->route('dashboard.whatsapp.index', ['tab' => 'messages'])
            ->with('success', $updated ? "تم تحديث حالة {$updated} رسالة." : 'لا توجد تحديثات جديدة من البوابة.');
    }

    /** إنشاء جلسة في البوابة (أو تبنّي الموجودة) ثم عرض الـ QR */
    public function connect(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('whatsapp.edit'), 403);

        $data = $request->validate(['name' => ['required', 'string', 'max:120']], [], ['name' => 'اسم الجلسة']);

        try {
            $this->whatsapp->connect(trim($data['name']));
        } catch (\Throwable $e) {
            return back()->with('error', 'تعذّر إنشاء الجلسة: '.KhabeerSoftClient::errorMessage($e));
        }

        return redirect()->route('dashboard.whatsapp.index')->with('success', 'أُنشئت الجلسة — امسح رمز QR من واتساب لربط الرقم.');
    }

    /** يُستطلع كل 3 ثوانٍ من لوحة الربط حتى تصبح الحالة «متصل» */
    public function qr(): JsonResponse
    {
        try {
            return response()->json($this->whatsapp->qrState());
        } catch (\Throwable $e) {
            return response()->json(['status' => 'error', 'error' => KhabeerSoftClient::errorMessage($e)], 502);
        }
    }

    public function disconnect(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('whatsapp.edit'), 403);

        $this->whatsapp->disconnect();

        return redirect()->route('dashboard.whatsapp.index')->with('success', 'تم فصل الرقم وحذف الجلسة.');
    }

    public function updateTemplate(Request $request, string $kind): RedirectResponse
    {
        abort_unless($request->user()->can('whatsapp.edit'), 403);
        abort_unless(isset(WhatsAppTemplates::KINDS[$kind]), 404);

        $data = $request->validate(['body' => ['required', 'string', 'max:4000']], [], ['body' => 'نص الرسالة']);

        WhatsappTemplate::updateOrCreate(['key' => $kind], [
            'name' => WhatsAppTemplates::kindLabel($kind),
            'body' => str_replace("\r\n", "\n", trim($data['body'])),
        ]);

        return redirect()->route('dashboard.whatsapp.index', ['tab' => 'templates'])->with('success', 'تم حفظ القالب.');
    }
}
