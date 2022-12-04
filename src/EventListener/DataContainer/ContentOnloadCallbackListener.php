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

namespace Markocupic\ContaoCsvTableMerger\EventListener\DataContainer;

use Contao\CoreBundle\ServiceAnnotation\Callback;

/**
 * @Callback(target="config.onload", table="tl_csv_table_merger")
 */
class ContentOnloadCallbackListener
{
    public function __invoke(): void
    {
        $GLOBALS['TL_CSS'][] = 'bundles/markocupiccontaocsvtablemerger/css/csv_table_merger.css';
        $GLOBALS['TL_JAVASCRIPT'][] = 'bundles/markocupiccontaocsvtablemerger/js/vuejs/vue.global@3.2.45.prod.js';
        $GLOBALS['TL_JAVASCRIPT'][] = 'bundles/markocupiccontaocsvtablemerger/js/TableMergeApp.js';
    }
}
