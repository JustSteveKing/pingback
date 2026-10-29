<?php

declare(strict_types=1);

namespace App\Support;

use Knuckles\Camel\Output\OutputEndpointData;
use Knuckles\Scribe\Writing\OpenApiSpecGenerators\OpenApiGenerator;

/**
 * Marks deprecated fields `deprecated: true` (OpenAPI 3.0, Schema Object) in the request and
 * response schemas of the endpoints that carry them. A field has no HTTP header of its own:
 * RFC 9745's Deprecation is about whole resources, so the contract is where a field's
 * deprecation is said (ADR-0007).
 */
final class DeprecatedFieldsOpenApiGenerator extends OpenApiGenerator
{
    /**
     * Deprecated fields, by the URI prefix of the endpoints whose bodies carry them.
     */
    public const array FIELDS = [
        'v1/contacts' => ['phone'],
        'v1/organizations/{organization}/contacts' => ['phone'],
    ];

    /**
     * @param  array<string, mixed>  $pathItem
     * @param  array<int, array{description: string, name: string, endpoints: OutputEndpointData[]}>  $groupedEndpoints
     * @return array<string, mixed>
     */
    public function pathItem(array $pathItem, array $groupedEndpoints, OutputEndpointData $endpoint): array
    {
        $fields = [];

        foreach (self::FIELDS as $prefix => $names) {
            if (str_starts_with($endpoint->uri, $prefix)) {
                $fields = [...$fields, ...$names];
            }
        }

        if ([] === $fields) {
            return $pathItem;
        }

        foreach (['requestBody', 'responses'] as $part) {
            if (is_array($pathItem[$part] ?? null)) {
                $pathItem[$part] = self::mark($pathItem[$part], $fields);
            }
        }

        return $pathItem;
    }

    /**
     * @param  array<array-key, mixed>  $node
     * @param  list<string>  $fields
     * @return array<array-key, mixed>
     */
    private static function mark(array $node, array $fields): array
    {
        if (is_array($node['properties'] ?? null)) {
            foreach ($fields as $field) {
                if (is_array($node['properties'][$field] ?? null)) {
                    $node['properties'][$field]['deprecated'] = true;
                }
            }
        }

        foreach ($node as $key => $child) {
            if (is_array($child)) {
                $node[$key] = self::mark($child, $fields);
            }
        }

        return $node;
    }
}
