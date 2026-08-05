<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

class PeriodPnlExcelExport
{
    /**
     * @param  array{
     *   from:string,
     *   to:string,
     *   timezone:string,
     *   order_count:int,
     *   lines:list<array{key:string,label:string,amount:int,section:string,note:?string}>,
     *   cash_by_type:list<array{entry_type:string,amount:int}>,
     *   bank_by_type:list<array{entry_type:string,amount:int,fee_amount:int}>,
     *   positions:array
     * }  $report
     */
    public static function download(array $report, ?string $filename = null): StreamedResponse
    {
        $filename = $filename ?: sprintf(
            'period-pnl-%s-to-%s.csv',
            $report['from'] ?? 'from',
            $report['to'] ?? 'to'
        );
        if (! str_ends_with(strtolower($filename), '.csv')) {
            $filename .= '.csv';
        }

        return response()->streamDownload(function () use ($report) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, ['Period P&L']);
            fputcsv($out, ['From', $report['from'] ?? '']);
            fputcsv($out, ['To', $report['to'] ?? '']);
            fputcsv($out, ['Timezone', $report['timezone'] ?? 'Asia/Dhaka']);
            fputcsv($out, ['Orders', $report['order_count'] ?? 0]);
            fputcsv($out, []);
            fputcsv($out, ['Section', 'Line', 'Amount (৳)', 'Note']);

            foreach ($report['lines'] ?? [] as $line) {
                fputcsv($out, [
                    $line['section'] ?? '',
                    $line['label'] ?? '',
                    $line['amount'] ?? 0,
                    $line['note'] ?? '',
                ]);
            }

            fputcsv($out, []);
            fputcsv($out, ['Cash movements by type']);
            fputcsv($out, ['Entry type', 'Amount (৳)']);
            foreach ($report['cash_by_type'] ?? [] as $row) {
                fputcsv($out, [$row['entry_type'], $row['amount']]);
            }

            fputcsv($out, []);
            fputcsv($out, ['Bank movements by type']);
            fputcsv($out, ['Entry type', 'Amount (৳)', 'Fee amount (৳)']);
            foreach ($report['bank_by_type'] ?? [] as $row) {
                fputcsv($out, [$row['entry_type'], $row['amount'], $row['fee_amount']]);
            }

            $positions = $report['positions'] ?? [];
            fputcsv($out, []);
            fputcsv($out, ['Cash positions (point-in-time)']);
            fputcsv($out, ['Bucket', 'Amount (৳)']);
            foreach ([
                'cash_at_eps' => 'Cash at EPS',
                'cash_receivable_kitchen' => 'Recv. kitchen',
                'cash_receivable_riders' => 'Recv. riders',
                'cash_in_hand' => 'Cash in hand (till)',
                'bank_float' => 'Bank float',
            ] as $key => $label) {
                fputcsv($out, [$label, $positions[$key]['amount'] ?? 0]);
            }
            fputcsv($out, ['Total cash cycle', $positions['total_cash_cycle'] ?? 0]);

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
