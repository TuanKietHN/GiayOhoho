<?php

namespace App\Http\Controllers;

abstract class Controller
{
    protected function ok(mixed $data = null, string $message = 'OK', int $status = 200)
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => now()->getTimestampMs(),
        ], $status);
    }

    protected function created(mixed $data = null, string $message = 'Created')
    {
        return $this->ok($data, $message, 201);
    }
}
