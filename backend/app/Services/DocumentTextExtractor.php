<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser as PdfParser;

class DocumentTextExtractor
{
    /**
     * Extract plain text from a stored document, given its disk path and mime type.
     *
     * @throws \Exception when the file can't be parsed (corrupt PDF, unsupported type)
     */
    public function extract(string $filePath, string $mimeType): string
    {
        $fullPath = Storage::disk('local')->path($filePath);

        if ($mimeType === 'application/pdf') {
            $parser = new PdfParser();
            $pdf = $parser->parseFile($fullPath);
            return $pdf->getText();
        }

        // text/plain, text/markdown, or anything else readable as-is
        return Storage::disk('local')->get($filePath);
    }
}
