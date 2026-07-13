<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginUserRequest;
use App\Http\Requests\Auth\LogoutRequest;
use App\Http\Requests\Auth\RegisterUserRequest;
use App\Http\Requests\Auth\ResendVerificationCodeRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\VerifyResetPasswordCodeRequest;
use App\Http\Requests\Auth\VerifyEmailRequest;
use App\Http\Resources\AuthResource;
use App\Http\Resources\ResetPasswordTokenResource;
use App\Models\User;
use App\Services\AuthService;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Throwable;

class AuthController extends Controller
{
    /**
     * Create a new authentication controller instance.
     */
    public function __construct(
        private readonly AuthService $authService,
    ) {}

    /**
     * Register a new user.
     */
    public function register(RegisterUserRequest $request): JsonResponse
    {
        return ApiResponse::created(
            new AuthResource($this->authService->register($request->validated())),
            __('messages.email_registration_success')
        );
    }

    /**
     * Authenticate a user.
     */
    public function login(LoginUserRequest $request): JsonResponse
    {
        return  ApiResponse::success(
            new AuthResource($this->authService->login($request->validated())),
            __('messages.login_success')
        );
    }

    /**
     * Revoke the current authenticated user's access token.
     */
    public function logout(LogoutRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->authService->logout($user);
        return ApiResponse::success(
            null,
            __('messages.logout_success')
        );
    }

    /**
     * Send a password reset verification code.
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        return  ApiResponse::success(
            new AuthResource($this->authService->forgotPassword($request->validated())),
            __('messages.verification_code_sent')
        );
    }
    public function verifyResetPasswordCode(VerifyResetPasswordCodeRequest $request): JsonResponse
    {
        return ApiResponse::success(
            new ResetPasswordTokenResource(
                $this->authService->verifyResetPasswordCode($request->validated())
            ),
            __('messages.verification_success')
        );
    }

    /**
     * Reset a user's password.
     */
    public function resetPassword(ResetPasswordRequest $request)
    {
        $result = $this->authService->resetPassword($request->validated());
        return ApiResponse::success(
            null,
            __('messages.password_reset_success'),
            
        );
    }

    /**
     * Verify a user's email address.
     */
    public function verifyresetPassword(VerifyEmailRequest $request): JsonResponse
    {
        return ApiResponse::success(
            new AuthResource($this->authService->verifyEmail($request->validated())),
            __('messages.verification_success')
        );
    }
    public function verifyEmail(VerifyEmailRequest $request): JsonResponse
    {
        return ApiResponse::success(
            new AuthResource($this->authService->verifyEmail($request->validated())),
            __('messages.verification_success')
        );
    }

    /**
     * Resend the user's email verification code.
     */
    public function resendVerificationCode(ResendVerificationCodeRequest $request): JsonResponse
    {
        return ApiResponse::success(
            new AuthResource($this->authService->resendVerificationCode($request->validated())),
            __('messages.verification_code_sent')
        );
    }
}
