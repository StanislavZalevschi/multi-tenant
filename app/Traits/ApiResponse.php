<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

trait ApiResponse
{
    /** ========== Success Responses ========== */

    public function responseSuccess(?string $message = null, mixed $data = null): JsonResponse
    {
        return $this->buildResponse(Response::HTTP_OK, $message ?? __('messages.success'), $data);
    }

    public function responseCreated(?string $message = null, mixed $data = null): JsonResponse
    {
        return $this->buildResponse(Response::HTTP_CREATED, $message ?? __('messages.created'), $data);
    }

    public function responseDeleted(): JsonResponse
    {
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /** ========== Error Responses ========== */

    public function responseBadRequest(mixed $details = null, ?string $message = null): JsonResponse
    {
        return $this->apiError(Response::HTTP_BAD_REQUEST, $message ?? __('messages.bad_request'), $details);
    }

    public function responseUnprocessable(mixed $details = null, ?string $message = null): JsonResponse
    {
        return $this->apiError(Response::HTTP_UNPROCESSABLE_ENTITY, $message ?? __('messages.unprocessable'), $details);
    }

    public function responseNotFound(mixed $details = null, ?string $message = null): JsonResponse
    {
        return $this->apiError(Response::HTTP_NOT_FOUND, $message ?? __('messages.not_found'), $details);
    }

    public function responseUnAuthorized(string $details = null, string $message = null): JsonResponse
    {
        return $this->apiError(
            Response::HTTP_FORBIDDEN,
            $message ?? __('messages.unauthorized'),
            $details ?? __('messages.unauthorized_action')
        );
    }

    public function responseUnAuthenticated(string $details = null, string $message = null): JsonResponse
    {
        return $this->apiError(
            Response::HTTP_UNAUTHORIZED,
            $message ?? __('messages.unauthenticated'),
            $details ?? __('messages.unauthenticated_action')
        );
    }

    public function responseConflictError(string $details = null, string $message = null): JsonResponse
    {
        return $this->apiError(
            Response::HTTP_CONFLICT,
            $message ?? __('messages.conflict'),
            $details ?? __('messages.conflict_detail')
        );
    }

    public function responseServerError(mixed $details = null, ?string $message = null): JsonResponse
    {
        return $this->apiError(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            $message ?? __('messages.server_error'),
            $details
        );
    }

    public function responseWithCustomError(mixed $title, mixed $details, int $statusCode): JsonResponse
    {
        return $this->apiError($statusCode, __($title), $details);
    }

    public function responseValidationError(ValidationException $exception): JsonResponse
    {
        $errors = collect($exception->validator->errors())->map(function ($messages, $field) {
            return [
                'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'title' => __('messages.validation_error'),
                'detail' => $messages[0],
                'source' => ['pointer' => '/' . str_replace('.', '/', $field)],
            ];
        })->values();

        return response()->json(
            ['errors' => $errors],
            Response::HTTP_UNPROCESSABLE_ENTITY,
            ['Content-Type' => 'application/problem+json']
        );
    }

    /** ========== Internal Helpers ========== */

    private function apiError(int $status, ?string $title, mixed $detail = null): JsonResponse
    {
        return response()->json([
            'errors' => [[
                'status' => $status,
                'title' => $title ?? __('messages.generic_error'),
                'detail' => $detail,
            ]],
        ], $status, ['Content-Type' => 'application/problem+json']);
    }

    private function buildResponse(int $status, ?string $message = null, mixed $data = null): JsonResponse
    {
        return response()->json([
            'status' => $status,
            'message' => $message,
            'data' => $data,
        ], $status);
    }
}
