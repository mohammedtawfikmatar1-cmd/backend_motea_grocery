<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginUserRequest;
use App\Http\Requests\Auth\LogoutRequest;
use App\Http\Requests\Auth\RegisterUserRequest;
use App\Http\Requests\Auth\ResendVerificationCodeRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\VerifyEmailRequest;
use App\Http\Resources\AuthResource;
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
        return $this->handleApi(
            fn (): JsonResponse => ApiResponse::created(
                new AuthResource($this->authService->register($request->validated())),
                __('messages.register_success')
            )
        );
    }

    /**
     * Authenticate a user.
     */
    public function login(LoginUserRequest $request): JsonResponse
    {
        return $this->handleApi(
            fn (): JsonResponse => ApiResponse::success(
                new AuthResource($this->authService->login($request->validated())),
                __('messages.login_success')
            )
        );
    }

    /**
     * Revoke the current authenticated user's access token.
     */
    public function logout(LogoutRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return $this->handleApi(
            function () use ($user): JsonResponse {
                $this->authService->logout($user);

                return ApiResponse::success(
                    null,
                    __('messages.logout_success')
                );
            }
        );
    }

    /**
     * Send a password reset verification code.
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        return $this->handleApi(
            fn (): JsonResponse => ApiResponse::success(
                new AuthResource($this->authService->forgotPassword($request->validated())),
                __('messages.verification_code_sent')
            )
        );
    }

    /**
     * Reset a user's password.
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        return $this->handleApi(
            function () use ($request): JsonResponse {
                $this->authService->resetPassword($request->validated());

                return ApiResponse::success(
                    null,
                    __('messages.password_reset_success')
                );
            }
        );
    }

    /**
     * Verify a user's email address.
     */
    public function verifyEmail(VerifyEmailRequest $request): JsonResponse
    {
        return $this->handleApi(
            fn (): JsonResponse => ApiResponse::success(
                new AuthResource($this->authService->verifyEmail($request->validated())),
                __('messages.verification_success')
            )
        );
    }

    /**
     * Resend the user's email verification code.
     */
    public function resendVerificationCode(ResendVerificationCodeRequest $request): JsonResponse
    {
        return $this->handleApi(
            fn (): JsonResponse => ApiResponse::success(
                new AuthResource($this->authService->resendVerificationCode($request->validated())),
                __('messages.verification_code_sent')
            )
        );
    }

    /**
     * Execute an API action and convert service exceptions into unified API responses.
     *
     * @param  Closure(): JsonResponse  $action
     */
    private function handleApi(Closure $action): JsonResponse
    {
        try {
            return $action();
        } catch (ValidationException $exception) {
            return ApiResponse::validation($exception->errors());
        } catch (AuthenticationException $exception) {
            return ApiResponse::unauthenticated($exception->getMessage());
        } catch (AuthorizationException $exception) {
            return ApiResponse::forbidden($exception->getMessage() ?: null);
        } catch (InvalidArgumentException $exception) {
            report($exception);

            return ApiResponse::serverError(
                app()->hasDebugModeEnabled() ? $exception->getMessage() : null
            );
        } catch (Throwable $exception) {
            report($exception);

            return ApiResponse::serverError(
                app()->hasDebugModeEnabled() ? $exception->getMessage() : null
            );
        }
    }
}
