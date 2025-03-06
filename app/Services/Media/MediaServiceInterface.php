<?php


namespace App\Services\Media;


use Spatie\MediaLibrary\MediaCollections\Models\Media;

interface MediaServiceInterface
{
    public function stream(Media $media);
}