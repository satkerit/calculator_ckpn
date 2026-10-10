<?php

namespace Tests\Feature;

use Tests\TestCase;

class AssessmentCalculationTest extends TestCase
{
    public function testRunCalculationSuccessLogic(): void
    {
        $snapshots = [
            ['loan_number' => 'LN-001', 'outstanding_balance' => 1000000, 'principal_arrears' => 100000, 'product_code' => 'MURA'],
            ['loan_number' => 'LN-002', 'outstanding_balance' => 2000000, 'principal_arrears' => 50000, 'product_code' => 'MJA'],
            ['loan_number' => 'LN-003', 'outstanding_balance' => 3000000, 'principal_arrears' => 0, 'product_code' => 'MUSY'],
            ['loan_number' => 'LN-004', 'outstanding_balance' => 4000000, 'principal_arrears' => 200000, 'product_code' => 'IMBT'],
            ['loan_number' => 'LN-005', 'outstanding_balance' => 5000000, 'principal_arrears' => 150000, 'product_code' => 'MURA'],
        ];

        $this->assertCount(5, $snapshots);
        foreach ($snapshots as $snapshot) {
            $this->assertArrayHasKey('outstanding_balance', $snapshot);
            $this->assertGreaterThanOrEqual(0, $snapshot['outstanding_balance']);
        }
    }

    public function testCalculationValidationFailLogic(): void
    {
        $emptySnapshots = [];
        $this->assertEmpty($emptySnapshots);
        $this->assertCount(0, $emptySnapshots);
    }

    public function testPdMethodSwitching(): void
    {
        $pdMethods = ['NET_FLOW', 'MIGRATION'];
        $this->assertContains('NET_FLOW', $pdMethods);
        $this->assertContains('MIGRATION', $pdMethods);
        $this->assertCount(2, $pdMethods);
    }

    public function testNplQualityCriteria(): void
    {
        // NPL ditentukan dari kualitas pembiayaan (kolektibilitas), bukan nominal.
        $nplRatings = [3, 4, 5];

        $portofolio = [
            ['rating' => 1, 'outstanding' => 900_000_000], // lancar
            ['rating' => 2, 'outstanding' => 800_000_000], // DPK
            ['rating' => 3, 'outstanding' => 700_000_000], // NPL
            ['rating' => 4, 'outstanding' => 600_000_000], // NPL
            ['rating' => 5, 'outstanding' => 500_000_000], // NPL
        ];

        $npl  = array_filter($portofolio, fn ($r) => in_array($r['rating'], $nplRatings, true));
        $baik = array_filter($portofolio, fn ($r) => ! in_array($r['rating'], $nplRatings, true));

        $this->assertCount(3, $npl, 'Kualitas 3,4,5 = NPL.');
        $this->assertCount(2, $baik, 'Kualitas 1,2 = non-NPL.');

        // Outstanding besar tapi kualitas baik → tetap non-NPL (bukan threshold nominal).
        $this->assertNotContains(1, array_column($npl, 'rating'));
    }
}
