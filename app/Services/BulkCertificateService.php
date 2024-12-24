<?php

namespace App\Services;

use App\Models\BulkCertificateJob;
use App\Models\CertificateTemplate;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use League\Csv\Reader;
use League\Csv\Writer;

class BulkCertificateService
{
    public function create(array $data)
    {
        /** @var UploadedFile $file */
        $file = $data['file'];
        $path = $file->store('bulk-jobs');

        $csv = Reader::createFromPath($file->path());
        $csv->setHeaderOffset(0);
        $totalRecords = count($csv);

        return BulkCertificateJob::create([
            'user_id' => auth()->id(),
            'template_id' => $data['template_id'],
            'file_path' => $path,
            'field_mapping' => $data['field_mapping'],
            'total_records' => $totalRecords,
            'status' => BulkCertificateJob::STATUS_PENDING,
        ]);
    }

    public function process(BulkCertificateJob $job)
    {
        try {
            $job->update(['status' => BulkCertificateJob::STATUS_PROCESSING]);

            $csv = Reader::createFromPath(Storage::path($job->file_path));
            $csv->setHeaderOffset(0);

            foreach ($csv as $index => $record) {
                try {
                    $this->processSingleRecord($job, $record, $index + 1);
                    $job->incrementProcessed();
                } catch (\Exception $e) {
                    $job->addError($index + 1, $e->getMessage());
                }
            }

            if ($job->failed_records === 0) {
                $job->update(['status' => BulkCertificateJob::STATUS_COMPLETED]);
            } else {
                $job->update(['status' => BulkCertificateJob::STATUS_FAILED]);
            }
        } catch (\Exception $e) {
            $job->update(['status' => BulkCertificateJob::STATUS_FAILED]);
            throw $e;
        }
    }

    protected function processSingleRecord(BulkCertificateJob $job, array $record, int $rowNumber)
    {
        $mappedData = [];
        foreach ($job->field_mapping as $templateField => $csvField) {
            if (!isset($record[$csvField])) {
                throw new \Exception("Field '{$csvField}' not found in CSV");
            }
            $mappedData[$templateField] = $record[$csvField];
        }

        // Create certificate using mapped data
        // This would call your certificate generation service
        // Implementation depends on your certificate generation logic
    }

    public function generateTemplateFile(CertificateTemplate $template)
    {
        $headers = array_merge(
            ['recipient_name', 'recipient_email'],
            array_keys($template->custom_fields ?? [])
        );

        $csv = Writer::createFromString();
        $csv->insertOne($headers);

        return response($csv->getContent())
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="template.csv"');
    }

    public function generateErrorReport(BulkCertificateJob $job)
    {
        $csv = Writer::createFromString();
        $csv->insertOne(['Row', 'Error', 'Timestamp']);

        foreach ($job->error_log as $error) {
            $csv->insertOne([
                $error['row'],
                $error['message'],
                $error['timestamp'],
            ]);
        }

        return response($csv->getContent())
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="error-report.csv"');
    }

    public function cancel(BulkCertificateJob $job)
    {
        $job->update(['status' => BulkCertificateJob::STATUS_FAILED]);
        // Additional cleanup if needed
    }
}
