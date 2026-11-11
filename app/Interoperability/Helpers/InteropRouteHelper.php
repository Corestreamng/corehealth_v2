<?php

namespace App\Interoperability\Helpers;

use Illuminate\Support\Facades\Route;

class InteropRouteHelper
{
    /**
     * Generate canonical FHIR resource URL using Laravel's route helper where defined.
     */
    public static function fhirRoute(string $resource, ?string $id = null): string
    {
        $action = $id ? '.read' : '.index';
        $kebabResource = strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $resource));
        $routeName = "fhir.{$kebabResource}{$action}";

        if (Route::has($routeName)) {
            return $id !== null ? route($routeName, ['id' => $id]) : route($routeName);
        }

        return url('/api/fhir/r4/' . $resource . ($id !== null ? '/' . $id : ''));
    }

    /**
     * Generate canonical openEHR ITS-REST URL using route helper.
     */
    public static function openEhrRoute(string $action, array $parameters = []): string
    {
        $routeName = "openehr.{$action}";
        if (Route::has($routeName)) {
            return route($routeName, $parameters);
        }

        return url('/api/openehr/v1/' . $action);
    }

    /**
     * Generate canonical OpenEMR compatibility URL using route helper.
     */
    public static function openEmrRoute(string $action, array $parameters = []): string
    {
        $routeName = "openemr.{$action}";
        if (Route::has($routeName)) {
            return route($routeName, $parameters);
        }

        return url('/apis/default/' . $action);
    }

    /**
     * Generate local system identifier URI using Laravel's url/route generator.
     */
    public static function identifierSystem(string $type): string
    {
        return url('/identifiers/' . $type);
    }
}
