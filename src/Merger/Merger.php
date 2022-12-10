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

namespace Markocupic\ContaoCsvTableMerger\Merger;

use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\Database;
use Contao\FilesModel;
use Contao\StringUtil;
use Contao\System;
use Contao\Versions;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use League\Csv\InvalidArgument;
use League\Csv\Reader;
use League\Csv\Statement;
use Markocupic\ContaoCsvTableMerger\DataRecord\DataRecord;
use Markocupic\ContaoCsvTableMerger\Model\CsvTableMergerModel;
use Markocupic\ContaoCsvTableMerger\Validator\WidgetValidator;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LogLevel;

class Merger implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private ContaoFramework $framework;
    private Connection $connection;
    private WidgetValidator $widgetValidator;
    private array $appConfig;
    private string $projectDir;
    private Adapter $stringUtilAdapter;
    private Adapter $filesModelAdapter;
    private Adapter $systemAdapter;

    private bool $initialized = false;
    private ?array $records = null;
    private ?CsvTableMergerModel $model = null;
    private ?string $importTable = null;
    private array $allowedFields = [];
    private ?string $identifier = null;
    private string $delimiter = ';';
    private string $enclosure = '"';
    private ?string $source = null;

    public function __construct(ContaoFramework $framework, Connection $connection, WidgetValidator $widgetValidator, array $appConfig, string $projectDir)
    {
        $this->framework = $framework;
        $this->connection = $connection;
        $this->widgetValidator = $widgetValidator;
        $this->appConfig = $appConfig;
        $this->projectDir = $projectDir;

        // Adapters
        $this->stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);
        $this->filesModelAdapter = $this->framework->getAdapter(FilesModel::class);
        $this->systemAdapter = $this->framework->getAdapter(System::class);
    }

    public function validate(MergeMonitor $mergeMonitor): bool
    {
        $model = $mergeMonitor->getModel();

        if (!$this->initialized) {
            $this->initialize($model);
        }

        if (!$this->validateSettings($mergeMonitor)) {
            return false;
        }

        $mergeMonitor->addInfoMessage('Validate settings: ok!');

        // Validate data and abort process in case of an invalid spreadsheet.
        if (!$this->validateSpreadsheet($mergeMonitor)) {
            return false;
        }

        $mergeMonitor->addInfoMessage('Validate spreadsheet: ok!');

        return true;
    }

    public function getRecordsCount(MergeMonitor $mergeMonitor): int
    {
        $model = $mergeMonitor->getModel();

        if (!$this->initialized) {
            $this->initialize($model);
        }

        return \count($this->getRecordsFromCsv());
    }

    /**
     * @throws Exception
     */
    public function run(MergeMonitor $mergeMonitor, int $offset = 0, int $limit = 0): void
    {
        $model = $mergeMonitor->getModel();

        if (!$this->initialized) {
            $this->initialize($model);
        }

        $line = $offset + 1;

        /*
         * The database won't be touched if an error occurs!
         */
        $this->connection->beginTransaction();

        try {
            foreach ($this->getRecordsFromCsv($offset, $limit) as $arrRecord) {
                ++$line;

                $result = $this->connection->fetchAssociative(
                    sprintf('SELECT * FROM %s WHERE %s = ?', $this->importTable, $this->identifier),
                    [$arrRecord[$this->identifier]]
                );

                $objDataRecord = new DataRecord($arrRecord, $this->model, $line);

                if (!$result) {
                    $this->insertRecord($objDataRecord, $mergeMonitor);
                } else {
                    $objDataRecord->setTargetRecord($result);
                    $this->updateRecord($objDataRecord, $mergeMonitor);
                }
            }

            // Do not update nor insert records if there has been an error!
            if ($mergeMonitor->hasErrorMessage()) {
                if ($this->connection->isTransactionActive()) {
                    $this->connection->rollBack();

                    return;
                }
            }
            $this->connection->commit();
        } catch (\Exception $e) {
            // Do not update nor insert records if there has been an error!
            if ($mergeMonitor->hasErrorMessage()) {
                if ($this->connection->isTransactionActive()) {
                    $this->connection->rollBack();

                    return;
                }
            }

            throw $e;
        }
    }

    /**
     * @throws Exception
     */
    public function deleteNonExistentRecords(MergeMonitor $mergeMonitor): void
    {
        $model = $mergeMonitor->getModel();

        $this->initialize($model);

        $arrIdentifiers = array_column($this->getRecordsFromCsv(), $this->identifier);

        if (!empty($arrIdentifiers)) {
            $arrDelIdentifiers = $this->connection->fetchFirstColumn(
                sprintf(
                    "SELECT id FROM %s WHERE %s NOT IN('%s')",
                    $this->importTable,
                    $this->identifier,
                    implode("', '", $arrIdentifiers),
                )
            );

            foreach ($arrDelIdentifiers as $intId) {
                $valIdentifier = $this->connection->fetchOne(sprintf('SELECT %s FROM %s WHERE id = ?', $this->identifier, $this->importTable), [$intId]);
                $affected = (bool) $this->connection->delete($this->importTable, ['id' => $intId]);

                if ($affected) {
                    $mergeMonitor->addInfoMessage(
                        sprintf(
                            'Delete data record with "%s.id = %s" and identifier "%s.%s = %s"',
                            $this->importTable,
                            $intId,
                            $this->importTable,
                            $this->identifier,
                            $valIdentifier,
                        )
                    );

                    $countDeletions = $mergeMonitor->get(MergeMonitor::KEY_COUNT_DELETIONS);
                    ++$countDeletions;
                    $mergeMonitor->set(MergeMonitor::KEY_COUNT_DELETIONS, $countDeletions);

                    // System log
                    if (null !== $this->logger) {
                        $this->logger->log(
                            LogLevel::INFO,
                            sprintf(
                                'DELETE FROM %s WHERE id=%d',
                                $this->importTable,
                                $intId,
                            ),
                            ['contao' => new ContaoContext(__METHOD__, ContaoContext::GENERAL)],
                        );
                    }
                }
            }
        }
    }

    private function initialize(CsvTableMergerModel $model): void
    {
        $this->model = $model;
        $this->importTable = $model->importTable;
        $this->allowedFields = $this->stringUtilAdapter->deserialize($model->allowedFields, true);
        $this->identifier = $model->identifier;
        $this->delimiter = $model->delimiter ?: $this->delimiter;
        $this->enclosure = $model->enclosure ?: $this->enclosure;

        $filesModel = $this->filesModelAdapter->findByUuid($model->fileSRC);

        if (null !== $filesModel) {
            $this->source = $this->projectDir.'/'.$filesModel->path;
        }

        $this->initialized = true;
    }

    /**
     * @throws \League\Csv\Exception
     * @throws InvalidArgument
     */
    private function getRecordsFromCsv(int $offset = 0, int $limit = 0): array
    {
        // Get from cache
        if (isset($this->records[$offset.'_'.$limit]) && \is_array($this->records[$offset.'_'.$limit])) {
            return $this->records[$offset.'_'.$limit];
        }

        $csv = Reader::createFromPath($this->source, 'r');

        $csv->setHeaderOffset(0);
        $csv->skipInputBOM();
        $csv->setDelimiter($this->delimiter);
        $csv->setEnclosure($this->enclosure);

        $stmt = Statement::create();

        if ($offset) {
            $stmt = $stmt->offset($offset);
        }

        if ($limit) {
            $stmt = $stmt->limit($limit);
        }

        $arrData = $stmt->process($csv);

        $arrRecords = [];

        foreach ($arrData as $arrRecord) {
            $arrRecords[] = array_map('trim', $arrRecord);
        }

        // Cache results
        $this->records[$offset.'_'.$limit] = $arrRecords;

        return $arrRecords;
    }

    /**
     * @throws Exception
     */
    private function insertRecord(DataRecord $objDataRecord, MergeMonitor $mergeMonitor): void
    {
        $arrRecord = $objDataRecord->getData();
        // Do only allow inserting selected fields.
        foreach (array_keys($arrRecord) as $fieldName) {
            if (!\in_array($fieldName, $this->allowedFields, true)) {
                unset($arrRecord[$fieldName]);
            }
        }

        $objDataRecord->setData($arrRecord);

        /** @var DataRecord $objDataRecord */
        $objDataRecord = $this->triggerBeforeInsertHook($objDataRecord);

        $arrRecord = $objDataRecord->getData();

        foreach ($arrRecord as $fieldName => $varValue) {
            $varValue = $this->widgetValidator->validate($fieldName, $this->importTable, $varValue, $this->model, $mergeMonitor, $objDataRecord->getCurrentLine(), 'insert');
            $arrRecord[$fieldName] = \is_array($varValue) ? serialize($varValue) : $varValue;
        }

        if ($mergeMonitor->hasErrorMessage()) {
            return;
        }

        $objDataRecord->setData($arrRecord);

        if ($objDataRecord->getStoreData()) {
            $affected = (bool) $this->connection->insert($this->importTable, $arrRecord);

            if ($affected) {
                $insertId = (int) $this->connection->lastInsertId();

                $this->triggerPostInsertHook($this->importTable, $insertId);

                $mergeMonitor->addInfoMessage(
                    sprintf(
                        'Line #%d: Insert new data record with ID %d and identifier "%s.%s =  %s".',
                        $objDataRecord->getCurrentLine(),
                        $insertId,
                        $this->importTable,
                        $this->identifier,
                        $arrRecord[$this->identifier]
                    )
                );

                $countInserts = $mergeMonitor->get(MergeMonitor::KEY_COUNT_INSERTS);
                ++$countInserts;
                $mergeMonitor->set(MergeMonitor::KEY_COUNT_INSERTS, $countInserts);

                // System log
                if (null !== $this->logger) {
                    $this->logger->log(
                        LogLevel::INFO,
                        sprintf(
                            'A new entry "%s.id=%d" has been created',
                            $this->importTable,
                            $insertId
                        ),
                        ['contao' => new ContaoContext(__METHOD__, ContaoContext::GENERAL)],
                    );
                }
            }
        }
    }

    /**
     * @throws Exception
     */
    private function updateRecord(DataRecord $objDataRecord, MergeMonitor $mergeMonitor): void
    {
        $arrRecord = $objDataRecord->getData();

        // Do not update id, passwords, etc.
        $arrFieldsNewerUpdate = $this->appConfig['fields_newer_update'];

        // Do not allow updating/inserting not allowed fields.
        // E.g. 'id', 'password', 'dateAdded', 'addedOn'
        // See Configuration.php
        foreach ($arrFieldsNewerUpdate as $fieldNewerUpdate) {
            if (isset($arrRecord[$fieldNewerUpdate])) {
                unset($arrRecord[$fieldNewerUpdate]);
            }
        }

        // Do only allow updating selected fields.
        foreach (array_keys($arrRecord) as $fieldName) {
            if (!\in_array($fieldName, $this->allowedFields, true)) {
                unset($arrRecord[$fieldName]);
            }
        }

        $objDataRecord->setData($arrRecord);

        $objDataRecord = $this->triggerBeforeUpdateHook($objDataRecord);

        if (!$mergeMonitor->hasErrorMessage() && $objDataRecord->getStoreData()) {
            // New state
            $arrRecord = $objDataRecord->getData();

            // Current state
            $arrCurrent = $objDataRecord->getTargetRecord();

            foreach ($arrRecord as $fieldName => $varValue) {
                $varValue = $this->widgetValidator->validate($fieldName, $this->importTable, $varValue, $this->model, $mergeMonitor, $objDataRecord->getCurrentLine(), 'update');
                $arrRecord[$fieldName] = \is_array($varValue) ? serialize($varValue) : $varValue;
            }

            if ($mergeMonitor->hasErrorMessage()) {
                return;
            }

            $objDataRecord->setData($arrRecord);

            $affected = (bool) $this->connection->update($this->importTable, $arrRecord, ['id' => $arrCurrent['id']]);

            if ($affected) {
                // Auto-update tstamp, if it wasn't explicitly declared.
                if (empty($arrRecord['tstamp'])) {
                    if (Database::getInstance()->fieldExists('tstamp', $this->importTable)) {
                        $this->connection->update($this->importTable, ['tstamp' => time()], ['id' => $arrCurrent['id']]);
                    }
                }

                $this->triggerPostUpdateHook($this->importTable, (int) $arrCurrent['id']);

                // Create new version
                $objVersions = new Versions($objDataRecord->getImportTable(), $arrCurrent['id']);
                $objVersions->initialize();
                $objVersions->create();

                $countUpdates = $mergeMonitor->get(MergeMonitor::KEY_COUNT_UPDATES);
                ++$countUpdates;
                $mergeMonitor->set(MergeMonitor::KEY_COUNT_UPDATES, $countUpdates);

                $mergeMonitor->addInfoMessage(
                    sprintf(
                        'Line #%d: Update data record with identifier "%s.%s = %s".',
                        $objDataRecord->getCurrentLine(),
                        $this->importTable,
                        $this->identifier,
                        $arrCurrent[$this->identifier],
                    )
                );
            } else {
                $mergeMonitor->addInfoMessage(
                    sprintf(
                        'Line #%d: Data record with identifier "%s.%s = %s" is already up to date.',
                        $objDataRecord->getCurrentLine(),
                        $this->importTable,
                        $this->identifier,
                        $arrCurrent[$this->identifier],
                    )
                );
            }
        } else {
            $mergeMonitor->addInfoMessage(
                sprintf(
                    'Line #%d: Skipped data record update for data record with identifier "%s.%s = %s".',
                    $objDataRecord->getCurrentLine(),
                    $this->importTable,
                    $this->identifier,
                    $arrRecord[$this->identifier]
                )
            );
        }
    }

    private function triggerBeforeInsertHook(DataRecord $objDataRecord): DataRecord
    {
        // HOOK: add custom logic
        if (isset($GLOBALS['TL_HOOKS']['csvTableMergerBeforeInsert']) && \is_array($GLOBALS['TL_HOOKS']['csvTableMergerBeforeInsert'])) {
            foreach ($GLOBALS['TL_HOOKS']['csvTableMergerBeforeInsert'] as $callback) {
                $objHook = $this->systemAdapter->importStatic($callback[0]);
                $objDataRecord = $objHook->{$callback[1]}($objDataRecord);
            }
        }

        return $objDataRecord;
    }

    private function triggerBeforeUpdateHook(DataRecord $objDataRecord): DataRecord
    {
        // HOOK: add custom logic
        if (isset($GLOBALS['TL_HOOKS']['csvTableMergerBeforeUpdate']) && \is_array($GLOBALS['TL_HOOKS']['csvTableMergerBeforeUpdate'])) {
            foreach ($GLOBALS['TL_HOOKS']['csvTableMergerBeforeUpdate'] as $callback) {
                $objHook = $this->systemAdapter->importStatic($callback[0]);
                $objDataRecord = $objHook->{$callback[1]}($objDataRecord);
            }
        }

        return $objDataRecord;
    }

    private function triggerPostInsertHook(string $importTable, int $id): void
    {
        // HOOK: add custom logic
        if (isset($GLOBALS['TL_HOOKS']['csvTableMergerPostInsert']) && \is_array($GLOBALS['TL_HOOKS']['csvTableMergerPostInsert'])) {
            foreach ($GLOBALS['TL_HOOKS']['csvTableMergerPostInsert'] as $callback) {
                $objHook = $this->systemAdapter->importStatic($callback[0]);
                $objHook->{$callback[1]}($importTable, $id);
            }
        }
    }

    private function triggerPostUpdateHook(string $importTable, int $id): void
    {
        // HOOK: add custom logic
        if (isset($GLOBALS['TL_HOOKS']['csvTableMergerPostUpdate']) && \is_array($GLOBALS['TL_HOOKS']['csvTableMergerPostUpdate'])) {
            foreach ($GLOBALS['TL_HOOKS']['csvTableMergerPostUpdate'] as $callback) {
                $objHook = $this->systemAdapter->importStatic($callback[0]);
                $objHook->{$callback[1]}($importTable, $id);
            }
        }
    }

    private function validateSettings(MergeMonitor $mergeMonitor): bool
    {
        // Check #1: Check if table exists.
        if (!Database::getInstance()->tableExists($this->importTable)) {
            $mergeMonitor->addErrorMessage(
                sprintf(
                    'Table merge process aborted! Table "%s" does not exist. Please check the settings.',
                    $this->importTable,
                )
            );

            return false;
        }

        // Check #2: Check if each of the selected fields exists in the table
        foreach ($this->allowedFields as $fieldName) {
            if (!Database::getInstance()->fieldExists($fieldName, $this->importTable)) {
                $mergeMonitor->addErrorMessage(
                    sprintf(
                        'Table merge process aborted! Field name "%s" does not exist in table "%s".  Please check "allowed fields" in the settings.',
                        $fieldName,
                        $this->importTable,
                    )
                );

                return false;
            }
        }

        return true;
    }

    /**
     * @throws Exception
     * @throws InvalidArgument
     * @throws \League\Csv\Exception
     */
    private function validateSpreadsheet(MergeMonitor $mergeMonitor): bool
    {
        $arrRecords = $this->getRecordsFromCsv();

        // Check #0: Check, if has been defined.
        if (!\strlen($this->identifier)) {
            $mergeMonitor->addErrorMessage(
                'Table merge process aborted! You have to define an identifier.'
            );

            return false;
        }

        // Check #1: Check, if each record contains an identifier.
        $line = 1; // headline: line #1

        foreach ($arrRecords as $arrRecord) {
            ++$line;

            if (!isset($arrRecord[$this->identifier])) {
                $mergeMonitor->addErrorMessage(
                    sprintf(
                        'Line #%d: Table merge process aborted! You selected column "%s" as identifier. But we could not detect a column with the name "%s"! Please check the spreadsheet on line #%d.',
                        $line,
                        $this->identifier,
                        $this->identifier,
                        $line,
                    )
                );

                return false;
            }

            if (!\strlen($arrRecord[$this->identifier])) {
                $mergeMonitor->addErrorMessage(
                    sprintf(
                        'Line #%d: Table merge process aborted! No identifier found! Please check the spreadsheet on line #%d.',
                        $line,
                        $line,
                    )
                );

                return false;
            }
        }

        // Check #2: Check, if identifier is unique.
        $arrIdentifier = array_column($arrRecords, $this->identifier);
        $arrIdentifierFiltered = array_filter(array_unique(array_column($arrRecords, $this->identifier)));

        if (\count($arrIdentifier) !== \count($arrIdentifierFiltered)) {
            $mergeMonitor->addErrorMessage(
                sprintf(
                    'Table merge process aborted! Identifier "%s.%s" has to be unique, but "%s" found two or more times in the spreadsheet. Please check the spreadsheet or select another field for the identifier.',
                    $this->importTable,
                    $this->identifier,
                    implode('", "', array_unique(array_diff_key($arrIdentifier, $arrIdentifierFiltered))),
                )
            );

            return false;
        }

        $hasError = false;

        // Check #3: Check if identifier occurs more than 1x in the database.
        $arrIdentifier = array_column($arrRecords, $this->identifier);

        foreach ($arrIdentifier as $valIdentifier) {
            $counter = $this->connection->fetchOne(
                sprintf(
                    'SELECT COUNT(%s) AS counter FROM %s WHERE %s = ?',
                    $this->identifier,
                    $this->importTable,
                    $this->identifier,
                ),
                [$valIdentifier]
            );

            if ($counter > 1) {
                $hasError = true;
                $mergeMonitor->addErrorMessage(
                    sprintf(
                        'Table merge process aborted! Identifier "%s.%s" with value "%s" has to be unique, but found multiple times in "%s". Please fix the database or select another field for the identifier.',
                        $this->importTable,
                        $this->identifier,
                        $valIdentifier,
                        $this->importTable,
                    )
                );
            }
        }

        return !$hasError;
    }
}
