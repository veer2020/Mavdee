<?php
/**
 * includes/image.php
 * ImageOptimizer — Server-side image resizing, WebP output, and srcset generation.
 */
declare(strict_types=1);

class ImageOptimizer
{
    /**
     * Resize an image and optionally output a WebP version.
     *
     * @param string $sourcePath   Absolute path to source image.
     * @param string $targetPath   Absolute path for output (without extension).
     * @param int    $width        Max output width in px.
     * @param int    $height       Max output height in px.
     * @param int    $quality      JPEG/WebP quality (1–100).
     * @return bool
     */
    public static function optimize(
        string $sourcePath,
        string $targetPath,
        int $width,
        int $height,
        int $quality = 80
    ): bool {
        if (!file_exists($sourcePath)) return false;

        [$origWidth, $origHeight, $type] = getimagesize($sourcePath);

        switch ($type) {
            case IMAGETYPE_JPEG:
                $image = imagecreatefromjpeg($sourcePath);
                break;
            case IMAGETYPE_PNG:
                $image = imagecreatefrompng($sourcePath);
                imagepalettetotruecolor($image);
                break;
            case IMAGETYPE_WEBP:
                $image = imagecreatefromwebp($sourcePath);
                break;
            default:
                return false;
        }

        if (!$image) return false;

        // Calculate new dimensions maintaining aspect ratio
        $ratio     = $origWidth / $origHeight;
        $newWidth  = $width;
        $newHeight = (int)round($newWidth / $ratio);

        if ($newHeight > $height) {
            $newHeight = $height;
            $newWidth  = (int)round($newHeight * $ratio);
        }

        $newImage = imagecreatetruecolor($newWidth, $newHeight);

        // Preserve transparency for PNG
        if ($type === IMAGETYPE_PNG) {
            imagealphablending($newImage, false);
            imagesavealpha($newImage, true);
        }

        imagecopyresampled($newImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);

        // Save as WebP (modern format) if supported
        if (function_exists('imagewebp')) {
            imagewebp($newImage, $targetPath . '.webp', $quality);
        }

        // Save original format as fallback
        imagejpeg($newImage, $targetPath . '.jpg', $quality);

        imagedestroy($image);
        imagedestroy($newImage);

        return true;
    }

    /**
     * Generate a responsive srcset string for an image URL.
     *
     * @param string $imagePath  Base URL of the image.
     * @param array  $sizes      Array of widths to generate descriptors for.
     * @return string
     */
    public static function getSrcSet(string $imagePath, array $sizes = [320, 640, 768, 1024, 1280]): string
    {
        $srcset = [];
        foreach ($sizes as $size) {
            $srcset[] = "{$imagePath}?w={$size} {$size}w";
        }
        return implode(', ', $srcset);
    }
}
