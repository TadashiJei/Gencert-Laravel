<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use League\Csv\Reader;

class CsvImportService
{
    public function process(UploadedFile $file, array $requiredFields = ['name', 'email'])
    {
        $csv = Reader::createFromPath($file->getPathname(), 'r');
        $csv->setHeaderOffset(0);

        $headers = $csv->getHeader();
        $this->validateHeaders($headers, $requiredFields);

        $records = [];
        $errors = [];
        $rowNumber = 1;

        foreach ($csv->getRecords() as $record) {
            $rowNumber++;
            
            // Validate required fields
            $validator = Validator::make($record, [
                'name' => 'required|string|max:255',
                'email' => 'required|email|max:255',
            ]);

            if ($validator->fails()) {
                $errors[] = "Row {$rowNumber}: " . implode(', ', $validator->errors()->all());
                continue;
            }

            // Extract data fields (any column that's not name or email)
            $data = array_diff_key($record, array_flip(['name', 'email']));

            $records[] = [
                'name' => $record['name'],
                'email' => $record['email'],
                'data' => $data,
            ];
        }

        return [
            'success' => empty($errors),
            'records' => $records,
            'errors' => $errors,
        ];
    }

    protected function validateHeaders(array $headers, array $requiredFields)
    {
        $missingFields = array_diff($requiredFields, $headers);

        if (!empty($missingFields)) {
            throw new \InvalidArgumentException(
                'CSV file is missing required headers: ' . implode(', ', $missingFields)
            );
        }
    }
}
