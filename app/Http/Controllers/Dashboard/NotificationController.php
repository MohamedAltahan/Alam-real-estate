<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\ContactRequest;
use App\Models\User;
use App\Notifications\TaskEvent;
use App\Notifications\ViewingReminder;
use App\Services\ViewingReminderService;
use App\Services\ViewingService;
use App\Services\WhatsApp\WhatsAppService;
use App\Support\NotificationFeed;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class NotificationController extends Controller
{
    /**
     * نقطة الاستطلاع (كل دقيقة من المتصفح): تُرسل تذكيرات المعاينات المستحقة
     * ثم تعيد العدّادات وآخر الإشعارات — بدون الحاجة إلى cron أو اتصال لحظي.
     */
    public function poll(Request $request, ViewingReminderService $reminders, ViewingService $viewings, WhatsAppService $whatsapp): JsonResponse
    {
        $user = $request->user();

        try {
            $reminders->dispatchDue();
        } catch (\Throwable $e) {
            Log::warning('viewing reminders: '.$e->getMessage());
        }

        // عقود الإيجار المنتهية → «إخلاء العقار» — مرة واحدة كل يوم (بديل cron للأمر viewings:vacate-expired)
        try {
            if (Cache::add('viewings:vacate-expired:'.today()->toDateString(), true, now()->addDay())) {
                $viewings->vacateExpired();
            }
        } catch (\Throwable $e) {
            Log::warning('vacate expired viewings: '.$e->getMessage());
        }

        // حالات تسليم رسائل واتساب المعلّقة (بديل الويب هوك) — دورة واحدة كل ~45 ثانية على الأكثر
        try {
            $whatsapp->refreshMessageStatuses();
        } catch (\Throwable $e) {
            Log::warning('whatsapp statuses: '.$e->getMessage());
        }

        $items = $user->notifications()->latest()->take(10)->get()
            ->map(function ($notification) {
                $item = NotificationFeed::databaseItem($notification);

                return [
                    'id' => $item['id'],
                    'kind' => $item['kind'],
                    'title' => $item['title'],
                    'url' => $item['url'],
                    'at' => $item['at']?->toIso8601String(),
                    'human' => $item['at']?->locale('ar')->diffForHumans(),
                    'read' => ! $item['unread'],
                    'overdue' => (bool) ($item['overdue'] ?? false),
                ];
            })
            ->values();

        return response()->json([
            'unread_total' => NotificationFeed::unreadCount($user),
            'viewing_unread' => $user->unreadNotifications()->where('type', ViewingReminder::class)->count(),
            'repeat_beep' => $user->viewingRepeatBeep(),
            'items' => $items,
            // حالة واتساب المكتب للأيقونة (تُسأل البوابة مرة كل 50 ثانية على الأكثر)
            'whatsapp' => $whatsapp->pollStatus(),
        ]);
    }

    /** فتح إشعار: يعلَّم كمقروء ثم يحوّل إلى صفحته (المعاينات لتذكيرات المعاينة) */
    public function open(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        $data = (array) $notification->data;
        $kind = $data['kind'] ?? null;

        // الوجهة تُبنى الآن لا تُقرأ: إشعارات قديمة خُزّنت برابط مطلق لمضيف آخر
        // (منفذ تطوير مثلاً) فكانت تفتح صفحة غير موجودة.
        $url = match (true) {
            $kind === ViewingReminder::KIND => route('dashboard.viewings.index'),
            $kind === TaskEvent::KIND && filled($data['task_id'] ?? null) => route('dashboard.tasks.index', ['task' => $data['task_id']]),
            default => $this->onThisHost($data['url'] ?? null) ?? route('dashboard.clients.index'),
        };

        return redirect($url);
    }

    /** يُبقي المسار والاستعلام فقط من أي رابط مخزّن، فيفتح دائمًا على المضيف الحالي */
    private function onThisHost(?string $stored): ?string
    {
        if (! filled($stored)) {
            return null;
        }

        $path = parse_url($stored, PHP_URL_PATH);

        if (! $path) {
            return $stored;
        }

        $query = parse_url($stored, PHP_URL_QUERY);

        return url($path).($query ? '?'.$query : '');
    }

    /** تعليم إشعارات المستخدم نفسه كمقروءة (لا يحتاج صلاحية تعديل الإشعارات) */
    public function readMine(Request $request): RedirectResponse
    {
        $this->markRead($request->user());

        return back();
    }

    /** قراءة الكل — نفس أثر readMine؛ المسار محفوظ بصلاحياته للتوافق */
    public function readAll(Request $request): RedirectResponse
    {
        $this->markRead($request->user());

        return back();
    }

    /**
     * إشعارات المستخدم + طلبات التواصل تُعلَّم مقروءة له وحده —
     * القراءة حالة شخصية، فلا تُخفي التنبيه عن بقية المستخدمين.
     */
    private function markRead(User $user): void
    {
        $user->unreadNotifications()->update(['read_at' => now()]);

        if ($user->can('contact_requests.view')) {
            ContactRequest::markAllReadFor($user);
        }
    }
}
