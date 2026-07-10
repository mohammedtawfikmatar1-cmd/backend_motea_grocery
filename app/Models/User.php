<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * الحقول المسموح بإدخالها جماعياً
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'birth_date',
        'password',
        'is_approved',
        'email_verified_at',
        'phone_verified_at',
    ];

    /**
     * الحقول التي لا تظهر عند تحويل الموديل إلى JSON
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * تحويل أنواع البيانات
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'birth_date' => 'date',
            'is_approved' => 'boolean',
            'password' => 'hashed',
        ];
    }

    /**
     * المستخدم يمتلك عدة أكواد تحقق
     */
    public function verificationCodes(): HasMany
    {
        return $this->hasMany(VerificationCode::class);
    }
}
