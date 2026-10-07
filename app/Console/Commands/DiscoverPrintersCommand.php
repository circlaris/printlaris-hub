<?php

namespace App\Console\Commands;

use App\Services\Ipp\IppPrinters;
use App\Services\Zebra\ZebraPrinters;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('printlaris:discover-printers')]
#[Description('Scan the LAN for Zebra label printers (TLS raw port) and IPP office printers.')]
class DiscoverPrintersCommand extends Command
{
    public function handle(ZebraPrinters $zebras, IppPrinters $ipp): int
    {
        $ipp->discover();
        $printers = $zebras->discover();

        $this->table(
            ['Name', 'Address', 'Model', 'Firmware', 'Online'],
            array_map(fn (array $printer): array => [
                $printer['name'],
                $printer['host'].':'.$printer['port'],
                $printer['model'],
                $printer['firmware'],
                $printer['online'] ? 'yes' : 'no',
            ], array_values($printers)),
        );

        return self::SUCCESS;
    }
}
