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

namespace Markocupic\ContaoCsvTableMerger\DataContainer;

use Contao\Controller;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\ServiceAnnotation\Callback;
//use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

class CsvTableMerger
{
    private ContaoFramework $framework;
    private Connection $connection;

    public function __construct(ContaoFramework $framework, Connection $connection)
    {
        $this->framework = $framework;
        $this->connection = $connection;
    }

    /**
     * @Callback(table="tl_csv_table_merger", target="fields.importTable.options")
     *
     * @throws Exception
     */
    //#[AsCallback(table: 'tl_csv_table_merger', target: 'fields.importTable.options', priority: 100)]
    public function optionsCallbackImportTable(): array
    {
        $schemaManager = $this->connection->createSchemaManager();

        return $schemaManager->listTableNames();
    }

    /**
     * @Callback(table="tl_csv_table_merger", target="fields.allowedFields.options")
     * @Callback(table="tl_csv_table_merger", target="fields.identifier.options")
     * @Callback(table="tl_csv_table_merger", target="fields.skipValidationFields.options")
     *
     * @throws Exception
     */
    //#[AsCallback(table: 'tl_csv_table_merger', target: 'fields.allowedFields.options', priority: 100)]
    //#[AsCallback(table: 'tl_csv_table_merger', target: 'fields.identifier.options', priority: 100)]
    //#[AsCallback(table: 'tl_csv_table_merger', target: 'fields.skipValidationFields.options', priority: 100)]
    public function optionsCallbackGetTableColumns(DataContainer $dc): array
    {
        $controllerAdapter = $this->framework->getAdapter(Controller::class);

        $tableName = $dc->activeRecord->importTable;

        if (!$tableName) {
            return [];
        }

        $schemaManager = $this->connection->createSchemaManager();

        // Get a list of all lowercase field names
        $arrLCFields = $schemaManager->listTableColumns($tableName);

        if (!\is_array($arrLCFields)) {
            return [];
        }

        $controllerAdapter->loadDataContainer($tableName);
        $arrDcaFields = [];

        foreach (array_keys($GLOBALS['TL_DCA'][$tableName]['fields'] ?? []) as $k) {
            $arrDcaFields[strtolower($k)] = [
                'fieldName' => $k,
                'sql' => $GLOBALS['TL_DCA'][$tableName]['fields'][$k]['sql'] ?? null,
            ];
        }

        $arrOptions = [];

        foreach (array_keys($arrLCFields) as $k) {
            // If exists, take the field name from the DCA
            $fieldName = $arrDcaFields[$k]['fieldName'] ?? $k;
            $sql = $arrDcaFields[$k]['sql'] ?? '';
            $strSql = !empty($sql) ? sprintf(' <span class="ctm-sql-descr">[%s]</span>', $sql) : '';
            $arrOptions[$fieldName] = $fieldName.$strSql;
        }

        return $arrOptions;
    }
}
