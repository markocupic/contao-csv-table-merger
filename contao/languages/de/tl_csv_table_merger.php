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
$GLOBALS['TL_LANG']['tl_csv_table_merger']['title_legend'] = "Titel";
$GLOBALS['TL_LANG']['tl_csv_table_merger']['settings_legend'] = "Einstellungen";
$GLOBALS['TL_LANG']['tl_csv_table_merger']['text_file_legend'] = "CSV Textdatei Einstellungen";

/**
 * Operations
 */
$GLOBALS['TL_LANG']['tl_csv_table_merger']['edit'] = "Datensatz mit ID: %s bearbeiten";
$GLOBALS['TL_LANG']['tl_csv_table_merger']['copy'] = "Datensatz mit ID: %s kopieren";
$GLOBALS['TL_LANG']['tl_csv_table_merger']['delete'] = "Datensatz mit ID: %s löschen";
$GLOBALS['TL_LANG']['tl_csv_table_merger']['show'] = "Datensatz mit ID: %s ansehen";
$GLOBALS['TL_LANG']['tl_csv_table_merger']['merge'] = "Merging mit ID: %s starten";

/**
 * Fields
 */
$GLOBALS['TL_LANG']['tl_csv_table_merger']['title'] = ["Titel", "Bitte geben Sie den Titel ein."];
$GLOBALS['TL_LANG']['tl_csv_table_merger']['importTable'] = ["Zieltabelle", "Bitte wählen Sie die Zieltabelle aus."];
$GLOBALS['TL_LANG']['tl_csv_table_merger']['identifier'] = ["Datensatz-Erkennungsfeld", "Bitte wählen Sie das Feld für die Datensatzerkennung aus."];
$GLOBALS['TL_LANG']['tl_csv_table_merger']['allowedFields'] = ["Erlaubte Felder", "Bitte wählen Sie die Felder aus, die überschrieben werden dürfen."];
$GLOBALS['TL_LANG']['tl_csv_table_merger']['delimiter'] = ["Feldtrennzeichen", "Bitte geben Sie das Feld-Trennzeichen ein. Üblicherweise ein ;"];
$GLOBALS['TL_LANG']['tl_csv_table_merger']['enclosure'] = ["Feldbegrenzerzeichen", "Bitte geben Sie das Feldbegrenzerzeichen ein. Üblicherweise ein \""];
$GLOBALS['TL_LANG']['tl_csv_table_merger']['arrayDelimiter'] = ["Array-Trennzeichen", "Bitte geben Sie das Array-Trennzeichen ein. Üblicherweise ||"];
$GLOBALS['TL_LANG']['tl_csv_table_merger']['skipValidationFields'] = ["Eingabeprüfung überspringen", "Bitte wählen Sie die Felder aus, wo die Eingabeprüfung übersprungen werden soll."];
$GLOBALS['TL_LANG']['tl_csv_table_merger']['fileSRC'] = ["CSV Textdatei", "Bitte wählen Sie die Textdatei aus."];
$GLOBALS['TL_LANG']['tl_csv_table_merger']['deleteNonExistentRecords'] = ["Nicht mehr existierende Datensätze löschen", "Entscheiden Sie, ob in der Textdatei nicht mehr vorhandene Datensätze auch aus der Zieltabelle gelöscht werden sollen."];
