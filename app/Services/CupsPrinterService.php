<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Symfony\Component\Process\Process;

class CupsPrinterService
{
    /**
     * @return Collection<int, array{name:string,state:string,description:?string,location:?string}>
     */
    public function listPrinters(): Collection
    {
        $process = new Process(['lpstat', '-p', '-l']);
        $process->run();

        if (!$process->isSuccessful()) {
            return collect();
        }

        $lines = preg_split('/\r\n|\r|\n/', $process->getOutput()) ?: [];
        $printers = collect();
        $current = null;

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if (preg_match('/^printer\s+(\S+)\s+is\s+(\S+)/i', $trimmed, $matches)) {
                if ($current !== null) {
                    $printers->push($current);
                }

                $current = [
                    'name' => $matches[1],
                    'state' => strtolower($matches[2]),
                    'description' => null,
                    'location' => null,
                ];

                continue;
            }

            if ($current === null) {
                continue;
            }

            if (preg_match('/^Description:\s*(.*)$/i', $trimmed, $matches)) {
                $current['description'] = trim($matches[1]);
                continue;
            }

            if (preg_match('/^Location:\s*(.*)$/i', $trimmed, $matches)) {
                $current['location'] = trim($matches[1]);
            }
        }

        if ($current !== null) {
            $printers->push($current);
        }

        return $printers->sortBy('name');
    }
}
