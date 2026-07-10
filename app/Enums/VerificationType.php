<?php

namespace App\Enums;

enum VerificationType: string
{
    case EMAIL = 'email';

    case PHONE = 'phone';

    case PASSWORD_RESET = 'password_reset';

    case CHANGE_EMAIL = 'change_email';

    case CHANGE_PHONE = 'change_phone';

    public function isEmail(): bool
    {
        return $this === self::EMAIL;
    }

    /**
     * هل هذا النوع خاص بإعادة تعيين كلمة المرور؟
     */
    public function isPasswordReset(): bool
    {
        return $this === self::PASSWORD_RESET;
    }
}
