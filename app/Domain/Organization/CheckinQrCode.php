<?php

namespace App\Domain\Organization;

use App\Domain\Organization\Models\Location;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/** SVG QR code pointing at a location's public check-in page. */
class CheckinQrCode
{
    public function svg(Location $location, int $size = 400): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle($size, 2), new SvgImageBackEnd));

        return $writer->writeString($location->checkinUrl(qr: true));
    }
}
