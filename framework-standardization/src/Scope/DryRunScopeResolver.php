<?php

namespace FrameworkStandardization\Scope;

use FrameworkStandardization\Contract\ScopeResolverInterface;

final class DryRunScopeResolver implements ScopeResolverInterface
{
    public function resolve(array $scope)
    {
        $scopeType = isset($scope['type']) ? $scope['type'] : '';

        if ($scopeType !== 'category') {
            return $this->failed(array('unsupported_scope_type'));
        }

        $categoryId = isset($scope['category_id']) ? (int)$scope['category_id'] : 0;

        if ($categoryId !== 11900213) {
            return $this->failed(array('scope_category_not_found'));
        }

        $products = array(
            array(
                'product_id' => 0,
                'model' => 'dry-run-product',
                'name' => 'Dry-run product',
                'category_ids' => array(11900213),
                'source' => 'dry_run_fixture',
            ),
        );

        if ($products === array()) {
            return $this->failed(array('scope_products_not_found'));
        }

        return array(
            'found' => 1,
            'scope' => array(
                'type' => 'category',
                'category_id' => 11900213,
                'category_name' => 'Скважинные насосы',
                'include_subcategories' => 1,
                'product_ids' => array(0),
                'product_count' => 1,
                'source' => 'dry_run_fixture',
            ),
            'products' => $products,
            'errors' => array(),
            'warnings' => array(),
            'source' => 'dry_run_fixture',
        );
    }

    private function failed(array $errors)
    {
        return array(
            'found' => 0,
            'scope' => array(),
            'products' => array(),
            'errors' => $errors,
            'warnings' => array(),
            'source' => 'dry_run_fixture',
        );
    }
}
