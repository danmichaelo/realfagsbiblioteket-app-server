<?php

namespace App;
use GuzzleHttp\Psr7\Response;


class Record
{
    static $libraries = [
        'UBO1030310' => [
            'name' => 'Realfagsbiblioteket',
            'zone' => 'local',
            'collections' => [
                // Open stack collections
                // sorted in order of increasing preference
                //('Pensum' is less preferred than the rest)
                '-----',   // (no match)
                'k00471',  // UREAL Pensum         [least preferred]
                'k00460',  // UREAL Laveregrad
                'k00413',  // UREAL Astr.
                'k00421',  // UREAL Biol.
                'k00447',  // UREAL Geo.
                'k00449',  // UREAL Geol.
                'k00426',  // UREAL Farm.
                'k00457',  // UREAL Kjem.
                'k00440',  // UREAL Fys.
                'k00465',  // UREAL Mat.
                'k00475',  // UREAL Samling 42
                'k00477',  // UREAL SciFi
                'k00423',  // UREAL Boksamling    [most preferred]
            ],
        ],
        'UBO1030500' => [
            'name' => 'Naturhistorisk museum',
            'zone' => 'satellite',
        ],
        'UBO1030317' => [
            'name' => 'Informatikkbiblioteket',
            'zone' => 'satellite',
        ],
    ];

    static public function process(&$record, $apiVersion = 1)
    {
        if ($apiVersion <= 1) {
            return;
        }

        unset($record->status);
        unset($record->frbr_type);

        $record->material = self::getReducedMaterialType($record);

        if ($record->type == 'record') {
            unset($record->frbr_group_id);
        }

        // Simplify since we don't have deduplication enabled
        $record->holdings = [];
        if (isset($record->components)) {
            $record->holdings = $record->components[0]->holdings;
            $record->category = $record->components[0]->category;
            $record->alma_id = $record->alma_id;
            unset($record->components);

            $record->holdings = Holdings::process($record->holdings);
        }

        $urls = [];
        foreach ($record->urls as $urlObj) {
            if ($urlObj->type == 'Alma-E' && in_array('UBO', $urlObj->access)) {
                $urls[] = $urlObj;
            } else if ($urlObj->type != 'Alma-E'){
                $urls[] = $urlObj;
            }
        }

        $record->urls = $urls;
    }
    /**
     * Reduce material type from array to string
     * Should work well as long as we don't have
     * deduplicated records! For FRBR records, the
     * value will just be the value for one of the records,
     * and as such should be ignored.
     */
    static protected function getReducedMaterialType(&$record)
    {
        if (count($record->material) == 0) {
            return null;
        }
        if (count($record->material) == 1) {
            return $record->material[0];
        }

        if (($key = array_search('books', $record->material)) !== false) {
            unset($record->material[$key]);
        }

        if (in_array('print-books', $record->material)) {
            return 'print-books';
        } else if (in_array('e-books', $record->material)) {
            return 'e-books';
        }
        return $record->material[0];
    }

    static protected function processHoldings(&$record)
    {
        $record->holdings = self::filterHoldings($record);

        // Enrich
        foreach ($record->holdings as $holding) {
            if (!isset(self::$libraries[$holding->library])) {
                continue;
            }
            $lib = self::$libraries[$holding->library];
            $cols = array_get($lib, 'collections', []);

            $holding->closed_stack = (
                count($cols) &&
                !in_array($holding->collection_code, $cols)
            );

            $holding->library_name = array_get($lib, 'name', $holding->library);
            $holding->library_zone = array_get($lib, 'zone');
        }
    }

    static protected function filterHoldings(&$record)
    {
        if (!isset($record->holdings)) {
            return [];
        }

        $selectedHoldings = [];

        $codes = ['local' => [], 'satellite' => [], 'other' => []];
        $holdings = [];
        $availableHoldings = [];

        foreach (self::$libraries as $k => $v) {
            $codes[$v['zone']][] = $k;
        }

        foreach (array_keys($codes) as $zone) {

            $holdings[$zone] = array_values(array_filter($record->holdings, function($holding) use ($codes, $zone) {
                return in_array($holding->library, $codes[$zone]);
            }));

            $availableHoldings[$zone] = array_values(array_filter($holdings[$zone], function($holding) {
                return $holding->status == 'available';
            }));
        }

        $collections = array_get(self::$libraries[$codes['local'][0]], 'collections', []);
        usort($availableHoldings['local'], function($a, $b) use ($collections) {
            $aKey = intval(array_search($a->collection_code, $collections));
            $bKey = intval(array_search($b->collection_code, $collections));
            return $aKey - $bKey;
        });

        if (count($availableHoldings['local'])) {
            // Available at local library!
            $selectedHoldings[] = $availableHoldings['local'][0];

            return $selectedHoldings;

        } else if (count($holdings['local'])) {
            // On loan at local library : Add, but do not return
            $selectedHoldings[] = $holdings['local'][0];
        }

        if (count($availableHoldings['satellite'])) {
            // Available at satellite library
            $selectedHoldings[] = $availableHoldings['satellite'][0];

            return $selectedHoldings;
        }

        if (count($availableHoldings['other'])) {
            // Available at other library
            $selectedHoldings[] = $availableHoldings['other'][0];

            return $selectedHoldings;

        }

        if (!count($selectedHoldings) && count($holdings['satellite'])) {
            // On loan at satellite library
            $selectedHoldings[] = $holdings['satellite'][0];

            return $selectedHoldings;
        }

        if (!count($selectedHoldings) && count($holdings['other'])) {
            // On loan at other library
            $selectedHoldings[] = $holdings['other'][0];

            return $selectedHoldings;
        }

        return $selectedHoldings;
    }
}
