<?php

namespace App\Twig;

use App\Service\ImagePathService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class ImagePathExtension extends AbstractExtension
{
    private ImagePathService $imagePathService;

    public function __construct(ImagePathService $imagePathService)
    {
        $this->imagePathService = $imagePathService;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('image_full_url', [$this->imagePathService, 'generateFullPath']),
        ];
    }
}
