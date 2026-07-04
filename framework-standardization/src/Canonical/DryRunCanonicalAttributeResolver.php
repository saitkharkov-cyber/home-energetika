<?php

namespace FrameworkStandardization\Canonical;

use FrameworkStandardization\Contract\CanonicalAttributeResolverInterface;

final class DryRunCanonicalAttributeResolver implements CanonicalAttributeResolverInterface
{
    public function resolve($canonicalCode)
    {
        if ($canonicalCode !== 'pump_diameter') {
            return array(
                'found' => 0,
                'canonical' => array(),
                'errors' => array('canonical_code_not_found'),
                'warnings' => array(),
                'source' => 'dry_run_fixture',
            );
        }

        return array(
            'found' => 1,
            'canonical' => array(
                'canonical_id' => 1,
                'canonical_code' => 'pump_diameter',
                'target_attribute_id' => 0,
                'target_attribute_name' => 'Dry-run pump diameter',
                'target_attribute_group_id' => 0,
                'target_attribute_group_name' => 'Dry-run attributes',
                'status' => 'active',
                'locked' => 1,
                'source' => 'dry_run_fixture',
            ),
            'errors' => array(),
            'warnings' => array(),
            'source' => 'dry_run_fixture',
        );
    }
}
