<?php

namespace App\Services;

use App\Enums\VerificationType;
use App\Models\User;
use App\Models\VerificationCode;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Random\RandomException;

class VerificationCodeService
{
    /**
     * Create a new verification code service instance.
     */
    public function __construct(
        private readonly DatabaseManager $database,
        private readonly ConfigRepository $config,
        private readonly Translator $translator,
    ) {}

    /**
     * Create a verification code for the given user and type.
     *
     * @throws RandomException
     * @throws ValidationException
     */
    public function create(User $user, VerificationType $type = VerificationType::EMAIL): VerificationCode
    {
        return $this->database->transaction(function () use ($user, $type): VerificationCode {
            if ($this->hasValidCode($user, $type)) {
                throw ValidationException::withMessages([
                    'code' => [$this->translator->get('messages.verification_code_already_sent')],
                ]);
            }

            $this->invalidate($user, $type);

            return VerificationCode::query()->create([
                'user_id' => $user->getKey(),
                'code' => $this->generate(),
                'type' => $type,
                'attempts' => 0,
                'expires_at' => Carbon::now()->addMinutes($this->expiresInMinutes()),
                'verified_at' => null,
            ]);
        });
    }

    /**
     * Verify a code for the given user and type.
     *
     * @throws ValidationException
     */
    public function verify(User $user, string $code, VerificationType $type = VerificationType::EMAIL): VerificationCode
    {
        return $this->database->transaction(function () use ($user, $code, $type): VerificationCode {
            $verificationCode = VerificationCode::query()
                ->whereBelongsTo($user)
                ->where('type', $type->value)
                ->whereNull('verified_at')
                ->latest('created_at')
                ->lockForUpdate()
                ->first();

            if (! $verificationCode instanceof VerificationCode) {
                throw ValidationException::withMessages([
                    'code' => [$this->translator->get('messages.verification_code_not_found')],
                ]);
            }

            if ($verificationCode->expires_at->isPast()) {
                throw ValidationException::withMessages([
                    'code' => [$this->translator->get('messages.verification_expired')],
                ]);
            }

            if ($verificationCode->attempts >= $this->maxAttempts()) {
                throw ValidationException::withMessages([
                    'code' => [$this->translator->get('messages.verification_max_attempts')],
                ]);
            }

            if (! hash_equals($verificationCode->code, $code)) {
                $verificationCode = $this->incrementAttempts($verificationCode);

                throw ValidationException::withMessages([
                    'code' => [
                        $verificationCode->attempts >= $this->maxAttempts()
                            ? $this->translator->get('messages.verification_max_attempts')
                            : $this->translator->get('messages.verification_failed'),
                    ],
                ]);
            }

            return $this->markAsVerified($verificationCode);
        });
    }

    /**
     * Get the latest verification code for the given user and type.
     */
    public function latest(User $user, VerificationType $type = VerificationType::EMAIL): ?VerificationCode
    {
        return VerificationCode::query()
            ->whereBelongsTo($user)
            ->where('type', $type->value)
            ->latest('created_at')
            ->first();
    }

    /**
     * Determine whether the user already has a valid unverified code.
     */
    public function hasValidCode(User $user, VerificationType $type = VerificationType::EMAIL): bool
    {
        return VerificationCode::query()
            ->whereBelongsTo($user)
            ->where('type', $type->value)
            ->whereNull('verified_at')
            ->where('expires_at', '>', Carbon::now())
            ->lockForUpdate()
            ->exists();
    }

    /**
     * Increment the attempts counter for a verification code.
     */
    private function incrementAttempts(VerificationCode $verificationCode): VerificationCode
    {
        $verificationCode->increment('attempts');

        return $verificationCode->refresh();
    }

    /**
     * Mark a verification code as verified.
     */
    private function markAsVerified(VerificationCode $verificationCode): VerificationCode
    {
        $verificationCode->forceFill([
            'verified_at' => Carbon::now(),
        ])->save();

        return $verificationCode->refresh();
    }

    /**
     * Delete all verification codes for the given user and type.
     */
    public function invalidate(User $user, VerificationType $type = VerificationType::EMAIL): int
    {
        return VerificationCode::query()
            ->whereBelongsTo($user)
            ->where('type', $type->value)
            ->delete();
    }

    /**
     * Delete expired verification codes, optionally scoped to a user and type.
     */
    public function deleteExpired(?User $user = null, ?VerificationType $type = null): int
    {
        return VerificationCode::query()
            ->when($user instanceof User, fn ($query) => $query->whereBelongsTo($user))
            ->when($type instanceof VerificationType, fn ($query) => $query->where('type', $type->value))
            ->where('expires_at', '<=', Carbon::now())
            ->delete();
    }

    /**
     * Generate a numeric verification code.
     *
     * @throws RandomException
     */
    private function generate(): string
    {
        $length = $this->codeLength();
        $code = '';

        // Build the code digit-by-digit so leading zeroes remain possible.
        for ($index = 0; $index < $length; $index++) {
            $code .= (string) random_int(0, 9);
        }

        return $code;
    }

    /**
     * Get the configured verification code length.
     */
    private function codeLength(): int
    {
        $length = (int) $this->config->get('verification.code_length');

        if ($length < 1) {
            throw new InvalidArgumentException('verification.code_length must be greater than zero.');
        }

        return $length;
    }

    /**
     * Get the configured expiration window in minutes.
     */
    private function expiresInMinutes(): int
    {
        $minutes = (int) $this->config->get('verification.expires_in_minutes');

        if ($minutes < 1) {
            throw new InvalidArgumentException('verification.expires_in_minutes must be greater than zero.');
        }

        return $minutes;
    }

    /**
     * Get the configured maximum number of verification attempts.
     */
    private function maxAttempts(): int
    {
        $attempts = (int) $this->config->get('verification.max_attempts');

        if ($attempts < 1) {
            throw new InvalidArgumentException('verification.max_attempts must be greater than zero.');
        }

        return $attempts;
    }
}
