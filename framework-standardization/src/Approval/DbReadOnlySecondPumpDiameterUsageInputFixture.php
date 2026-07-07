<?php

namespace FrameworkStandardization\Approval;

class DbReadOnlySecondPumpDiameterUsageInputFixture
{
    public function getPreparedFixture()
    {
        $rows = array(
            array(
                'product_id' => 20021,
                'attribute_id' => 301,
                'attribute_name' => 'pump_diameter',
                'raw_value' => 'DN 65',
                'normalized_value' => '65',
                'confidence' => 'medium',
            ),
            array(
                'product_id' => 20022,
                'attribute_id' => 301,
                'attribute_name' => 'pump_diameter',
                'raw_value' => '80mm',
                'normalized_value' => '80',
                'confidence' => 'high',
            ),
            array(
                'product_id' => 20023,
                'attribute_id' => 301,
                'attribute_name' => 'pump_diameter',
                'raw_value' => 'diameter 100 mm',
                'normalized_value' => '100',
                'confidence' => 'medium',
            ),
            array(
                'product_id' => 20024,
                'attribute_id' => 301,
                'attribute_name' => 'pump_diameter',
                'raw_value' => 'D 115 mm',
                'normalized_value' => '115',
                'confidence' => 'medium',
            ),
            array(
                'product_id' => 20025,
                'attribute_id' => 301,
                'attribute_name' => 'pump_diameter',
                'raw_value' => '1 1/4 inch / 32 mm',
                'normalized_value' => '32',
                'confidence' => 'low',
            ),
            array(
                'product_id' => 20026,
                'attribute_id' => 301,
                'attribute_name' => 'pump_diameter',
                'raw_value' => '130mm outlet',
                'normalized_value' => '130',
                'confidence' => 'medium',
            ),
            array(
                'product_id' => 20027,
                'attribute_id' => 301,
                'attribute_name' => 'pump_diameter',
                'raw_value' => 'DN150',
                'normalized_value' => '150',
                'confidence' => 'medium',
            ),
            array(
                'product_id' => 20028,
                'attribute_id' => 301,
                'attribute_name' => 'pump_diameter',
                'raw_value' => '200 mm flange',
                'normalized_value' => '200',
                'confidence' => 'low',
            ),
        );

        return array(
            'context' => 'pump_diameter',
            'source_mode' => 'local_readonly_dump_derived_test_fixture',
            'source_marker' => 'second_pump_diameter_controlled_sample',
            'readonly' => 1,
            'max_rows' => 12,
            'rows' => $rows,
            'diagnostics' => $this->buildDiagnostics(count($rows)),
        );
    }

    public function getFirstRunSlice($limit = 2)
    {
        $fixture = $this->getPreparedFixture();
        $limit = (int)$limit;

        if ($limit < 1) {
            $limit = 1;
        }

        if ($limit > 2) {
            $limit = 2;
        }

        $rows = array_slice($fixture['rows'], 0, $limit);

        return $this->buildReadonlyInput($fixture, $rows);
    }

    public function getFullBatch()
    {
        $fixture = $this->getPreparedFixture();
        $rows = $fixture['rows'];

        if (count($rows) > $fixture['max_rows']) {
            $rows = array_slice($rows, 0, $fixture['max_rows']);
        }

        return $this->buildReadonlyInput($fixture, $rows);
    }

    private function buildReadonlyInput($fixture, $rows)
    {
        return array(
            'context' => $fixture['context'],
            'source' => $fixture['source_marker'],
            'source_mode' => $fixture['source_mode'],
            'source_marker' => $fixture['source_marker'],
            'readonly' => 1,
            'rows' => $rows,
            'diagnostics' => $this->buildDiagnostics(count($rows)),
        );
    }

    private function buildDiagnostics($rowsCount)
    {
        return array(
            'checker_mode' => 'standalone_second_pump_diameter_usage_input_fixture',
            'context' => 'pump_diameter',
            'source_mode' => 'local_readonly_dump_derived_test_fixture',
            'source_marker' => 'second_pump_diameter_controlled_sample',
            'rows_count' => (int)$rowsCount,
            'readonly' => 1,
            'sql_generated' => 0,
            'apply_plan_created' => 0,
            'safe_to_apply' => 0,
            'sql_apply_allowed' => 0,
            'production_ready' => 0,
        );
    }
}
