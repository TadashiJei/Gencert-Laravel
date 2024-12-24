<?php

namespace App\Services\Analytics;

use App\Models\Report;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use Dompdf\Dompdf;
use Carbon\Carbon;

class ReportExportService
{
    /**
     * Export report to PDF
     */
    public function toPdf(Report $report)
    {
        $dompdf = new Dompdf();
        $html = view('reports.pdf', [
            'report' => $report,
            'data' => $report->data,
            'generated_at' => now()
        ])->render();

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = $this->generateFilename($report, 'pdf');
        Storage::put("reports/{$filename}", $dompdf->output());

        return "reports/{$filename}";
    }

    /**
     * Export report to Excel
     */
    public function toExcel(Report $report)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Set headers
        $headers = $this->getReportHeaders($report);
        foreach ($headers as $col => $header) {
            $sheet->setCellValue(chr(65 + $col) . '1', $header);
        }

        // Add data
        $row = 2;
        foreach ($this->formatReportData($report) as $dataRow) {
            foreach ($dataRow as $col => $value) {
                $sheet->setCellValue(chr(65 + $col) . $row, $value);
            }
            $row++;
        }

        // Style the worksheet
        $sheet->getStyle('A1:' . chr(65 + count($headers) - 1) . '1')->applyFromArray([
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E0E0E0']
            ]
        ]);

        $writer = new Xlsx($spreadsheet);
        $filename = $this->generateFilename($report, 'xlsx');
        $path = storage_path("app/reports/{$filename}");
        $writer->save($path);

        return "reports/{$filename}";
    }

    /**
     * Export report to CSV
     */
    public function toCsv(Report $report)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Set headers
        $headers = $this->getReportHeaders($report);
        foreach ($headers as $col => $header) {
            $sheet->setCellValue(chr(65 + $col) . '1', $header);
        }

        // Add data
        $row = 2;
        foreach ($this->formatReportData($report) as $dataRow) {
            foreach ($dataRow as $col => $value) {
                $sheet->setCellValue(chr(65 + $col) . $row, $value);
            }
            $row++;
        }

        $writer = new Csv($spreadsheet);
        $filename = $this->generateFilename($report, 'csv');
        $path = storage_path("app/reports/{$filename}");
        $writer->save($path);

        return "reports/{$filename}";
    }

    /**
     * Export report to JSON
     */
    public function toJson(Report $report)
    {
        $data = [
            'report' => [
                'id' => $report->id,
                'name' => $report->name,
                'type' => $report->type,
                'generated_at' => now()->toIso8601String()
            ],
            'data' => $report->data
        ];

        $filename = $this->generateFilename($report, 'json');
        Storage::put("reports/{$filename}", json_encode($data, JSON_PRETTY_PRINT));

        return "reports/{$filename}";
    }

    /**
     * Generate filename for report
     */
    protected function generateFilename(Report $report, string $extension)
    {
        $timestamp = now()->format('Y-m-d_His');
        $safeName = str_replace(' ', '_', strtolower($report->name));
        return "{$safeName}_{$timestamp}.{$extension}";
    }

    /**
     * Get report headers based on report type
     */
    protected function getReportHeaders(Report $report)
    {
        switch ($report->type) {
            case 'certificate_metrics':
                return [
                    'Date',
                    'Total Certificates',
                    'Active',
                    'Expired',
                    'Revoked',
                    'Expiring Soon'
                ];
            case 'user_metrics':
                return [
                    'Date',
                    'Total Users',
                    'Active Users',
                    'New Users',
                    'User Activities'
                ];
            case 'activity_metrics':
                return [
                    'Date',
                    'Activity Type',
                    'User',
                    'Details',
                    'IP Address'
                ];
            default:
                return array_keys($report->data[0] ?? []);
        }
    }

    /**
     * Format report data for export
     */
    protected function formatReportData(Report $report)
    {
        $data = [];

        switch ($report->type) {
            case 'certificate_metrics':
                foreach ($report->data['certificates'] ?? [] as $date => $metrics) {
                    $data[] = [
                        Carbon::parse($date)->format('Y-m-d'),
                        $metrics['total'] ?? 0,
                        $metrics['active'] ?? 0,
                        $metrics['expired'] ?? 0,
                        $metrics['revoked'] ?? 0,
                        $metrics['expiring_soon'] ?? 0
                    ];
                }
                break;

            case 'user_metrics':
                foreach ($report->data['users'] ?? [] as $date => $metrics) {
                    $data[] = [
                        Carbon::parse($date)->format('Y-m-d'),
                        $metrics['total_users'] ?? 0,
                        $metrics['active_users'] ?? 0,
                        $metrics['new_users'] ?? 0,
                        $metrics['activities'] ?? 0
                    ];
                }
                break;

            case 'activity_metrics':
                foreach ($report->data['activities'] ?? [] as $activity) {
                    $data[] = [
                        Carbon::parse($activity['created_at'])->format('Y-m-d H:i:s'),
                        $activity['activity_type'],
                        $activity['user']['name'] ?? 'N/A',
                        json_encode($activity['metadata'] ?? []),
                        $activity['ip_address']
                    ];
                }
                break;

            default:
                $data = $report->data;
        }

        return $data;
    }
}
