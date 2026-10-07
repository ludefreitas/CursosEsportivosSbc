<?php

namespace App\Services;

final class InternPermissionService
{
    public static function isRestricted(array $roles): bool
    {
        $slugs = array_column($roles, 'slug');
        return in_array('intern', $slugs, true)
            && array_intersect($slugs, ['teacher', 'master_admin', 'admin', 'supervisor', 'coordinator']) === [];
    }
}
