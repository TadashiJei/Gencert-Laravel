<?php

namespace App\Services;

use App\Models\Certificate;
use App\Models\Template;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Browsershot\Browsershot;

class CertificateService
{
    public function generateCertificate(Certificate $certificate): string
    {
        $template = $certificate->template;
        $content = $this->replacePlaceholders($template->latestVersion()->content, $certificate->data);
        
        $filePath = $this->generateFile($content, $certificate->format, $certificate);
        
        $certificate->update([
            'file_path' => $filePath,
            'status' => 'generated',
            'generated_at' => now(),
        ]);

        return $filePath;
    }

    public function generateBulk(array $data, Template $template, string $format = 'pdf'): array
    {
        $results = [];
        
        foreach ($data as $recipientData) {
            $certificate = Certificate::create([
                'template_id' => $template->id,
                'user_id' => auth()->id(),
                'recipient_name' => $recipientData['name'],
                'recipient_email' => $recipientData['email'] ?? null,
                'data' => $recipientData,
                'format' => $format,
            ]);

            try {
                $filePath = $this->generateCertificate($certificate);
                $results[] = [
                    'success' => true,
                    'certificate_id' => $certificate->id,
                    'file_path' => $filePath,
                ];
            } catch (\Exception $e) {
                $results[] = [
                    'success' => false,
                    'certificate_id' => $certificate->id,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    protected function replacePlaceholders(string $content, array $data): string
    {
        foreach ($data as $key => $value) {
            $content = str_replace('{{' . $key . '}}', $value, $content);
        }
        return $content;
    }

    protected function generateFile(string $content, string $format, Certificate $certificate): string
    {
        $fileName = Str::slug($certificate->recipient_name) . '-' . time() . '.' . $format;
        $directory = 'certificates/' . date('Y/m');
        $fullPath = $directory . '/' . $fileName;

        switch ($format) {
            case 'pdf':
                $this->generatePDF($content, $fullPath);
                break;
            case 'png':
                $this->generatePNG($content, $fullPath);
                break;
            case 'svg':
                $this->generateSVG($content, $fullPath);
                break;
            default:
                throw new \InvalidArgumentException("Unsupported format: {$format}");
        }

        return $fullPath;
    }

    protected function generatePDF(string $content, string $fullPath): void
    {
        $pdf = PDF::loadHTML($this->wrapContent($content));
        Storage::put($fullPath, $pdf->output());
    }

    protected function generatePNG(string $content, string $fullPath): void
    {
        Browsershot::html($this->wrapContent($content))
            ->windowSize(1024, 768)
            ->waitUntilNetworkIdle()
            ->save(Storage::path($fullPath));
    }

    protected function generateSVG(string $content, string $fullPath): void
    {
        // For SVG, we'll need to ensure the content is proper SVG
        if (!Str::contains($content, '<svg')) {
            $content = $this->convertToSVG($content);
        }
        Storage::put($fullPath, $content);
    }

    protected function wrapContent(string $content): string
    {
        return '
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="utf-8">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <title>Certificate</title>
                <style>
                    body {
                        margin: 0;
                        padding: 0;
                        width: 100%;
                        height: 100%;
                    }
                    .certificate-container {
                        width: 100%;
                        height: 100%;
                        padding: 20px;
                        box-sizing: border-box;
                    }
                </style>
            </head>
            <body>
                <div class="certificate-container">
                    ' . $content . '
                </div>
            </body>
            </html>
        ';
    }

    protected function convertToSVG(string $content): string
    {
        // Basic conversion of HTML to SVG
        return '
            <svg xmlns="http://www.w3.org/2000/svg" width="1024" height="768">
                <foreignObject width="100%" height="100%">
                    <div xmlns="http://www.w3.org/1999/xhtml">
                        ' . $content . '
                    </div>
                </foreignObject>
            </svg>
        ';
    }
}
