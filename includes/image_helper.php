<?php

/**
 * includes/image_helper.php
 * WebP helper – returns the .webp variant of an image URL when the file exists on disk.
 */

if (defined('IMAGE_HELPER_LOADED')) return;
define('IMAGE_HELPER_LOADED', true);

/**
 * Convert .jpg/.jpeg/.png URL to .webp if the WebP version exists on disk.
 */
function get_webp_url(string $url): string
{
    $webp = preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $url);
    if ($webp === $url) return $url; // no extension match

    $docRoot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\');
    $localPath = $docRoot . '/' . ltrim($webp, '/');

    if (file_exists($localPath)) {
        return $webp;
    }
    return $url; // fallback to original
}

/**
 * Emit a <picture> element with optional WebP source and lazy loading.
 */
function picture_tag(string $imgUrl, string $alt, string $class = '', string $extra = ''): string
{
    $webpUrl = get_webp_url($imgUrl);
    $altSafe = htmlspecialchars($alt, ENT_QUOTES, 'UTF-8');
    $imgSafe = htmlspecialchars($imgUrl, ENT_QUOTES, 'UTF-8');
    $cls     = $class ? ' class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '"' : '';
    $ext     = $extra ? ' ' . $extra : '';

    $html = '<picture>';
    if ($webpUrl !== $imgUrl) {
        $html .= '<source srcset="' . htmlspecialchars($webpUrl, ENT_QUOTES, 'UTF-8') . '" type="image/webp">';
    }
    $html .= '<img src="' . $imgSafe . '" alt="' . $altSafe . '"' . $cls . ' loading="lazy"' . $ext . '>';
    $html .= '</picture>';
    return $html;
}
