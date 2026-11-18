<?php

namespace App\Services;

use RuntimeException;
use Symfony\Component\Process\Process;

class LeavePdfRenderer
{
    public function render(string $html): string
    {
        $localNode = glob(base_path('.tools/node-*-win-x64/node.exe')) ?: [];
        $node = config('leave.pdf_node_binary') ?: (end($localNode) ?: 'node');
        $environment = ['LEAVE_PDF_BROWSER_BINARY' => config('leave.pdf_browser_binary') ?: false];
        if (PHP_OS_FAMILY === 'Windows') {
            // Windows PHP web requests can omit the inherited variables Node and Edge need.
            foreach (['SystemRoot', 'WINDIR', 'ComSpec', 'TEMP', 'TMP', 'USERPROFILE', 'LOCALAPPDATA', 'APPDATA', 'PATH'] as $variable) {
                $value = getenv($variable, local_only: true);
                if ($value !== false) {
                    $environment[$variable] = $value;
                }
            }
        }

        $process = new Process(
            [$node, base_path('scripts/render-leave-pdf.mjs')],
            base_path(),
            $environment,
            $html,
            25,
        );
        $process->run();
        $pdf = $process->getOutput();
        if (! $process->isSuccessful() || ! str_starts_with($pdf, '%PDF-') || ! str_ends_with(rtrim($pdf), '%%EOF')) {
            throw new RuntimeException('The official leave PDF renderer did not return a complete PDF.');
        }

        return $pdf;
    }
}
