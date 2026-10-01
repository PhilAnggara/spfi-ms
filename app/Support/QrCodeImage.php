<?php

namespace App\Support;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use InvalidArgumentException;
use RuntimeException;

class QrCodeImage
{
    /**
     * Generate a PNG QR code using GD (no Imagick required).
     */
    public static function png(string $content, int $size = 200): string
    {
        $content = trim($content);

        if ($content === '') {
            throw new InvalidArgumentException('QR content cannot be empty.');
        }

        if ($size < 50) {
            throw new InvalidArgumentException('QR size must be at least 50 pixels.');
        }

        if (! function_exists('imagecreate')) {
            throw new RuntimeException('GD extension is required to generate QR code images.');
        }

        $qrCode = Encoder::encode($content, ErrorCorrectionLevel::M());
        $matrix = $qrCode->getMatrix();
        $moduleCount = $matrix->getWidth();
        $moduleSize = max(1, (int) floor($size / ($moduleCount + 2)));
        $imageSize = $moduleSize * ($moduleCount + 2);

        $image = imagecreate($imageSize, $imageSize);

        if ($image === false) {
            throw new RuntimeException('Unable to create QR code image.');
        }

        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);

        if ($white === false || $black === false) {
            imagedestroy($image);
            throw new RuntimeException('Unable to allocate QR code colors.');
        }

        imagefilledrectangle($image, 0, 0, $imageSize - 1, $imageSize - 1, $white);

        for ($y = 0; $y < $moduleCount; $y++) {
            for ($x = 0; $x < $moduleCount; $x++) {
                if ($matrix->get($x, $y) !== 1) {
                    continue;
                }

                $left = ($x + 1) * $moduleSize;
                $top = ($y + 1) * $moduleSize;

                imagefilledrectangle(
                    $image,
                    $left,
                    $top,
                    $left + $moduleSize - 1,
                    $top + $moduleSize - 1,
                    $black
                );
            }
        }

        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        imagedestroy($image);

        if ($png === false || $png === '') {
            throw new RuntimeException('Unable to encode QR code PNG.');
        }

        return $png;
    }

    public static function dataUri(string $content, int $size = 200): string
    {
        return 'data:image/png;base64,'.base64_encode(self::png($content, $size));
    }
}
