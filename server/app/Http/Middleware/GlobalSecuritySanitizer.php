<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class GlobalSecuritySanitizer
{
    /**
     * Strict SQLi signature patterns (non-destructive subset).
     * Matches canonical attack vectors without flagging legitimate data.
     */
    private array $sqliPatterns = [
        '/(\bunion\b.+\bselect\b)/i',
        '/(\bselect\b.+\bfrom\b.+\bwhere\b)/i',
        '/(\bdrop\b\s+\btable\b)/i',
        '/(\binsert\b\s+\binto\b)/i',
        '/(\bdelete\b\s+\bfrom\b)/i',
        '/(\bor\b\s+["\']?\d+["\']?\s*=\s*["\']?\d+["\']?)/i', // OR 1=1
        '/(\bwaitfor\b\s+\bdelay\b)/i',                           // MSSQL time-based
        '/(--\s*$)/m',                                             // SQL comment strip
        '/;\s*\bdrop\b/i',                                         // Stacked query drop
        '/xp_cmdshell/i',                                          // MSSQL exec vector
    ];

    /**
     * XSS patterns targeting injected HTML/JS execution contexts.
     */
    private array $xssPatterns = [
        '/<script\b[^>]*>.*?<\/script>/is',
        '/<iframe\b[^>]*>.*?<\/iframe>/is',
        '/\bon\w+\s*=\s*["\'][^"\']*["\']/i',   // onerror=, onload=, onclick=
        '/<(object|embed|applet|link|meta)\b[^>]*>/i',
        '/javascript\s*:/i',
        '/vbscript\s*:/i',
        '/data\s*:\s*text\/html/i',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $method = strtoupper($request->method());

        if (in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $payload = $request->all();
            $flatPayload = $this->flattenArray($payload);
            $fullString = implode(' ', array_map('strval', $flatPayload));

            // 1. SQLi Hardstop — block and log immediately
            foreach ($this->sqliPatterns as $pattern) {
                if (preg_match($pattern, $fullString)) {
                    Log::channel('security')->critical('SQLi signature detected', [
                        'ip'          => $request->ip(),
                        'url'         => $request->fullUrl(),
                        'method'      => $method,
                        'user_agent'  => $request->userAgent(),
                        'payload_snip' => substr($fullString, 0, 300),
                    ]);

                    return response()->json([
                        'message'    => 'FPM Security: Malicious payload signature detected.',
                        'error_code' => 'SECURITY_SQLI_BLOCKED'
                    ], 400);
                }
            }

            // 2. XSS Sanitize — strip tags, do NOT block (permissive sanitization)
            $sanitized = $this->sanitizeArray($payload);
            $request->replace($sanitized);
        }

        return $next($request);
    }

    /**
     * Recursively flatten a nested array to a single-depth key-value set for pattern scanning.
     */
    private function flattenArray(array $array): array
    {
        $result = [];
        array_walk_recursive($array, function ($value) use (&$result) {
            $result[] = $value;
        });
        return $result;
    }

    /**
     * Recursively strip XSS vectors from all string values in a nested array.
     */
    private function sanitizeArray(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->sanitizeArray($value);
            } elseif (is_string($value)) {
                $data[$key] = $this->sanitizeString($value);
            }
        }
        return $data;
    }

    private function sanitizeString(string $value): string
    {
        foreach ($this->xssPatterns as $pattern) {
            $value = preg_replace($pattern, '', $value);
        }
        // Strip remaining HTML tags after pattern passes
        return strip_tags($value);
    }
}
