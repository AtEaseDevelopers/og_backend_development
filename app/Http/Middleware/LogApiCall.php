<?php

namespace App\Http\Middleware;

use App\Models\ApiLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records every API call in api_logs: who, what was sent, what came back, how long it took. Passwords and tokens are
 * never stored. A failure to write the log never fails the call.
 */
class LogApiCall
{
    private const SECRET_KEYS = ['password', 'password_confirmation', 'current_password', 'token'];

    private const MAX_RESPONSE = 20000;

    public function handle(Request $request, Closure $next): Response
    {
        $started = microtime(true);
        $response = $next($request);

        try {
            ApiLog::query()->create([
                'user_id' => $request->user()?->getAuthIdentifier(),
                'method' => $request->method(),
                'path' => Str::limit($request->path(), 497),
                'route' => $request->route()?->uri(),
                'status' => $response->getStatusCode(),
                'duration_ms' => (int) round((microtime(true) - $started) * 1000),
                'ip' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 497),
                'request' => $this->mask($request->all()),
                'response' => $this->responseBody($response),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }

        return $response;
    }

    /** Secrets replaced by ***, uploaded files by their name and size. */
    private function mask(array $data): array
    {
        foreach ($data as $key => $value) {
            $data[$key] = match (true) {
                in_array(strtolower((string) $key), self::SECRET_KEYS, true) => '***',
                is_array($value) => $this->mask($value),
                $value instanceof UploadedFile => '[file '.$value->getClientOriginalName().' '.$value->getSize().' bytes]',
                default => $value,
            };
        }

        return $data;
    }

    private function responseBody(Response $response): ?string
    {
        $content = $response->getContent();
        if (! is_string($content) || $content === '') {
            return null; // a file download or stream
        }

        $json = json_decode($content, true);
        if (is_array($json)) {
            $content = json_encode($this->mask($json), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return mb_strcut($content, 0, self::MAX_RESPONSE);
    }
}
