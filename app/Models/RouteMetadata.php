<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Route Metadata Model
 *
 * Stores hierarchical metadata for navigation routes to enable global search.
 * Populated by the routes:scan artisan command.
 */
class RouteMetadata extends Model
{
    use HasFactory;

    protected $table = 'route_metadata';

    protected $fillable = [
        'route_name',
        'url',
        'title',
        'description',
        'section',
        'parent_section',
        'icon',
        'keywords',
        'roles',
        'permissions',
        'hierarchy_path',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'keywords' => 'array',
        'roles' => 'array',
        'permissions' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Scope to search routes by query
     */
    public function scopeSearch($query, string $search)
    {
        $searchLower = strtolower($search);

        return $query->where(function ($q) use ($search, $searchLower) {
            $q->where('title', 'LIKE', "%{$search}%")
              ->orWhere('description', 'LIKE', "%{$search}%")
              ->orWhere('section', 'LIKE', "%{$search}%")
              ->orWhere('parent_section', 'LIKE', "%{$search}%")
              ->orWhere('hierarchy_path', 'LIKE', "%{$search}%")
              // Also search in JSON keywords array
              ->orWhere('keywords', 'LIKE', "%{$searchLower}%");
        });
    }

    /**
     * Scope to filter by user's roles and permissions
     *
     * Routes are accessible if:
     * 1. No roles are specified (available to all)
     * 2. User has at least one of the specified roles
     * 3. User has required permission (if specified)
     */
    public function scopeForRoles($query, array $userRoles, ?object $user = null)
    {
        return $query->where(function ($q) use ($userRoles, $user) {
            // Routes with no role restrictions (empty array or null)
            $q->where(function ($sub) {
                $sub->whereNull('roles')
                    ->orWhere('roles', '[]')
                    ->orWhere('roles', 'null');
            });

            // Routes where user has at least one required role
            foreach ($userRoles as $role) {
                $q->orWhereJsonContains('roles', $role);
            }
        })->when($user, function ($q) use ($user) {
            // Additionally filter by permissions if user is provided
            return $q->where(function ($sub) use ($user) {
                $sub->where(function ($permSub) {
                    $permSub->whereNull('permissions')
                            ->orWhere('permissions', '[]')
                            ->orWhere('permissions', 'null');
                });

                // Check each required permission
                // This is done in PHP since it requires checking against user's actual permissions
            });
        });
    }

    /**
     * Filter a collection of routes by user's permissions
     * Call this after database query for permission-based filtering
     */
    public static function filterByPermissions($routes, $user)
    {
        if (!$user) {
            return $routes->filter(function ($route) {
                $permissions = $route->permissions ?? [];
                return empty($permissions);
            });
        }

        return $routes->filter(function ($route) use ($user) {
            $permissions = $route->permissions ?? [];

            // If no permissions required, allow access
            if (empty($permissions)) {
                return true;
            }

            // Check if user has any of the required permissions
            foreach ($permissions as $permission) {
                if ($user->can($permission)) {
                    return true;
                }
            }

            return false;
        });
    }

    /**
     * Check if the current user can access this route
     */
    public function isAccessibleBy($user): bool
    {
        // Check roles
        $roles = $this->roles ?? [];
        if (!empty($roles)) {
            $hasRole = false;
            foreach ($roles as $role) {
                if ($user->hasRole($role)) {
                    $hasRole = true;
                    break;
                }
            }
            if (!$hasRole) {
                return false;
            }
        }

        // Check permissions
        $permissions = $this->permissions ?? [];
        if (!empty($permissions)) {
            foreach ($permissions as $permission) {
                if ($user->can($permission)) {
                    return true;
                }
            }
            return false;
        }

        return true;
    }

    /**
     * Get the full hierarchy as an array
     */
    public function getHierarchyArrayAttribute(): array
    {
        return array_filter(explode(' > ', $this->hierarchy_path));
    }

    /**
     * Get formatted display with hierarchy
     */
    public function getDisplayTextAttribute(): string
    {
        return $this->hierarchy_path ?: $this->title;
    }
}
