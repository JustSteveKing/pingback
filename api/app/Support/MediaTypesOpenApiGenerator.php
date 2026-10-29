<?php

declare(strict_types=1);

namespace App\Support;

use Knuckles\Camel\Output\OutputEndpointData;
use Knuckles\Scribe\Writing\OpenApiSpecGenerators\OpenApiGenerator;

/**
 * Documents the media type of a response that is not JSON. Scribe calls any string example
 * text/plain, and #[Response] has no way to say otherwise.
 */
final class MediaTypesOpenApiGenerator extends OpenApiGenerator
{
    /**
     * By method and URI, then status.
     */
    private const array MEDIA_TYPES = [
        'GET v1/exports/{export}' => [200 => 'text/csv'],
    ];

    /**
     * @param  array<string, mixed>  $pathItem
     * @param  array<int, array{description: string, name: string, endpoints: OutputEndpointData[]}>  $groupedEndpoints
     * @return array<string, mixed>
     */
    public function pathItem(array $pathItem, array $groupedEndpoints, OutputEndpointData $endpoint): array
    {
        $types = self::MEDIA_TYPES[$endpoint->httpMethods[0] . ' ' . $endpoint->uri] ?? [];

        if ( ! is_array($pathItem['responses'] ?? null)) {
            return $pathItem;
        }

        foreach ($types as $status => $type) {
            $response = $pathItem['responses'][$status] ?? null;
            $content = is_array($response) ? ($response['content'] ?? null) : null;

            if (is_array($response) && is_array($content) && [] !== $content) {
                $response['content'] = [$type => reset($content)];
                $pathItem['responses'][$status] = $response;
            }
        }

        return $pathItem;
    }
}
