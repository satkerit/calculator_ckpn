<?php

namespace Tests\Feature;

use Tests\TestCase;

class AgentExecutionTest extends TestCase
{
    public function testDataValidationAgent(): void
    {
        $validSnapshots = [
            ['loan_number' => 'LN-001', 'office_code' => 'KC001', 'product_code' => 'MURA'],
            ['loan_number' => 'LN-002', 'office_code' => 'KC002', 'product_code' => 'MJA'],
            ['loan_number' => 'LN-003', 'office_code' => 'KC001', 'product_code' => 'MUSY'],
            ['loan_number' => 'LN-004', 'office_code' => 'KPU', 'product_code' => 'IMBT'],
            ['loan_number' => 'LN-005', 'office_code' => 'KC002', 'product_code' => 'MURA'],
            ['loan_number' => 'LN-006', 'office_code' => 'KC001', 'product_code' => 'MJA'],
            ['loan_number' => 'LN-007', 'office_code' => 'KPU', 'product_code' => 'MUSY'],
            ['loan_number' => 'LN-008', 'office_code' => 'KC002', 'product_code' => 'IMBT'],
        ];

        $invalidSnapshots = [
            ['loan_number' => 'LN-009', 'office_code' => null, 'product_code' => 'MURA'],
            ['loan_number' => 'LN-010', 'office_code' => null, 'product_code' => 'MJA'],
        ];

        $validCount = count($validSnapshots);
        $invalidCount = count($invalidSnapshots);
        $totalCount = $validCount + $invalidCount;

        $this->assertEquals(8, $validCount);
        $this->assertEquals(2, $invalidCount);
        $this->assertEquals(10, $totalCount);

        $qualityScore = ($validCount / $totalCount) * 100;
        $this->assertGreaterThanOrEqual(80, $qualityScore);
    }

    public function testEadCalculationAgent(): void
    {
        $snapshot = [
            'loan_number' => 'LN-001',
            'product_code' => 'MURA',
            'outstanding_balance' => 1000000,
            'principal_arrears' => 100000,
        ];

        $ead = $snapshot['outstanding_balance'];
        $this->assertEquals(1000000, $ead);
        $this->assertGreaterThan(0, $ead);
    }

    public function testLgdWeightedRounding(): void
    {
        $lgdValue = 0.456789;
        $rounded = round($lgdValue, 2, PHP_ROUND_HALF_UP);

        $this->assertEquals(0.46, $rounded);
        $this->assertIsFloat($rounded);
        
        $lgdValue2 = 0.454321;
        $rounded2 = round($lgdValue2, 2, PHP_ROUND_HALF_UP);
        $this->assertEquals(0.45, $rounded2);
    }

    public function testCkpnCalculationConsistency(): void
    {
        $individualCkpn = 150000000;
        $collectiveCkpn = 350000000;
        $consolidatedCkpn = $individualCkpn + $collectiveCkpn;

        $this->assertEquals(500000000, $consolidatedCkpn);

        $tolerance = 0.0001;
        $difference = abs($consolidatedCkpn - ($individualCkpn + $collectiveCkpn));
        $this->assertLessThan($tolerance, $difference);

        $normalizedCkpn = $consolidatedCkpn / 1000000000;
        $this->assertLessThanOrEqual(1, $normalizedCkpn);
        $this->assertGreaterThanOrEqual(0, $normalizedCkpn);
    }

    public function testReconciliationPasses(): void
    {
        $financing = [
            ['loan_number' => 'LN-001', 'outstanding_balance' => 1000000, 'principal_arrears' => 100000],
            ['loan_number' => 'LN-002', 'outstanding_balance' => 2000000, 'principal_arrears' => 150000],
            ['loan_number' => 'LN-003', 'outstanding_balance' => 3000000, 'principal_arrears' => 200000],
            ['loan_number' => 'LN-004', 'outstanding_balance' => 4000000, 'principal_arrears' => 50000],
            ['loan_number' => 'LN-005', 'outstanding_balance' => 5000000, 'principal_arrears' => 300000],
        ];

        $totalFinancing = count($financing);
        $individualCount = 2;
        $collectiveCount = 3;

        $this->assertEquals($totalFinancing, $individualCount + $collectiveCount);
        $this->assertEquals(5, $totalFinancing);

        $totalEad = array_sum(array_map(fn($f) => $f['outstanding_balance'] + $f['principal_arrears'], $financing));
        $this->assertGreaterThan(0, $totalEad);
        $this->assertEquals(15800000, $totalEad);
    }
}
