<?php

namespace App\Accounting;

use Mpdf\Mpdf;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

final class Exports
{
    public function xlsx(iterable $rows, string $prefix = 'report'): string
    {
        $dir = storage_path('app/private/exports');
        if (! is_dir($dir)) {
            mkdir($dir, 0700, true);
        }$path = $dir.'/'.$prefix.'-'.bin2hex(random_bytes(12)).'.xlsx';
        $w = new Writer;
        $w->openToFile($path);
        foreach ($rows as $row) {
            $w->addRow(new Row(array_map(fn ($v) => new StringCell((string) $v), $row)));
        }$w->close();

        return $path;
    }

    public function pdf(array $data): string
    {
        $dir = storage_path('app/private/pdf');
        if (! is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
        $pdf = new Mpdf(['mode' => 'utf-8', 'format' => 'A4-L', 'tempDir' => $dir, 'default_font' => 'dejavusans', 'autoScriptToLang' => true, 'autoLangToFont' => true]);
        $pdf->SetDirectionality('rtl');
        $pdf->WriteHTML(view('accounting.pdf', $data)->render());

        return $pdf->OutputBinaryData();
    }

    public function ledgerRows(iterable $ledger): array
    {
        $rows = [['تاریخ میلادی', 'شماره', 'حساب', 'شخص', 'بانک', 'بدهکار ریال', 'بستانکار ریال', 'شرح']];
        foreach ($ledger as $l) {
            $rows[] = [$l->date, $l->number, AccountingService::ACCOUNTS[$l->account], $l->party_id ?? '', $l->bank_account_id ?? '', $l->debit, $l->credit, $l->description];
        }

        return $rows;
    }
}
