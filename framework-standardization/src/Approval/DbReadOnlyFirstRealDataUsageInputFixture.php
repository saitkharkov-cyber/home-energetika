<?php

namespace FrameworkStandardization\Approval;

class DbReadOnlyFirstRealDataUsageInputFixture
{
    public function getPreparedFixture()
    {
        $rows = array(
            array(
                'product_id' => 10001,
                'attribute_id' => 301,
                'attribute_name' => 'pump_diameter',
                'raw_value' => '75 mm',
                'normalized_value' => '75',
                'confidence' => 'high',
            ),
            array(
                'product_id' => 10002,
                'attribute_id' => 301,
                'attribute_name' => 'pump_diameter',
                'raw_value' => '90 mm',
                'normalized_value' => '90',
                'confidence' => 'high',
            ),
            array(
                'product_id' => 10003,
                'attribute_id' => 301,
                'attribute_name' => 'pump_diameter',
                'raw_value' => '110 mm',
                'normalized_value' => '110',
                'confidence' => 'medium',
            ),
            array(
                'product_id' => 10004,
                'attribute_id' => 301,
                'attribute_name' => 'pump_diameter',
                'raw_value' => '125 mm',
                'normalized_value' => '125',
                'confidence' => 'medium',
            ),
        );

        return array(
            'context' => 'pump_diameter',
            'source_mode' => 'local_readonly_dump_derived_test_fixture',
            'readonly' => 1,
            'max_rows' => 12,
            'rows' => $rows,
            'diagnostics' => $this->buildDiagnostics(count($rows)),
        );
    }

    public function getFirstRunSlice($limit = 2)
    {
        $fixture = $this->getPreparedFixture();
        $limit = (int) $limit;

        if ($limit < 1) {
            $limit = 1;
        }

        if ($limit > 2) {
            $limit = 2;
        }

        $rows = array_slice($fixture['rows'], 0, $limit);

        return array(
            'context' => $fixture['context'],
            'source' => $fixture['source_mode'],
            'source_mode' => $fixture['source_mode'],
            'readonly' => 1,
            'rows' => $rows,
            'diagnostics' => $this->buildDiagnostics(count($rows)),
        );
    }

    private function buildDiagnostics($rowsCount)
    {
        return array(
            'checker_mode' => 'standalone_first_real_data_usage_input_fixture',
            'context' => 'pump_diameter',
            'source_mode' => 'local_readonly_dump_derived_test_fixture',
            'rows_count' => (int) $rowsCount,
            'readonly' => 1,
            'sql_generated' => 0,
            'apply_plan_created' => 0,
            'safe_to_apply' => 0,
            'sql_apply_allowed' => 0,
            'production_ready' => 0,
        );
    }
}
