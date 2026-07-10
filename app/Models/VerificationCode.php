<?php

namespace App\Models;

use App\Enums\VerificationType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VerificationCode extends Model
{
    use HasFactory;

    /**
     * الحقول المسموح بإدخالها جماعياً (Mass Assignment)
     */
    protected $fillable = [
        'user_id',
        'code',
        'type',
        'attempts',
        'expires_at',
        'verified_at',
    ];

    /**
     * تحويل الأعمدة إلى أنواع بيانات PHP
     */
    protected $casts = [
        'type' => VerificationType::class,
        'expires_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    /**
     * كل رمز تحقق ينتمي إلى مستخدم واحد
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope the query to active unverified verification codes.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->whereNull('verified_at')
            ->where('expires_at', '>', now());
    }

    /**
     * Scope the query to a verification type.
     */
    public function scopeType(Builder $query, VerificationType $type): Builder
    {
        return $query->where('type', $type->value);
    }
}
