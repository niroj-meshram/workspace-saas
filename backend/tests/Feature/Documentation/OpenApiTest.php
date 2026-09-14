<?php

use App\Enums\ActivityType;
use App\Enums\InvitationStatus;
use App\Enums\ProjectStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\WorkspaceRole;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Yaml;

/*
|--------------------------------------------------------------------------
| The OpenAPI document describes this API, not a past version of it
|--------------------------------------------------------------------------
|
| Hand-written documentation drifts silently. These tests fail the moment the
| routes and the specification disagree, in either direction.
|
*/

/**
 * @return array<string, mixed>
 */
function openapi(): array
{
    return Yaml::parseFile(base_path('docs/openapi.yaml'));
}

/**
 * Registered API routes as "METHOD /path", with Laravel's {param} placeholders.
 *
 * @return list<string>
 */
function registeredEndpoints(): array
{
    $endpoints = [];

    foreach (Route::getRoutes() as $route) {
        $uri = $route->uri();

        if (! Str::startsWith($uri, 'api/v1/')) {
            continue;
        }

        $path = '/'.Str::after($uri, 'api/v1/');

        foreach ($route->methods() as $method) {
            if (in_array($method, ['HEAD', 'OPTIONS'], true)) {
                continue;
            }

            $endpoints[] = strtoupper($method).' '.$path;
        }
    }

    sort($endpoints);

    return $endpoints;
}

/**
 * @return list<string>
 */
function documentedEndpoints(): array
{
    $endpoints = [];

    foreach (openapi()['paths'] as $path => $operations) {
        foreach (array_keys($operations) as $method) {
            if ($method === 'parameters') {
                continue;
            }

            $endpoints[] = strtoupper($method).' '.$path;
        }
    }

    sort($endpoints);

    return $endpoints;
}

it('is a valid OpenAPI 3.1 document', function () {
    $spec = openapi();

    expect($spec['openapi'])->toStartWith('3.1')
        ->and($spec['info']['title'])->toBe('Workspace SaaS API')
        ->and($spec['paths'])->not->toBeEmpty()
        ->and($spec['components']['schemas'])->not->toBeEmpty();
});

it('documents every API endpoint the application serves', function () {
    // The health probe is infrastructure, not part of the product API.
    $undocumented = array_diff(registeredEndpoints(), documentedEndpoints(), ['GET /health']);

    expect($undocumented)->toBeEmpty(
        'Undocumented endpoints: '.implode(', ', $undocumented)
    );
});

it('documents no endpoint the application does not serve', function () {
    $phantom = array_diff(documentedEndpoints(), registeredEndpoints());

    expect($phantom)->toBeEmpty(
        'Documented but not routed: '.implode(', ', $phantom)
    );
});

it('lists the same enum values the application uses', function (string $schema, string $enum) {
    $documented = openapi()['components']['schemas'][$schema]['enum'];
    $actual = array_column($enum::cases(), 'value');

    sort($documented);
    sort($actual);

    expect($documented)->toBe($actual);
})->with([
    'workspace roles' => ['WorkspaceRole', WorkspaceRole::class],
    'project statuses' => ['ProjectStatus', ProjectStatus::class],
    'task statuses' => ['TaskStatus', TaskStatus::class],
    'task priorities' => ['TaskPriority', TaskPriority::class],
    'invitation statuses' => ['InvitationStatus', InvitationStatus::class],
    'activity types' => ['ActivityType', ActivityType::class],
]);

it('lists the same morph aliases the application registers', function () {
    $documented = openapi()['components']['schemas']['Activity']['properties']['subject_type']['enum'];
    $actual = array_keys(Relation::morphMap());

    sort($documented);
    sort($actual);

    expect($documented)->toBe($actual);
});

it('describes the user resource without any credential field', function () {
    $properties = array_keys(openapi()['components']['schemas']['User']['properties']);

    expect($properties)->toBe(['id', 'name', 'email', 'created_at', 'updated_at'])
        ->and($properties)->not->toContain('password')
        ->and($properties)->not->toContain('remember_token');
});

it('never describes an invitation token or its hash', function () {
    $properties = array_keys(openapi()['components']['schemas']['Invitation']['properties']);

    expect($properties)->not->toContain('token')
        ->and($properties)->not->toContain('token_hash');
});
