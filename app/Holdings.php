<?php

namespace App;

class Holdings
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

        // HumSam
        'UBO1030300' => ['zone' => 'other', 'name' => 'HumSam-biblioteket'],
        'UBO1030303' => ['zone' => 'other', 'name' => 'Studentbiblioteket Sophus Bugge'],
        'UBO1030301' => ['zone' => 'other', 'name' => 'Teologisk bibliotek'],

        // Kulturhistorisk museum
        'UBO1030011' => ['zone' => 'other', 'name' => 'Arkeologisk bibliotek'],
        'UBO1030010' => ['zone' => 'other', 'name' => 'Etnografisk bibliotek'],
        'UBO1030012' => ['zone' => 'other', 'name' => 'Numismatisk bibliotek'],

        // Medisin og ontologi
        'UBO1032300' => ['zone' => 'other', 'name' => 'Medisinsk bibliotek (Rikshospitalet)'],
        'UBO1032500' => ['zone' => 'other', 'name' => 'Medisinsk bibliotek (Radiumhospitalet)'],
        'UBO1030338' => ['zone' => 'other', 'name' => 'Medisinsk bibliotek (Ullevål)'],
        'UBO1030307' => ['zone' => 'other', 'name' => 'Odontologisk bibliotek'],

        // Juridisk
        'UBO1030000' => ['zone' => 'other', 'name' => 'Juridisk bibliotek'],
        'UBO1030001' => ['zone' => 'other', 'name' => 'Juridisk bibliotek : Privatrett'],
        'UBO1030002' => ['zone' => 'other', 'name' => 'Juridisk bibliotek : Kriminologi og rettssosiologi'],
        'UBO1030003' => ['zone' => 'other', 'name' => 'Juridisk bibliotek : Offentlig rett'],
        'UBO1030004' => ['zone' => 'other', 'name' => 'Juridisk bibliotek : Rettsinformatikk'],
        'UBO1030005' => ['zone' => 'other', 'name' => 'Juridisk bibliotek : Petroleumsrett og europarett'],
        'UBO1030006' => ['zone' => 'other', 'name' => 'Juridisk bibliotek : Sjørett'],
        'UBO1030009' => ['zone' => 'other', 'name' => 'Juridisk bibliotek : Læringssenteret'],
        'UBO1030015' => ['zone' => 'other', 'name' => 'Juridisk bibliotek : Rettshistorisk samling'],
    ];

    static public function process($holdings)
    {
        $holdings = json_decode(json_encode(self::filter($holdings)));

        // Enrich
        foreach ($holdings as $holding) {
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

        return $holdings;
    }

    static public function filter($record_holdings)
    {
        $selectedHoldings = [];

        $codes = ['local' => [], 'satellite' => [], 'other' => []];
        $holdings = [];
        $availableHoldings = [];

        foreach (self::$libraries as $k => $v) {
            $codes[$v['zone']][] = $k;
        }

        foreach (array_keys($codes) as $zone) {

            $holdings[$zone] = array_values(array_filter($record_holdings, function($holding) use ($codes, $zone) {
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
            return $bKey - $aKey;
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
