<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Traits\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotAcceptableHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    use ApiResponse;

    protected $dontReport = [];

    protected $dontFlash = ['password', 'password_confirmation'];

    public function render($request, Throwable $e): Response
    {
        $this->log($e);

        if ($request->expectsJson()) {
            return $this->handleApiException($e);
        }

        return parent::render($request, $e);
    }

    protected function handleApiException(Throwable $e): JsonResponse
    {
        return match (true) {
            $e instanceof ThrottleRequestsException => $this->responseForThrottleRequests(),
            $e instanceof ValidationException => $this->responseForValidation($e),
            $e instanceof ModelNotFoundException => $this->responseForModelNotFound($e),
            $e instanceof QueryException => $this->responseForQuery($e),
            $e instanceof AuthorizationException => $this->responseUnAuthorized(),
            $e instanceof AuthenticationException => $this->responseUnAuthenticated($e->getMessage()),
            $e instanceof NotFoundHttpException => $this->responseNotFound($e->getMessage()),
            $e instanceof UnprocessableEntityHttpException => $this->responseUnprocessable(
                $e->getMessage(),
                $this->formatExceptionTitle($e)
            ),
            $e instanceof BadRequestHttpException => $this->responseBadRequest(
                $e->getMessage(),
                $this->formatExceptionTitle($e)
            ),
            $e instanceof NotAcceptableHttpException => $this->responseWithCustomError(
                __('messages.not_acceptable'),
                $e->getMessage(),
                Response::HTTP_NOT_ACCEPTABLE
            ),
            $e instanceof ConflictHttpException => $this->responseConflictError($e->getMessage()),
            default => $this->responseServerError(
                app()->isProduction() ? null : $e->getMessage(),
                $this->formatExceptionTitle($e)
            ),
        };
    }

    protected function log(Throwable $exception): void
    {
        try {
            $logger = $this->container->make(LoggerInterface::class);
            $logger->error($exception->getMessage(), [
                ...$this->context(),
                'exception' => $exception,
            ]);
        } catch (BindingResolutionException) {
            // Logging service couldn't be resolved — ignore
        }
    }

    /** ========== Custom Exception Handlers ========== */

    protected function responseForValidation(ValidationException $e): JsonResponse
    {
        return $this->responseValidationError($e);
    }

    protected function responseForModelNotFound(ModelNotFoundException $e): JsonResponse
    {
        $id = !empty($e->getIds()) ? implode(', ', $e->getIds()) : 'unknown';
        $model = class_basename($e->getModel());

        return $this->responseNotFound(
            __('messages.model_not_found', ['model' => $model, 'id' => $id])
        );
    }

    protected function responseForQuery(QueryException $e): JsonResponse
    {
        return app()->isProduction()
            ? $this->responseServerError()
            : $this->responseNotFound($e->getMessage(), $this->formatExceptionTitle($e));
    }

    protected function responseForThrottleRequests(): JsonResponse
    {
        return $this->responseWithCustomError(
            __('messages.too_many_requests'),
            __('messages.try_again_later'),
            Response::HTTP_TOO_MANY_REQUESTS
        );
    }

    /** ========== Helpers ========== */

    protected function formatExceptionTitle(Throwable $e): string
    {
        return Str::title(Str::snake(class_basename($e), ' '));
    }
}
