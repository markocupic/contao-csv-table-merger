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

/**
 * Legends
 */
$GLOBALS['TL_LANG']['tl_csv_table_merger']['title_legend'] = "Title";
$GLOBALS['TL_LANG']['tl_csv_table_merger']['settings_legend'] = "Settings";
$GLOBALS['TL_LANG']['tl_csv_table_merger']['text_file_legend'] = "CSV text file settings";

/**
 * Operations
 */
$GLOBALS['TL_LANG']['tl_csv_table_merger']['edit'] = "Edit record with ID: %s";
$GLOBALS['TL_LANG']['tl_csv_table_merger']['copy'] = "Copy record with ID: %s";
$GLOBALS['TL_LANG']['tl_csv_table_merger']['delete'] = "Delete record with ID: %s";
$GLOBALS['TL_LANG']['tl_csv_table_merger']['show'] = "Show record with ID: %s";
$GLOBALS['TL_LANG']['tl_csv_table_merger']['appAction'] = ["Run merging process", "Run merging process"];

/**
 * Fields
 */
$GLOBALS['TL_LANG']['tl_csv_table_merger']['title'] = ["Title", "Please enter the title."];
$GLOBALS['TL_LANG']['tl_csv_table_merger']['importTable'] = ["Target table", "Please select the target table."];
$GLOBALS['TL_LANG']['tl_csv_table_merger']['identifier'] = ["Identifier field", "Please select the identifier field."];
$GLOBALS['TL_LANG']['tl_csv_table_merger']['allowedFields'] = ["Allowed fields", "Please select the fields that may be overwritten."];
$GLOBALS['TL_LANG']['tl_csv_table_merger']['delimiter'] = ["Delimiter", "Please enter the field delimiter. Normally ;"];
$GLOBALS['TL_LANG']['tl_csv_table_merger']['enclosure'] = ["Enclosure", "Please enter the field enclosure. Normally \""];
$GLOBALS['TL_LANG']['tl_csv_table_merger']['arrayDelimiter'] = ["Array delimiter", "Please enter the array delimiter. Normally ||"];
$GLOBALS['TL_LANG']['tl_csv_table_merger']['skipValidationFields'] = ["Skip input validation for these fields", "Please select the fields whose value should not be validated."];
$GLOBALS['TL_LANG']['tl_csv_table_merger']['fileSRC'] = ["CSV text file", "Please select the CSV text file."];
$GLOBALS['TL_LANG']['tl_csv_table_merger']['deleteNonExistentRecords'] = ["Delete non existent records", "Delete records in the target table that no longer exist in the text file."];
