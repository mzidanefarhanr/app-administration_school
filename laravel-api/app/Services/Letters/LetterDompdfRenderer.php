<?php

namespace App\Services\Letters;

use Composer\InstalledVersions;
use Dompdf\Canvas;
use Dompdf\Dompdf;
use Dompdf\FontMetrics;
use Dompdf\Options;

/**
 * Renderer PDF surat bertipe (Fase 07c, DEC-34).
 *
 * Input hanya view internal dan data snapshot; remote resource, JavaScript,
 * dan PHP dinonaktifkan. Watermark preview serta footer digambar per halaman.
 */
class LetterDompdfRenderer
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{bytes: string, page_count: int, renderer: string}
     */
    public function render(string $view, array $data, ?string $watermark = null): array
    {
        if (! view()->exists($view)) {
            throw new \RuntimeException("Letter PDF view [{$view}] does not exist.");
        }

        $fontFamily = (string) config('letters.document.font', 'DejaVu Sans');

        $options = new Options([
            'defaultFont' => $fontFamily,
            'isRemoteEnabled' => false,
            'isJavascriptEnabled' => false,
            'isPhpEnabled' => false,
            'chroot' => storage_path('app'),
        ]);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view($view, $data)->render(), 'UTF-8');
        $dompdf->setPaper(
            (string) config('letters.document.paper', 'a4'),
            (string) config('letters.document.orientation', 'portrait'),
        );

        $footerLabel = $watermark !== null && $watermark !== ''
            ? $watermark
            : (string) ($data['letter']['official_number'] ?? '');

        $dompdf->setCallbacks([[
            'event' => 'end_document',
            'f' => function (int $pageNumber, int $pageCount, Canvas $canvas, FontMetrics $fontMetrics) use ($fontFamily, $footerLabel, $watermark): void {
                $this->drawFooter($canvas, $fontMetrics, $fontFamily, $footerLabel, $pageNumber, $pageCount);

                if ($watermark !== null && $watermark !== '') {
                    $this->drawWatermark($canvas, $fontMetrics, $fontFamily, $watermark);
                }
            },
        ]]);

        $dompdf->render();

        $bytes = $dompdf->output();
        $pageCount = max(1, (int) $dompdf->getCanvas()->get_page_count());

        return [
            'bytes' => is_string($bytes) ? $bytes : '',
            'page_count' => $pageCount,
            'renderer' => $this->rendererName(),
        ];
    }

    private function drawFooter(
        Canvas $canvas,
        FontMetrics $fontMetrics,
        string $fontFamily,
        string $label,
        int $pageNumber,
        int $pageCount,
    ): void {
        $font = $fontMetrics->getFont($fontFamily, 'normal');

        if ($font === null) {
            return;
        }

        $size = 7.5;
        $color = [0.42, 0.42, 0.42];
        $baseline = $canvas->get_height() - 28.0;
        $margin = 40.0;

        if ($label !== '') {
            $canvas->text($margin, $baseline, $label, $font, $size, $color);
        }

        $pageLabel = 'Page '.$pageNumber.' of '.$pageCount;
        $pageWidth = $fontMetrics->getTextWidth($pageLabel, $font, $size);

        $canvas->text(
            $canvas->get_width() - $margin - $pageWidth,
            $baseline,
            $pageLabel,
            $font,
            $size,
            $color,
        );
    }

    private function drawWatermark(
        Canvas $canvas,
        FontMetrics $fontMetrics,
        string $fontFamily,
        string $watermark,
    ): void {
        $font = $fontMetrics->getFont($fontFamily, 'bold') ?? $fontMetrics->getFont($fontFamily, 'normal');

        if ($font === null) {
            return;
        }

        $size = 38.0;
        $width = $fontMetrics->getTextWidth($watermark, $font, $size);
        $height = $fontMetrics->getFontHeight($font, $size);

        $x = max(20.0, ($canvas->get_width() - $width) / 2);
        $y = ($canvas->get_height() + $height) / 2;

        $canvas->text($x, $y, $watermark, $font, $size, [0.78, 0.78, 0.78], 0.0, 0.0, 45.0);
    }

    private function rendererName(): string
    {
        try {
            $version = InstalledVersions::getPrettyVersion('dompdf/dompdf');
        } catch (\Throwable) {
            $version = null;
        }

        return is_string($version) && $version !== '' ? 'dompdf/'.$version : 'dompdf';
    }
}
