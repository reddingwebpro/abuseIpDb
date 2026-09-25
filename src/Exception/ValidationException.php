<?php

declare(strict_types=1);

namespace AbuseIpDb\Exception;

/**
 * 422 Unprocessable Entity - one or more parameters were rejected.
 */
final class ValidationException extends ApiException
{
    /**
     * Error details keyed by the offending parameter (from `source.parameter`); unnamed errors use "_".
     *
     * @return array<string, list<string>>
     */
    public function getFieldErrors(): array
    {
        $fields = [];
        foreach ($this->getErrors() as $error) {
            $source = $error['source'] ?? null;
            $field = is_array($source) && isset($source['parameter']) && is_string($source['parameter'])
                ? $source['parameter'] : '_';
            $detail = $error['detail'] ?? null;
            if (is_string($detail)) {
                $fields[$field][] = $detail;
            }
        }

        return $fields;
    }
}
