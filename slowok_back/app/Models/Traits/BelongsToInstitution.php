<?php

namespace App\Models\Traits;

use App\Models\Institution;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToInstitution
{
    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class, 'institution_id', 'institution_id');
    }

    /**
     * 기관 범위 필터링 스코프
     * institution_id가 null이면 전체(MASTER용), 있으면 해당 기관 + 공용(NULL) 데이터
     */
    public function scopeForInstitution(Builder $query, ?int $institutionId): Builder
    {
        if ($institutionId === null) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($institutionId) {
            $q->where('institution_id', $institutionId)
              ->orWhereNull('institution_id');
        });
    }

    /**
     * 해당 기관 소유 데이터만 (공용 제외)
     */
    public function scopeOwnedByInstitution(Builder $query, ?int $institutionId): Builder
    {
        if ($institutionId === null) {
            return $query;
        }

        return $query->where('institution_id', $institutionId);
    }
}
