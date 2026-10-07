<?php

namespace App\Services;

final class InternPermissionService
{
    public static function studentData(array $person): array
    {
        return array_diff_key($person, array_flip([
            'conta_id', 'conta_ativa', 'situacao_certificados',
            'condition_indicators', 'health_certificate_indicators',
        ]));
    }

    public static function isRestricted(array $roles): bool
    {
        $slugs = array_column($roles, 'slug');
        return in_array('intern', $slugs, true)
            && array_intersect($slugs, ['teacher', 'master_admin', 'admin', 'supervisor', 'coordinator']) === [];
    }
}
