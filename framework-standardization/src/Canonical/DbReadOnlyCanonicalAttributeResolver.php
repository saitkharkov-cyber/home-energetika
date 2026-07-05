<?php

namespace FrameworkStandardization\Canonical;

use FrameworkStandardization\Contract\CanonicalAttributeResolverInterface;
use FrameworkStandardization\Contract\ReadOnlyDbConnectionInterface;
use FrameworkStandardization\OpenCart\OpenCartTableName;

final class DbReadOnlyCanonicalAttributeResolver implements CanonicalAttributeResolverInterface
{
    private $db;
    private $tableName;
    private $mapping;

    public function __construct(ReadOnlyDbConnectionInterface $db, OpenCartTableName $tableName, array $mapping)
    {
        $this->db = $db;
        $this->tableName = $tableName;
        $this->mapping = $mapping;
    }

    public function resolve($canonicalCode)
    {
        try {
            return $this->resolveSafely($canonicalCode);
        } catch (\Exception $e) {
            return $this->failed('canonical_lookup_failed');
        }
    }

    private function resolveSafely($canonicalCode)
    {
        if (!$this->hasRequiredMapping()) {
            return $this->failed('canonical_lookup_failed');
        }

        if ((string)$canonicalCode !== (string)$this->mapping['canonical_code']) {
            return $this->failed('canonical_code_not_found');
        }

        if ((int)$this->mapping['category_id'] !== 11900213) {
            return $this->failed('scope_category_not_supported');
        }

        $language = $this->loadLanguage();

        if ($language === array()) {
            return $this->failed('language_id_not_found');
        }

        $targetAttribute = $this->loadTargetAttribute();

        if ($targetAttribute === array()) {
            return $this->failed('target_attribute_id_not_found');
        }

        $usageCount = $this->loadUsageCount();

        if ($usageCount <= 0) {
            return $this->failed('target_attribute_not_used_in_scope');
        }

        $warnings = array();

        if (isset($this->mapping['expected_usage_count']) && (int)$this->mapping['expected_usage_count'] !== $usageCount) {
            $warnings[] = 'target_attribute_usage_count_changed';
        }

        return $this->succeeded($warnings);
    }

    private function hasRequiredMapping()
    {
        $requiredKeys = array(
            'canonical_code',
            'category_id',
            'language_id',
            'target_attribute_id',
            'target_attribute_name',
            'target_attribute_group_id',
            'target_attribute_group_name',
        );

        foreach ($requiredKeys as $key) {
            if (!array_key_exists($key, $this->mapping)) {
                return false;
            }
        }

        return true;
    }

    private function loadLanguage()
    {
        $sql = "SELECT language_id, name, code, status";
        $sql .= " FROM " . $this->tableName->name('language');
        $sql .= " WHERE language_id = :language_id";

        $row = $this->db->fetchOne($sql, array(
            ':language_id' => (int)$this->mapping['language_id'],
        ));

        if ($row === array()) {
            return array();
        }

        if (isset($row['status']) && (int)$row['status'] !== 1) {
            return array();
        }

        return $row;
    }

    private function loadTargetAttribute()
    {
        $sql = "SELECT";
        $sql .= " a.attribute_id,";
        $sql .= " a.attribute_group_id,";
        $sql .= " ad.name AS attribute_name,";
        $sql .= " agd.name AS attribute_group_name";
        $sql .= " FROM " . $this->tableName->name('attribute') . " a";
        $sql .= " JOIN " . $this->tableName->name('attribute_description') . " ad ON ad.attribute_id = a.attribute_id";
        $sql .= " JOIN " . $this->tableName->name('attribute_group') . " ag ON ag.attribute_group_id = a.attribute_group_id";
        $sql .= " JOIN " . $this->tableName->name('attribute_group_description') . " agd ON agd.attribute_group_id = ag.attribute_group_id";
        $sql .= " WHERE a.attribute_id = :target_attribute_id";
        $sql .= " AND a.attribute_group_id = :target_attribute_group_id";
        $sql .= " AND ad.language_id = :language_id";
        $sql .= " AND ad.name = :target_attribute_name";
        $sql .= " AND agd.language_id = :language_id";
        $sql .= " AND agd.name = :target_attribute_group_name";

        return $this->db->fetchOne($sql, array(
            ':target_attribute_id' => (int)$this->mapping['target_attribute_id'],
            ':target_attribute_group_id' => (int)$this->mapping['target_attribute_group_id'],
            ':language_id' => (int)$this->mapping['language_id'],
            ':target_attribute_name' => (string)$this->mapping['target_attribute_name'],
            ':target_attribute_group_name' => (string)$this->mapping['target_attribute_group_name'],
        ));
    }

    private function loadUsageCount()
    {
        $sql = "SELECT COUNT(DISTINCT p.product_id) AS product_count";
        $sql .= " FROM " . $this->tableName->name('product') . " p";
        $sql .= " JOIN " . $this->tableName->name('product_to_category') . " p2c ON p2c.product_id = p.product_id";
        $sql .= " JOIN " . $this->tableName->name('product_attribute') . " pa ON pa.product_id = p.product_id";
        $sql .= " WHERE p2c.category_id = :category_id";
        $sql .= " AND pa.attribute_id = :target_attribute_id";
        $sql .= " AND pa.language_id = :language_id";

        $row = $this->db->fetchOne($sql, array(
            ':category_id' => (int)$this->mapping['category_id'],
            ':target_attribute_id' => (int)$this->mapping['target_attribute_id'],
            ':language_id' => (int)$this->mapping['language_id'],
        ));

        if ($row === array() || !isset($row['product_count'])) {
            return 0;
        }

        return (int)$row['product_count'];
    }

    private function succeeded(array $warnings)
    {
        return array(
            'found' => 1,
            'canonical' => array(
                'canonical_id' => 1,
                'canonical_code' => (string)$this->mapping['canonical_code'],
                'target_attribute_id' => (int)$this->mapping['target_attribute_id'],
                'target_attribute_name' => (string)$this->mapping['target_attribute_name'],
                'target_attribute_group_id' => (int)$this->mapping['target_attribute_group_id'],
                'target_attribute_group_name' => (string)$this->mapping['target_attribute_group_name'],
                'status' => 'active',
                'locked' => 1,
                'source' => 'local_dump_db_readonly',
            ),
            'errors' => array(),
            'warnings' => $warnings,
            'source' => 'local_dump_db_readonly',
        );
    }

    private function failed($errorCode)
    {
        return array(
            'found' => 0,
            'canonical' => array(),
            'errors' => array($errorCode),
            'warnings' => array(),
            'source' => 'local_dump_db_readonly',
        );
    }
}
