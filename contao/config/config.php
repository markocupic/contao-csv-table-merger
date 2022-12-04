<?php

/*
 * This file is part of Contao CSV Table Merger.
 *
 * (c) Marko Cupic 2022 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/contao-csv-table-merger
 */

use Markocupic\ContaoCsvTableMerger\Model\CsvTableMergerModel;
use Markocupic\ContaoCsvTableMerger\Controller\Backend\CsvTableMergeController;

/**
 * Backend modules
 */
$GLOBALS['BE_MOD']['system']['csv_table_merger'] = array(
    'tables' => array('tl_csv_table_merger'),
    'appAction' => [CsvTableMergeController::class, 'appAction'],
);

/**
 * Models
 */
$GLOBALS['TL_MODELS']['tl_csv_table_merger'] = CsvTableMergerModel::class;
