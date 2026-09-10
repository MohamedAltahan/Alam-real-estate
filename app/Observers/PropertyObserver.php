<?php

namespace App\Observers;

use App\Models\Property;
use App\Models\PropertyStatus;

/**
 * يضبط sold_at عند الانتقال إلى «مباع» ويمسحه عند مغادرتها.
 * أي تعديل آخر على العقار (عنوان، صورة، تقييم) لا يحرّك تاريخ البيع — بخلاف updated_at.
 */
class PropertyObserver
{
    public function saving(Property $property): void
    {
        if (! $property->isDirty('status_id')) {
            return;
        }

        $soldId = PropertyStatus::where('key', 'sold')->value('id');
        $isSold = $soldId !== null && (int) $property->status_id === (int) $soldId;

        $property->sold_at = $isSold ? ($property->sold_at ?? now()) : null;
    }
}
