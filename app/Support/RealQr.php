<?php

namespace App\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * Real, scannable QR codes as inline SVG (pure PHP — no gd/imagick needed).
 */
class RealQr
{
    /** Return an inline SVG (no XML prolog) encoding $data. */
    public static function svg(string $data, int $size = 320): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($size, 1),
            new SvgImageBackEnd()
        );
        $svg = (new Writer($renderer))->writeString($data);

        // strip the XML prolog so the SVG embeds cleanly inside HTML
        $svg = preg_replace('/^<\?xml.*?\?>\s*/s', '', $svg);

        // let the SVG scale to its container (keep the viewBox for aspect ratio)
        return preg_replace('/\swidth="\d+"\sheight="\d+"/', ' width="100%" height="100%"', $svg, 1);
    }
}
