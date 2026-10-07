<?php

namespace App\Services;

class TestPage
{
    public function zpl(string $printer): string
    {
        $text = fn (string $value): string => str_replace(['^', '~'], '', $value);

        return '^XA^CF0,40^FO40,40^FDPrintlaris test^FS^CF0,28'
            .'^FO40,100^FD'.$text($printer).'^FS'
            .'^FO40,140^FD'.$text(now()->format('Y-m-d H:i')).'^FS^XZ';
    }

    public function pdf(string $printer): string
    {
        $escape = fn (string $value): string => addcslashes($value, '\\()');

        $content = "BT /F1 28 Tf 72 740 Td (Printlaris test page) Tj ET\n"
            ."BT /F1 14 Tf 72 700 Td (Printer: {$escape($printer)}) Tj ET\n"
            .'BT /F1 14 Tf 72 680 Td ('.$escape(now()->format('Y-m-d H:i')).") Tj ET\n";

        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            '<< /Length '.strlen($content)." >>\nstream\n{$content}endstream",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n{$object}\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";

        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf.'trailer << /Size '.(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
    }
}
