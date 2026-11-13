<?php

namespace App\Services\Photos;

/** O que o Lightroom gravou no JPG: quem aparece e a orientação EXIF (1 a 8). */
final readonly class PhotoMetadata
{
    /** @param  list<TaggedPerson>  $people */
    public function __construct(public array $people, public int $orientation = 1) {}
}
