<?php

use App\Holdings;
use GuzzleHttp\Psr7\Response;


class HoldingsTest extends TestCase
{
    public function testAvailableAtVb()
    {
        $item1 = (object) [
            'library' => 'UBO1030317',
            'status' => 'available',
            'collection_code' => 'n/a',
        ];
        $item2 = (object) [
            'library' => 'UBO1030310',
            'status' => 'available',
            'collection_code' => 'n/a',
        ];

        $this->assertEquals(
            [$item2],
            Holdings::filter([$item1, $item2])
        );
    }

    public function testAvailableAtInf()
    {
        $item1 = (object) [
            'library' => 'UBO1030317',
            'status' => 'available',
            'collection_code' => 'n/a',
        ];
        $item2 = (object) [
            'library' => 'UBO1030310',
            'status' => 'unavailable',
            'collection_code' => 'n/a',
        ];

        $this->assertEquals(
            [$item2, $item1],
            Holdings::filter([$item1, $item2])
        );
    }

    public function testAvailableAtOtherLib()
    {
        $item1 = (object) [
            'library' => 'UBO1030300',
            'status' => 'available',
            'collection_code' => 'n/a',
        ];
        $item2 = (object) [
            'library' => 'UBO1030310',
            'status' => 'unavailable',
            'collection_code' => 'k00423', // Boksaml.
        ];

        $this->assertEquals(
            [$item2, $item1],
            Holdings::filter([$item1, $item2])
        );
    }

    public function testBoksamlOverPensum()
    {
        $item1 = (object) [
            'library' => 'UBO1030310',
            'status' => 'available',
            'collection_code' => 'k00471', // Pensum
        ];
        $item2 = (object) [
            'library' => 'UBO1030310',
            'status' => 'available',
            'collection_code' => 'k00423', // Boksaml.
        ];

        $this->assertEquals(
            [$item2],
            Holdings::filter([$item1, $item2])
        );
    }

    public function testPensumOverBoksamlIfAvailable()
    {
        $item1 = (object) [
            'library' => 'UBO1030310',
            'status' => 'available',
            'collection_code' => 'k00471', // Pensum
        ];
        $item2 = (object) [
            'library' => 'UBO1030310',
            'status' => 'unavailable',
            'collection_code' => 'k00423', // Boksaml.
        ];

        $this->assertEquals(
            [$item1],
            Holdings::filter([$item1, $item2])
        );
    }

}
