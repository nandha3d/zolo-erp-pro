<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'password',
        'password_confirmation',
        'auth_token', 'token', 'api_key', 'provider_secret', 'authorization',
    ];

    public function register(): void
    {
        // A rejected stock posting wrote nothing; tell the operator why instead of failing with a server error.
        $this->renderable(function (\App\Services\Inventory\StockPolicyException $error, $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $error->getMessage()], 422);
            }

            return redirect()->back()->withInput()->with('not_permitted', $error->getMessage());
        });
    }

    /**
     * Report or log an exception.
     *
     * @param  \Throwable  $exception
     * @return void
     *
     * @throws \Throwable
     */
    public function report(Throwable $exception)
    {
        if ($exception instanceof \Illuminate\Database\QueryException || $exception instanceof \PDOException) {
            \Illuminate\Support\Facades\Log::error('erp.database_failure', ['message' => $exception->getMessage(), 'request_id' => request()->attributes->get('erp.request_id'), 'category' => class_basename($exception)]);
            return;
        }
        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Exception  $exception
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws \Throwable
     */
    public function render($request, Throwable $exception)
    {
        if ($exception instanceof \Illuminate\Database\QueryException || $exception instanceof \PDOException) {
            $response = $request->expectsJson() ? response()->json(['message' => 'Database temporarily unavailable. Contact your administrator with the request ID.'], 503)
                : response('Database temporarily unavailable. Contact your administrator with the request ID.', 503);
        } else {
            $response = parent::render($request, $exception);
        }
        if ($id = $request->attributes->get('erp.request_id')) $response->headers->set('X-Request-ID', $id);
        $response->headers->set('Cache-Control', 'private, no-store');
        return $response;
    }
}
