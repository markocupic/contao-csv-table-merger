<?php

declare(strict_types=1);

/*
 * This file is part of Contao CSV Table Merger.
 *
 * (c) Marko Cupic 2022 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/contao-csv-table-merger
 */

namespace Markocupic\ContaoCsvTableMerger\EventListener\ContaoHook\BeforeInsert;

use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Markocupic\ContaoCsvTableMerger\DataRecord\DataRecord;
use Markocupic\ContaoCsvTableMerger\EventListener\ContaoHook\AbstractContaoHook;
use yidas\googleMaps\Client;

#[AsHook(GeocodingListener::HOOK, priority: GeocodingListener::PRIORITY)]
class GeocodingListener extends AbstractContaoHook
{
    public const HOOK = 'csvTableMergerBeforeInsert';
    public const PRIORITY = 500;
    private array $appConfig;

    public function __construct(array $appConfig)
    {
        $this->appConfig = $appConfig;
    }

    /**
     * @throws \Exception
     */
    public function __invoke(DataRecord $dataRecord): DataRecord
    {
        if (static::$disableHook) {
            return $dataRecord;
        }

        if (empty($this->appConfig['google_api_key'])) {
            return $dataRecord;
        }

        $arrRecord = $dataRecord->getData();

        if (!empty($arrRecord['street']) && !empty($arrRecord['postal']) && !empty($arrRecord['city'])) {
            $arrAddress = [];
            $arrAddress[] = $arrRecord['street'];
            $arrAddress[] = trim($arrRecord['postal'].' '.$arrRecord['city']);
            $arrAddress[] = $arrRecord['country'] ?? '';
            $arrAddress = array_filter(array_values($arrAddress));

            $gmaps = new Client(['key' => $this->appConfig['google_api_key']]);

            // Geocoding an address
            $geocodeResult = $gmaps->geocode(implode(', ', $arrAddress));

            if (!empty($geocodeResult[0]['geometry']['location']['lat']) && !empty($geocodeResult[0]['geometry']['location']['lng'])) {
                $arrRecord['longitude'] = $geocodeResult[0]['geometry']['location']['lng'];
                $arrRecord['latitude'] = $geocodeResult[0]['geometry']['location']['lat'];
                $dataRecord->setData($arrRecord);
            }
        }

        return $dataRecord;
    }
}
