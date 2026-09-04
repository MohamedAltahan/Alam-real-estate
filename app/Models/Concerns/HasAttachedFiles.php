<?php

namespace App\Models\Concerns;

use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * ملفات مرفقة بسجل واحد (صور · PDF · Word · Excel) — الموديل يعلن const FILES باسم المجموعة.
 * يستخدمه: مالك العقار · العميل · المهمة.
 */
trait HasAttachedFiles
{
    use InteractsWithMedia;

    public const FILE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx'];

    public const MAX_FILE_KB = 15360;

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(static::FILES);
    }

    /** الملفات بصيغة جاهزة للواجهة (Alpine) */
    public function filePayload(): array
    {
        return $this->getMedia(static::FILES)->map(fn (Media $media) => [
            'id' => $media->id,
            'name' => $media->file_name,
            'size' => (int) $media->size,
            'url' => $media->getUrl(),
            'ext' => strtolower(pathinfo($media->file_name, PATHINFO_EXTENSION)),
        ])->values()->all();
    }
}
