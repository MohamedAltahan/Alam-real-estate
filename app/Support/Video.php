<?php

namespace App\Support;

/**
 * استخراج معرّف فيديو يوتيوب من أي صيغة رابط يدخلها المستخدم في الداشبورد.
 */
class Video
{
    /** يدعم watch?v= · youtu.be · /embed/ · /shorts/ · /live/ */
    public static function youtubeId(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        $patterns = [
            '~youtube\.com/watch\?(?:.*&)?v=([\w-]{6,})~i',
            '~youtu\.be/([\w-]{6,})~i',
            '~youtube(?:-nocookie)?\.com/embed/([\w-]{6,})~i',
            '~youtube\.com/shorts/([\w-]{6,})~i',
            '~youtube\.com/live/([\w-]{6,})~i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $m)) {
                return $m[1];
            }
        }

        return null;
    }
}
