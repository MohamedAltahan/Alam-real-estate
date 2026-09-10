<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ويب هوك بوابة KhabeerSoft: تحديث حالة رسائل واتساب لحظياً (أُرسلت · وصلت · قُرئت · فشلت).
 * يُسجَّل رابطه في لوحة البوابة (الإشعارات) ويُتحقق من توقيع HMAC-SHA256 بالسرّ من .env.
 */
class KhabeerSoftWebhookController extends Controller
{
    public function __invoke(Request $request, WhatsAppService $whatsapp): JsonResponse
    {
        $secret = (string) config('services.khabeersoft.webhook_secret');

        if ($secret === '') {
            return response()->json(['error' => 'webhook secret is not configured'], 503);
        }

        $signature = trim((string) $request->header('x-khabeersoft-signature'));
        $digest = hash_hmac('sha256', $request->getContent(), $secret);

        if (! hash_equals('sha256='.$digest, $signature) && ! hash_equals($digest, $signature)) {
            return response()->json(['error' => 'invalid signature'], 401);
        }

        $handled = $whatsapp->handleWebhook((array) $request->json()->all());

        return response()->json(['ok' => true, 'handled' => $handled]);
    }
}
