<?php

namespace App\Http\Requests\Concerns;

use App\Support\OraDocument;
use Illuminate\Validation\Validator;

trait ValidatesOraDocument
{
    protected function validateOraDocumentLimits(Validator $validator): void
    {
        $document = $this->input('document');
        if (! is_array($document)) {
            return;
        }

        $error = OraDocument::limitError($document);
        if ($error !== null) {
            $validator->errors()->add('document', $error);
        }
    }
}
