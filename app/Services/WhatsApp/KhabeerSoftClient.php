<?php

namespace App\Services\WhatsApp;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * عميل HTTP لبوابة KhabeerSoft (واتساب): الجلسات والـ QR وإرسال النصوص.
 * المفتاح يُقرأ من config/services.php ولا يصل للمتصفح أبداً.
 */
class KhabeerSoftClient
{
    private string $baseUrl;

    private string $apiKey;

    public function __construct(?string $baseUrl = null, ?string $apiKey = null)
    {
        $this->baseUrl = rtrim($baseUrl ?? (string) config('services.khabeersoft.base_url'), '/');
        $this->apiKey = $apiKey ?? (string) config('services.khabeersoft.api_key');
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    /** @return array<string, mixed> */
    public function createInstance(string $name): array
    {
        return $this->json($this->http()->post('/instances', ['name' => $name]));
    }

    /** @return array<int, array<string, mixed>> */
    public function listInstances(): array
    {
        $body = $this->json($this->http()->get('/instances'));
        $rows = $body['data'] ?? $body;

        return is_array($rows) && array_is_list($rows) ? $rows : [];
    }

    /** @return array<string, mixed> */
    public function instance(string $id): array
    {
        return $this->unwrap($this->json($this->http()->get('/instances/'.$id)));
    }

    /** رمز QR للربط: {status: waiting_scan, qr_base64} أو {status: connected, phone} */
    public function qr(string $id): array
    {
        return $this->unwrap($this->json($this->http()->get('/instances/'.$id.'/qr')));
    }

    public function deleteInstance(string $id): void
    {
        $this->json($this->http()->delete('/instances/'.$id));
    }

    /**
     * إرسال نص: instance_id عدد صحيح، والرقم بصيغة دولية أرقاماً فقط بلا + أو صفر (96555112233).
     *
     * @return array{id?:int|string, status?:string}
     */
    public function sendText(int $instanceId, string $to, string $text): array
    {
        return $this->unwrap($this->json($this->http()->post('/messages/send', [
            'instance_id' => $instanceId,
            'to' => preg_replace('/\D+/', '', $to),
            'type' => 'text',
            'text' => $text,
        ])));
    }

    /** نص الخطأ من رد البوابة (error / message) أو من الاستثناء نفسه */
    public static function errorMessage(\Throwable $e): string
    {
        if ($e instanceof RequestException) {
            $body = $e->response->json();
            $message = $body['error'] ?? $body['message'] ?? null;

            return is_string($message) && $message !== '' ? $message : 'HTTP '.$e->response->status();
        }

        return $e->getMessage() ?: 'تعذّر الاتصال بالبوابة';
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->withHeaders(['x-api-key' => $this->apiKey])
            ->acceptJson()
            ->timeout(12);
    }

    /** @return array<string, mixed> */
    private function json(Response $response): array
    {
        $response->throw();

        return (array) $response->json();
    }

    /** بعض الردود ملفوفة في data */
    private function unwrap(array $payload): array
    {
        return isset($payload['data']) && is_array($payload['data']) ? $payload['data'] : $payload;
    }
}
