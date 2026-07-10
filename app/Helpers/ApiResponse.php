<?php

namespace App\Helpers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Symfony\Component\HttpFoundation\Response;

class ApiResponse
{
    /**
     * Return a unified successful API response.
     */
    public static function success(
        mixed $data = null,
        ?string $message = null,
        int $status = Response::HTTP_OK
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => self::resolveData($data),
            'errors' => null,
        ], $status);
    }

    /**
     * Return a unified created API response.
     */
    public static function created(mixed $data = null, ?string $message = null): JsonResponse
    {
        return self::success($data, $message, Response::HTTP_CREATED);
    }

    /**
     * Return a unified failed API response.
     *
     * @param  array<string, mixed>|null  $errors
     */
    public static function error(
        ?string $message = null,
        int $status = Response::HTTP_BAD_REQUEST,
        ?array $errors = null,
        mixed $data = null
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => self::resolveData($data),
            'errors' => $errors,
        ], $status);
    }

    /**
     * Return a unified validation error response.
     *
     * @param  array<string, mixed>  $errors
     */
    public static function validation(array $errors, ?string $message = null): JsonResponse
    {
        return self::error(
            message: $message ?? __('messages.validation_failed'),
            status: Response::HTTP_UNPROCESSABLE_ENTITY,
            errors: $errors,
        );
    }

    /**
     * Return a unified unauthenticated response.
     */
    public static function unauthenticated(?string $message = null): JsonResponse
    {
        return self::error(
            message: $message ?? __('auth.unauthorized'),
            status: Response::HTTP_UNAUTHORIZED,
        );
    }

    /**
     * Return a unified forbidden response.
     */
    public static function forbidden(?string $message = null): JsonResponse
    {
        return self::error(
            message: $message ?? __('auth.forbidden'),
            status: Response::HTTP_FORBIDDEN,
        );
    }

    /**
     * Return a unified not found response.
     */
    public static function notFound(?string $message = null): JsonResponse
    {
        return self::error(
            message: $message ?? __('messages.not_found'),
            status: Response::HTTP_NOT_FOUND,
        );
    }

    /**
     * Return a unified method not allowed response.
     */
    public static function methodNotAllowed(?string $message = null): JsonResponse
    {
        return self::error(
            message: $message ?? __('messages.method_not_allowed'),
            status: Response::HTTP_METHOD_NOT_ALLOWED,
        );
    }

    /**
     * Return a unified server error response.
     */
    public static function serverError(?string $message = null): JsonResponse
    {
        return self::error(
            message: $message ?? __('messages.server_error'),
            status: Response::HTTP_INTERNAL_SERVER_ERROR,
        );
    }

    /**
     * Resolve API resources before nesting them in the response envelope.
     */
    private static function resolveData(mixed $data): mixed
    {
        if ($data instanceof JsonResource) {
            return $data->resolve(request());
        }

        return $data;
    }
}
