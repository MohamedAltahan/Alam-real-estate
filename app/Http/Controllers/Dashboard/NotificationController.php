<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\ContactRequest;
use App\Notifications\ViewingReminder;
use App\Services\ViewingReminderService;
use App\Services\WhatsApp\WhatsAppService;
use App\Support\NotificationFeed;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NotificationController extends Controller
{
    /**
     * نقطة الاستطلاع (كل دقيقة من المتصفح): تُرسل تذكيرات المعاينات المستحقة
     * ثم تعيد العدّادات وآخر الإشعارات — بدون الحاجة إلى cron أو اتصال لحظي.
     */
    public function poll(Request $request, ViewingReminderService $reminders, WhatsAppService $whatsapp): JsonResponse
    {
        $user = $request->user();

        try {
            $reminders->dispatchDue();
        } catch (\Throwable $e) {
            Log::warning('viewing reminders: '.$e->getMessage());
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

        // تذكيرات المعاينات القديمة كانت تحمل رابط صفحة العميل
        $url = ($data['kind'] ?? null) === ViewingReminder::KIND
            ? route('dashboard.viewings.index')
            : ($data['url'] ?? route('dashboard.clients.index'));

        return redirect((string) $url);
    }

    /** تعليم إشعارات المستخدم نفسه كمقروءة (لا يحتاج صلاحية طلبات التواصل) */
    public function readMine(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back();
    }

    /** قراءة الكل: طلبات التواصل غير المقروءة + إشعارات المستخدم */
    public function readAll(Request $request): RedirectResponse
    {
        ContactRequest::unread()->update(['is_read' => true]);
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back();
    }
}
