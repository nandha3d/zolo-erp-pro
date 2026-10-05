<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

/** Validate the destination before creating an authenticated browser session. */
final class InternalRedirectValidator
{
    public function withQuery(mixed $destination, array $query): string
    {
        if (!is_string($destination) || $destination === '' || strlen($destination) > 8192) {
            $this->reject();
        }

        $normalized = $destination;
        // Decode to a fixed point, including nested encodings. Each changing pass shortens the input.
        do {
            $previous = $normalized;
            $normalized = rawurldecode($normalized);
            if (preg_match('/[\x00-\x1f\x7f\\\\]/', $normalized)) {
                $this->reject();
            }
        } while ($normalized !== $previous);

        $parts = parse_url($normalized);
        if ($parts === false || !str_starts_with($normalized, '/') || str_starts_with($normalized, '//')
            || isset($parts['scheme']) || isset($parts['host']) || isset($parts['user']) || isset($parts['port'])) {
            $this->reject();
        }

        // Preserve encoded query values, while normalizing encoded path separators for validation.
        $original = parse_url($destination);
        if ($original === false) {
            $this->reject();
        }
        $path = $original['path'] ?? '/';
        do {
            $previous = $path;
            $path = rawurldecode($path);
        } while ($path !== $previous);
        if (str_contains($path, '?') || str_contains($path, '#') || str_contains($path, ' ')) {
            $this->reject();
        }
        parse_str($original['query'] ?? '', $parameters);
        $encoded = http_build_query(array_replace($parameters, $query), '', '&', PHP_QUERY_RFC3986);

        return $path . ($encoded === '' ? '' : '?' . $encoded)
            . (isset($original['fragment']) ? '#' . $original['fragment'] : '');
    }

    private function reject(): never
    {
        throw ValidationException::withMessages(['redirect' => 'An internal application path is required.']);
    }
}
