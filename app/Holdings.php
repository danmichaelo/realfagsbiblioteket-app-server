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
                'k60011',  // UREAL Pensum (Dagslån)        [least preferred]
                'k00471',  // UREAL Pensum
                'k00460',  // UREAL Laveregrad
                'k00469',  // UREAL Oppsl.
                'k00418',  // UREAL Avis
                'k00480',  // UREAL Skranken
                'k00593',  // UREAL Avsamling
                'k00481',  // UREAL Spill Bjørnehjørnet
                'k00413',  // UREAL Astr.
                'k00421',  // UREAL Biol.
                'k00447',  // UREAL Geo.
                'k00449',  // UREAL Geol.
                'k00457',  // UREAL Kjem.
                'k00440',  // UREAL Fys.
                'k00465',  // UREAL Mat.
                'k00475',  // UREAL Samling 42
                'k00477',  // UREAL SciFi
                'k00416',  // UREAL Atlas
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
        'UBO1030300' => ['zone' => 'ubo', 'name' => 'HumSam-biblioteket'],
        'UBO1030303' => ['zone' => 'ubo', 'name' => 'Studentbiblioteket Sophus Bugge'],
        'UBO1030301' => ['zone' => 'ubo', 'name' => 'Teologisk bibliotek'],

        // Kulturhistorisk museum
        'UBO1030011' => ['zone' => 'ubo', 'name' => 'Arkeologisk bibliotek'],
        'UBO1030010' => ['zone' => 'ubo', 'name' => 'Etnografisk bibliotek'],
        'UBO1030012' => ['zone' => 'ubo', 'name' => 'Numismatisk bibliotek'],

        // Medisin og ontologi
        'UBO1032300' => ['zone' => 'ubo', 'name' => 'Medisinsk bibliotek (Rikshospitalet)'],
        'UBO1032500' => ['zone' => 'ubo', 'name' => 'Medisinsk bibliotek (Radiumhospitalet)'],
        'UBO1030338' => ['zone' => 'ubo', 'name' => 'Medisinsk bibliotek (Ullevål)'],
        'UBO1030307' => ['zone' => 'ubo', 'name' => 'Odontologisk bibliotek'],

        // Juridisk
        'UBO1030000' => ['zone' => 'ubo', 'name' => 'Juridisk bibliotek'],
        'UBO1030001' => ['zone' => 'ubo', 'name' => 'Juridisk bibliotek : Privatrett'],
        'UBO1030002' => ['zone' => 'ubo', 'name' => 'Juridisk bibliotek : Kriminologi og rettssosiologi'],
        'UBO1030003' => ['zone' => 'ubo', 'name' => 'Juridisk bibliotek : Offentlig rett'],
        'UBO1030004' => ['zone' => 'ubo', 'name' => 'Juridisk bibliotek : Rettsinformatikk'],
        'UBO1030005' => ['zone' => 'ubo', 'name' => 'Juridisk bibliotek : Petroleumsrett og europarett'],
        'UBO1030006' => ['zone' => 'ubo', 'name' => 'Juridisk bibliotek : Sjørett'],
        'UBO1030009' => ['zone' => 'ubo', 'name' => 'Juridisk bibliotek : Læringssenteret'],
        'UBO1030015' => ['zone' => 'ubo', 'name' => 'Juridisk bibliotek : Rettshistorisk samling'],
    ];

    static public function process($holdings)
    {
        $holdings = json_decode(json_encode(self::filter($holdings)));

        // Enrich
        foreach ($holdings as $holding) {

            $lib = isset(self::$libraries[$holding->library]) ? self::$libraries[$holding->library] : [];
            $cols = array_get($lib, 'collections', []);

            $holding->closed_stack = (
                count($cols) &&
                !in_array($holding->collection_code, $cols)
            );

            $holding->library_name = array_get($lib, 'name', $holding->library);
            $holding->library_zone = array_get($lib, 'zone', 'other');
        }

        return $holdings;
    }

    static public function getBestHolding($holdings)
    {
        usort($holdings, function($a, $b) {

            // Men merk at NB ikke har callcode på sine fjernlånseks.
            // Så litt usikker på  denne
            // Eks.: https://ub-lsm.uio.no/primo/records/BIBSYS_ILS71478038780002201?raw=true
            $points = (intval(isset($b->callcode))) - (intval(isset($a->callcode)));

            return $points;
        });

        return $holdings[0];
    }

    static public function filter($record_holdings)
    {
        $selectedHoldings = [];

        $codes = ['local' => [], 'satellite' => [], 'ubo' => [], 'other' => ['*']];
        $holdings = [];
        $availableHoldings = [];

        foreach (self::$libraries as $k => $v) {
            $codes[$v['zone']][] = $k;
        }

        foreach ($codes as $zone => $zv) {
            $holdings[$zone] = [];
        }
        foreach ($record_holdings as $holding) {
           foreach ($codes as $zone => $zv) {
                if (in_array($holding->library, $zv) || in_array('*', $zv)) {
                    $holdings[$zone][] = $holding;
                    break; // break out of the zone loop to avoid the record going into the '*' zone
                }
            }
        }
        foreach ($codes as $zone => $zv) {
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
            $selectedHoldings[] = self::getBestHolding($availableHoldings['local']);

            return $selectedHoldings;

        } else if (count($holdings['local'])) {
            // On loan at local library : Add, but do not return
            $selectedHoldings[] = self::getBestHolding($holdings['local']);
        }

        if (count($availableHoldings['satellite'])) {
            // Available at satellite library
            $selectedHoldings[] = self::getBestHolding($availableHoldings['satellite']);

            return $selectedHoldings;
        }

        if (count($availableHoldings['ubo'])) {
            // Available at other ubo library
            $selectedHoldings[] = self::getBestHolding($availableHoldings['ubo']);

            return $selectedHoldings;
        }

        if (!count($selectedHoldings) && count($holdings['satellite'])) {
            // On loan at satellite library
            $selectedHoldings[] = self::getBestHolding($holdings['satellite']);

            return $selectedHoldings;
        }

        if (!count($selectedHoldings) && count($holdings['ubo'])) {
            // On loan at other ubo library
            $selectedHoldings[] = self::getBestHolding($holdings['ubo']);

            return $selectedHoldings;
        }

        if (!count($selectedHoldings) && count($availableHoldings['other'])) {
            $selectedHoldings[] = self::getBestHolding($availableHoldings['other']);

            return $selectedHoldings;
        }

        if (!count($selectedHoldings) && count($holdings['other'])) {
            $selectedHoldings[] = self::getBestHolding($holdings['other']);

            return $selectedHoldings;
        }

        return $selectedHoldings;
    }
}
