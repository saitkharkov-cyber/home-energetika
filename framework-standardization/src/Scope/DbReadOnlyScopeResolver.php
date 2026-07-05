<?php

namespace FrameworkStandardization\Scope;

use FrameworkStandardization\Contract\ReadOnlyDbConnectionInterface;
use FrameworkStandardization\Contract\ScopeResolverInterface;
use FrameworkStandardization\OpenCart\OpenCartTableName;

final class DbReadOnlyScopeResolver implements ScopeResolverInterface
{
    private $db;
    private $tableName;
    private $runtimeContext;

    public function __construct(ReadOnlyDbConnectionInterface $db, OpenCartTableName $tableName, array $runtimeContext)
    {
        $this->db = $db;
        $this->tableName = $tableName;
        $this->runtimeContext = $runtimeContext;
    }

    public function resolve(array $scope)
    {
        $scopeType = isset($scope['type']) ? $scope['type'] : '';

        if ($scopeType !== 'category') {
            return $this->failed(array('unsupported_scope_type'));
        }

        if (!$this->isSupportedRuntimeContext()) {
            return $this->failed(array('scope_category_not_supported'));
        }

        $categoryId = isset($scope['category_id']) ? (int)$scope['category_id'] : 0;

        if ($categoryId !== 11900213) {
            return $this->failed(array('scope_category_not_supported'));
        }

        $category = $this->loadCategory($categoryId);

        if ($category === array()) {
            return $this->failed(array('scope_category_not_found'));
        }

        $categoryDescription = $this->loadCategoryDescription($categoryId);

        if ($categoryDescription === array()) {
            return $this->failed(array('scope_category_description_not_found'));
        }

        $products = $this->loadProducts($categoryId);

        if ($products === array()) {
            return $this->failed(array('scope_products_not_found'));
        }

        $productIds = array();

        foreach ($products as $product) {
            $productIds[] = (int)$product['product_id'];
        }

        return array(
            'found' => 1,
            'scope' => array(
                'type' => 'category',
                'category_id' => $categoryId,
                'category_name' => $categoryDescription['name'],
                'product_ids' => $productIds,
                'product_count' => count($products),
                'source' => $this->getSource(),
            ),
            'products' => $products,
            'errors' => array(),
            'warnings' => array(),
            'source' => $this->getSource(),
        );
    }

    private function isSupportedRuntimeContext()
    {
        if (!isset($this->runtimeContext['language_id']) || (int)$this->runtimeContext['language_id'] !== 1) {
            return false;
        }

        if (!isset($this->runtimeContext['expected_category_id']) || (int)$this->runtimeContext['expected_category_id'] !== 11900213) {
            return false;
        }

        if (!isset($this->runtimeContext['expected_category_name']) || $this->runtimeContext['expected_category_name'] !== 'Скважинные насосы') {
            return false;
        }

        if (!isset($this->runtimeContext['source']) || $this->runtimeContext['source'] !== 'local_dump_db_readonly') {
            return false;
        }

        return true;
    }

    private function loadCategory($categoryId)
    {
        $sql = "SELECT category_id";
        $sql .= " FROM " . $this->tableName->name('category');
        $sql .= " WHERE category_id = :category_id";

        return $this->db->fetchOne($sql, array(
            ':category_id' => (int)$categoryId,
        ));
    }

    private function loadCategoryDescription($categoryId)
    {
        $sql = "SELECT category_id, name";
        $sql .= " FROM " . $this->tableName->name('category_description');
        $sql .= " WHERE category_id = :category_id";
        $sql .= " AND language_id = :language_id";
        $sql .= " AND name = :category_name";

        return $this->db->fetchOne($sql, array(
            ':category_id' => (int)$categoryId,
            ':language_id' => (int)$this->runtimeContext['language_id'],
            ':category_name' => (string)$this->runtimeContext['expected_category_name'],
        ));
    }

    private function loadProducts($categoryId)
    {
        $sql = "SELECT";
        $sql .= " p.product_id,";
        $sql .= " p.model,";
        $sql .= " p.status,";
        $sql .= " p.quantity,";
        $sql .= " pd.name";
        $sql .= " FROM " . $this->tableName->name('product') . " p";
        $sql .= " JOIN " . $this->tableName->name('product_to_category') . " p2c ON p2c.product_id = p.product_id";
        $sql .= " JOIN " . $this->tableName->name('product_description') . " pd ON pd.product_id = p.product_id";
        $sql .= " WHERE p2c.category_id = :category_id";
        $sql .= " AND pd.language_id = :language_id";
        $sql .= " ORDER BY p.product_id";

        $rows = $this->db->fetchAll($sql, array(
            ':category_id' => (int)$categoryId,
            ':language_id' => (int)$this->runtimeContext['language_id'],
        ));

        $products = array();

        foreach ($rows as $row) {
            $products[] = array(
                'product_id' => (int)$row['product_id'],
                'model' => isset($row['model']) ? (string)$row['model'] : '',
                'name' => isset($row['name']) ? (string)$row['name'] : '',
                'status' => isset($row['status']) ? (int)$row['status'] : 0,
                'quantity' => isset($row['quantity']) ? (int)$row['quantity'] : 0,
                'category_ids' => array((int)$categoryId),
                'source' => $this->getSource(),
            );
        }

        return $products;
    }

    private function getSource()
    {
        return isset($this->runtimeContext['source']) ? $this->runtimeContext['source'] : 'local_dump_db_readonly';
    }

    private function failed(array $errors)
    {
        return array(
            'found' => 0,
            'scope' => array(),
            'products' => array(),
            'errors' => $errors,
            'warnings' => array(),
            'source' => $this->getSource(),
        );
    }
}
