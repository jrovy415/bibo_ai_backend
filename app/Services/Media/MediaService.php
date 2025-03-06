<?php

namespace App\Services\Media;


use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaService implements MediaServiceInterface
{
    public function stream(Media $media): JsonResponse | StreamedResponse
    {
        $allowedMimeTypes = ['image/jpeg','image/png'];

        if (!in_array($media->mime_type, $allowedMimeTypes)) {
            return response()->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        return $media->toInlineResponse(request());
    }
}
