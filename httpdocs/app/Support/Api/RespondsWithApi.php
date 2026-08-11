<?php

namespace App\Support\Api;

use Illuminate\Http\JsonResponse;

trait RespondsWithApi
{
    protected function ok(array $data = [], array $meta = []): JsonResponse
    {
        return response()->json([
            'ok'    => true,
            'data'  => $data,
            'error' => null,
            'meta'  => $meta,
        ]);
    }

    protected function fail(string $message, int $code = 422, array $meta = []): JsonResponse
    {
        return response()->json([
            'ok'    => false,
            'data'  => null,
            'error' => $message,
            'meta'  => $meta,
        ], $code);
    }
}
