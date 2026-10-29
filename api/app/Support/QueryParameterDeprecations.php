<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Routing\Route;
use JustSteveKing\Toolbox\Deprecation\Contracts\DeprecationSource;
use JustSteveKing\Toolbox\Deprecation\Notice;
use Override;

/**
 * Deprecates a route only when it is called with a given query parameter (ADR-0008).
 *
 * A URI's query is part of what identifies the resource (RFC 3986, section 3), so
 * /contacts?organization_id=X is a resource of its own, and RFC 9745 lets it be deprecated without
 * /contacts being deprecated too. api-toolbox finds notices by route, which both share, so this
 * wraps its source and adds the notice for the query form. The header middleware and Kit's sunset
 * check both read this source, so the headers and the 410 after the sunset agree.
 */
final readonly class QueryParameterDeprecations implements DeprecationSource
{
    /**
     * By route name, then query parameter.
     *
     * @var array<string, array<string, array{deprecated: string, sunset: string, link: string}>>
     */
    public const array NOTICES = [
        'v1.contacts.index' => [
            'organization_id' => [
                'deprecated' => '2026-10-29T00:00:00Z',
                'sunset' => '2027-01-31T23:59:59Z',
                'link' => 'https://github.com/JustSteveKing/pingback/blob/main/architecture/adr/0008-the-organisation-filter-becomes-a-resource-and-is-deprecated.md',
            ],
        ],
    ];

    public function __construct(
        private DeprecationSource $routes,
    ) {}

    #[Override]
    public function forRoute(Route $route): ?Notice
    {
        // Read when asked, not held: the source is a singleton, and under Octane one serves many
        // requests.
        $request = app('request');

        foreach (self::NOTICES[(string) $route->getName()] ?? [] as $parameter => $notice) {
            if ($request->query->has($parameter)) {
                return new Notice(
                    deprecatedAt: Notice::date($notice['deprecated']),
                    deprecationLink: $notice['link'],
                    sunsetAt: Notice::date($notice['sunset']),
                );
            }
        }

        return $this->routes->forRoute($route);
    }
}
