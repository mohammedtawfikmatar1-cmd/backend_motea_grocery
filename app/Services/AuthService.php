<?php

namespace App\Services;

use Illuminate\Support\Str;
use App\Enums\VerificationType;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;
use Random\RandomException;

class AuthService
{
    /**
     * Create a new authentication service instance.
     */
    public function __construct(
        private readonly VerificationCodeService $verificationCodes,
        private readonly VerificationNotificationService $verificationNotifications,
        private readonly DatabaseManager $database,
        private readonly Hasher $hasher,
        private readonly ConfigRepository $config,
        private readonly Translator $translator,
    ) {}

    /**
     * Register a new user and send the email verification code.
     *
     * @param  array<string, mixed>  $data
     * @return array{user: User, verification: array{type: string, expires_at: mixed}}
     *
     * @throws RandomException
     * @throws ValidationException
     */
    public function register(array $data): array
    {
        [$user, $verificationCode] = $this->database->transaction(function () use ($data): array {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'birth_date' => $data['birth_date'],
                'password' => $this->hasher->make((string) $data['password']),
            ]);

            $verificationCode = $this->verificationCodes->create($user, VerificationType::EMAIL);

            return [$user, $verificationCode];
        });

        $this->verificationNotifications->send($user, $verificationCode);

        return [
            'user' => $user->fresh(),
            'verification' => [
                'type' => $verificationCode->type->value,
                'expires_at' => $verificationCode->expires_at,
            ],
        ];
    }

    /**
     * Authenticate a user and issue a Sanctum personal access token.
     *
     * @param  array{email: string, password: string}  $data
     * @return array{user: User, access_token: string, token_type: string}
     *
     * @throws AuthenticationException
     */
    public function login(array $data): array
    {
        $user = $this->findUserByEmail((string) $data['email']);

        if (! $user instanceof User || ! $this->hasher->check((string) $data['password'], $user->password)) {
            throw new AuthenticationException($this->translator->get('auth.failed'));
        }

        $token = $this->createAccessToken($user);

        return [
            'user' => $user,
            'access_token' => $token->plainTextToken,
            'token_type' => 'Bearer',
        ];
    }

    /**
     * Revoke the current Sanctum access token for the authenticated user.
     */
    public function logout(User $user): void
    {
        $currentToken = $user->currentAccessToken();

        if ($currentToken !== null && method_exists($currentToken, 'delete')) {
            $currentToken->delete();
        }
    }

    /**
     * Send a password reset verification code to the user's email.
     *
     * @param  array{email: string}  $data
     * @return array{sent: bool}
     *
     * @throws RandomException
     * @throws ValidationException
     */
    public function forgotPassword(array $data): array
    {
        $user = $this->findUserByEmail((string) $data['email']);

        if (! $user instanceof User) {
            return ['sent' => true];
        }

        $verificationCode = $this->verificationCodes->create($user, VerificationType::PASSWORD_RESET);

        $this->verificationNotifications->send($user, $verificationCode);

        return ['sent' => true];
    }

    /**
     * Reset a user's password after validating the password reset code.
     *
     * @param  array{email: string, code: string, password: string}  $data
     *
     * @throws ValidationException
     */
    public function resetPassword(array $data): array
    {
        $accessToken = PersonalAccessToken::findToken(
            (string) $data['reset_token']
        );

        if (
            ! $accessToken ||
            ! $accessToken->can('password:reset')
        ) {
            throw ValidationException::withMessages([
                'reset_token' => __('messages.invalid_reset_token'),
            ]);
        }

        if (
            $accessToken->expires_at &&
            $accessToken->expires_at->isPast()
        ) {
            $accessToken->delete();

            throw ValidationException::withMessages([
                'reset_token' => __('messages.reset_token_expired'),
            ]);
        }

        /** @var User $user */
        $user = $accessToken->tokenable;

        return $this->database->transaction(function () use ($user, $accessToken, $data): array {

            // تحديث كلمة المرور
            $user->forceFill([
                'password' => $this->hasher->make((string) $data['password']),
            ])->save();

            // تسجيل خروج جميع الأجهزة
            $user->tokens()->delete();

            // حذف أكواد التحقق
            $this->verificationCodes->invalidate(
                $user,
                VerificationType::PASSWORD_RESET
            );

            // حذف Token إعادة التعيين
            $accessToken->delete();

            // إنشاء Access Token جديد للمستخدم
            $token = $this->createAccessToken($user);

            return [
                'user' => $user->fresh(),
                'access_token' => $token->plainTextToken,
                'token_type' => 'Bearer',
            ];
        });
    }
    public function verifyResetPasswordCode(array $data): array
    {
        $user = $this->findUserOrFail((string) $data['email']);

        return $this->database->transaction(function () use ($user, $data): array {

            $this->verificationCodes->verify(
                $user,
                (string) $data['code'],
                VerificationType::PASSWORD_RESET
            );

            // حذف أي Token قديم لإعادة التعيين
            $user->tokens()
                ->where('name', 'password-reset')
                ->delete();

            // إنشاء Token صالح لمدة 5 دقائق
            $token = $user->createToken(
                'password-reset',
                ['password:reset'],
                now()->addMinutes(5)
            );

            return [
                'reset_token' => $token->plainTextToken,
                'expires_in' => 300,
            ];
        });
    }
    /**
     * Verify a user's email address using an email verification code.
     *
     * @param  array{email: string, code: string}  $data
     *
     * @throws ValidationException
     */
    public function verifyEmail(array $data): array
    {
        $user = $this->findUserOrFail((string) $data['email']);

        return $this->database->transaction(function () use ($user, $data): array {
            $this->verificationCodes->verify($user, (string) $data['code'], VerificationType::EMAIL);

            if ($user->email_verified_at === null) {
                $user->Fill([
                    'is_approved' => true,
                    'email_verified_at' => Carbon::now(),
                ])->save();
            }

            $token = $this->createAccessToken($user);

            return [
                'user' => $user,
                'access_token' => $token->plainTextToken,
                'token_type' => 'Bearer',
            ];
        });
    }

    /**
     * Resend an email verification code when no valid active code exists.
     *
     * @param  array{email: string}  $data
     * @return array{sent: bool, verification: array{type: string, expires_at: mixed}}
     *
     * @throws RandomException
     * @throws ValidationException
     */
    public function resendVerificationCode(array $data): array
    {
        $user = $this->findUserOrFail((string) $data['email']);
        $verificationCode = $this->verificationCodes->create($user, VerificationType::EMAIL);

        $this->verificationNotifications->send($user, $verificationCode);

        return [
            'sent' => true,
            'verification' => [
                'type' => $verificationCode->type->value,
                'expires_at' => $verificationCode->expires_at,
            ],
        ];
    }

    /**
     * Find a user by email address.
     */
    private function findUserByEmail(string $email): ?User
    {
        return User::query()
            ->where('email', $email)
            ->first();
    }

    /**
     * Find a user by email address or throw a validation exception.
     *
     * @throws ValidationException
     */
    private function findUserOrFail(string $email): User
    {
        $user = $this->findUserByEmail($email);

        if (! $user instanceof User) {
            throw ValidationException::withMessages([
                'email' => [$this->translator->get('messages.user_not_found')],
            ]);
        }

        return $user;
    }

    /**
     * Create a Sanctum access token for the given user.
     */
    private function createAccessToken(User $user): NewAccessToken
    {
        return $user->createToken((string) $this->config->get('auth.api_token_name'));
    }
}
