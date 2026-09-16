<?php

namespace App\Services;

class ZplLabelBuilder
{
    public function buildForWatchLabel(array $data = []): string
    {
        $product = $data['product'] ?? 'Watch Label';
        $sku = $data['sku'] ?? 'N/A';
        $serial = $data['serial'] ?? 'N/A';

        return "^XA\n^CF0,30\n^FO40,40^FD{$product}\^FS\n^FO40,90^FDSKU: {$sku}\^FS\n^FO40,140^FDSerial: {$serial}\^FS\n^XZ";
    }

    public function buildForJewelryLabel(array $data = []): string
    {
        $product = $data['product'] ?? 'Jewelry Label';
        $sku = $data['sku'] ?? 'N/A';
        $serial = $data['serial'] ?? 'N/A';

        return "^XA\n^CF0,28\n^FO30,30^FD{$product}\^FS\n^FO30,80^FD{$sku}\^FS\n^FO30,120^FD{$serial}\^FS\n^XZ";
    }
}
