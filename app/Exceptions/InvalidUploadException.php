<?php

namespace App\Exceptions;

use Illuminate\Validation\ValidationException;

/**
 * A file MediaService refuses (wrong type, unreadable, too large). It is a validation error, so if a
 * controller ever passes a file without the SafeUpload rule, the visitor still gets a 422 on "file"
 * instead of a server error. The SafeUpload rule turns it into the form field's own error.
 */
class InvalidUploadException extends ValidationException
{
    public static function because(string $reason): self
    {
        return self::withMessages(['file' => $reason]);
    }

    public function reason(): string
    {
        return (string) $this->validator->errors()->first('file');
    }
}
