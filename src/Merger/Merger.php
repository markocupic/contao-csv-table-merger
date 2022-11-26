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
use Contao\Database;
use Contao\FilesModel;
use Contao\StringUtil;
use Contao\System;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use League\Csv\InvalidArgument;
use League\Csv\Reader;
use League\Csv\Statement;
use Markocupic\ContaoCsvTableMerger\DataRecord\DataRecord;
use Markocupic\ContaoCsvTableMerger\Message\Message;
use Markocupic\ContaoCsvTableMerger\Model\CsvTableMergerModel;

class Merger
{
    private ContaoFramework $framework;
    private Connection $connection;
    private Message $message;
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

    public function __construct(ContaoFramework $framework, Connection $connection, Message $message, array $appConfig, string $projectDir)
    {
        $this->framework = $framework;
        $this->connection = $connection;
        $this->message = $message;
        $this->appConfig = $appConfig;
        $this->projectDir = $projectDir;

        // Adapters
        $this->stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);
        $this->filesModelAdapter = $this->framework->getAdapter(FilesModel::class);
        $this->systemAdapter = $this->framework->getAdapter(System::class);
    }

    /**
     * @throws Exception
     */
    public function run(CsvTableMergerModel $model): void
    {
        if (!$this->initialized) {
            $this->initialize($model);
        }

        if (!$this->validateSettings()) {
            return;
        }

        // Validate data and abort process in case of an invalid spreadsheet.
        if (!$this->validateSpreadsheet()) {
            return;
        }

        $line = 1;

        /*
         * The database won't be touched if an error occurs!
         */
        $this->connection->beginTransaction();

        try {
            foreach ($this->getRecordsFromCsv() as $arrRecord) {
                ++$line;

                $result = $this->connection->fetchAssociative(
                    sprintf('SELECT * FROM %s WHERE %s = ?', $this->importTable, $this->identifier),
                    [$arrRecord[$this->identifier]]
                );

                $objDataRecord = new DataRecord($arrRecord, $this->model, $line);

                if (!$result) {
                    $this->insertRecord($objDataRecord);
                } else {
                    $objDataRecord->setTargetRecord($result);
                    $this->updateRecord($objDataRecord);
                }
            }

            // Delete all db records that are not in the list.
            if ($this->model->deleteNonExistentRecords) {
                $this->deleteNonExistentRecords();
            }

            // Do not update/insert if there was an error!
            if ($this->message->hasError()) {
                $this->connection->rollBack();
            } else {
                $this->connection->commit();
            }
        } catch (\Exception $e) {
            $this->connection->rollBack();

            throw $e;
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
    private function getRecordsFromCsv(): array
    {
        if (\is_array($this->records)) {
            return $this->records;
        }

        $csv = Reader::createFromPath($this->source, 'r');

        $csv->setHeaderOffset(0);
        $csv->setDelimiter($this->delimiter);
        $csv->setEnclosure($this->enclosure);

        $stmt = Statement::create();

        $arrData = $stmt->process($csv);

        $arrRecords = [];

        foreach ($arrData as $arrRecord) {
            $arrRecords[] = array_map('trim', $arrRecord);
        }

        $this->records = $arrRecords;

        return $arrRecords;
    }

    /**
     * @throws Exception
     */
    private function insertRecord(DataRecord $objDataRecord): void
    {
        /** @var DataRecord $objDataRecord */
        $objDataRecord = $this->triggerBeforeInsertHook($objDataRecord);
        $arrRecord = $objDataRecord->getData();

        if ($objDataRecord->getStoreData()) {
            $affected = (bool) $this->connection->insert($this->importTable, $arrRecord);

            if ($affected) {
                $insertId = (int) $this->connection->lastInsertId();
                $this->triggerPostInsertHook($this->importTable, $insertId);

                $this->message->addInfo(
                    sprintf(
                        'Line #%d: Inserted new data record with ID %d and identifier "%s.%s =  %s".',
                        $objDataRecord->getCurrentLine(),
                        $insertId,
                        $this->importTable,
                        $this->identifier,
                        $arrRecord[$this->identifier]
                    )
                );
            }
        } else {
            $this->message->addError(
                sprintf(
                    'Line #%d: Skipped database insert for data record with identifier "%s.%s = %s".',
                    $objDataRecord->getCurrentLine(),
                    $this->importTable,
                    $this->identifier,
                    $arrRecord[$this->identifier]
                )
            );
        }
    }

    /**
     * @throws Exception
     */
    private function updateRecord(DataRecord $objDataRecord): void
    {
        $objDataRecord = $this->triggerBeforeUpdateHook($objDataRecord);

        // Do not update id, passwords, etc.
        $arrFieldsNewerUpdate = $this->appConfig['fields_newer_update'];

        if ($objDataRecord->getStoreData()) {
            // New state
            $arrNew = $objDataRecord->getData();

            // Current state
            $arrCurrent = $objDataRecord->getTargetRecord();

            // Do not allow updating/inserting not allowed fields.
            // E.g. 'id', 'password', 'dateAdded', 'addedOn'
            // See Configuration.php
            foreach ($arrFieldsNewerUpdate as $fieldNewerUpdate) {
                if (isset($arrNew[$fieldNewerUpdate])) {
                    unset($arrNew[$fieldNewerUpdate]);
                }
            }

            // Do only allow updating selected fields.
            foreach (array_keys($arrNew) as $fieldName) {
                if (!\in_array($fieldName, $this->allowedFields, true)) {
                    unset($arrNew[$fieldName]);
                }
            }

            $affected = (bool) $this->connection->update($this->importTable, $arrNew, ['id' => $arrCurrent['id']]);

            if ($affected) {
                // Auto-update tstamp, if it wasn't explicitly declared.
                if (!isset($arrNew['tstamp']) || empty($arrNew['tstamp'])) {
                    if (Database::getInstance()->fieldExists('tstamp', $this->importTable)) {
                        $this->connection->update($this->importTable, ['tstamp' => time()], ['id' => $arrCurrent['id']]);
                    }
                }

                $this->message->addInfo(
                    sprintf(
                        'Line #%d: Updated data record with identifier "%s.%s = %s".',
                        $objDataRecord->getCurrentLine(),
                        $this->importTable,
                        $this->identifier,
                        $arrCurrent[$this->identifier],
                    )
                );
            }

            $this->triggerPostUpdateHook($this->importTable, (int) $arrCurrent['id'], $affected);
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

    private function triggerPostUpdateHook(string $importTable, int $id, bool $affected): void
    {
        // HOOK: add custom logic
        if (isset($GLOBALS['TL_HOOKS']['csvTableMergerPostUpdate']) && \is_array($GLOBALS['TL_HOOKS']['csvTableMergerPostUpdate'])) {
            foreach ($GLOBALS['TL_HOOKS']['csvTableMergerPostUpdate'] as $callback) {
                $objHook = $this->systemAdapter->importStatic($callback[0]);
                $objHook->{$callback[1]}($importTable, $id, $affected);
            }
        }
    }

    /**
     * @throws Exception
     */
    private function deleteNonExistentRecords(): void
    {
        $arrIdentifiers = array_column($this->getRecordsFromCsv(), $this->identifier);

        if (!empty($arrIdentifiers)) {
            $arrDelIdentifiers = $this->connection->fetchFirstColumn(
                sprintf(
                    "SELECT %s FROM %s WHERE %s NOT IN('%s')",
                    $this->identifier,
                    $this->importTable,
                    $this->identifier,
                    implode("', '", $arrIdentifiers),
                )
            );

            foreach ($arrDelIdentifiers as $identifier) {
                $affected = (bool) $this->connection->delete($this->importTable, [$this->identifier => $identifier]);

                if ($affected) {
                    $this->message->addInfo(
                        sprintf(
                            'Deleted data record with "%s.%s = %s"',
                            $this->importTable,
                            $this->identifier,
                            $identifier,
                        )
                    );
                }
            }
        }
    }

    private function validateSettings(): bool
    {
        // Check #1: Check if table exists.
        if (!Database::getInstance()->tableExists($this->importTable)) {
            $this->message->addError(
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
                $this->message->addError(
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
    private function validateSpreadsheet(): bool
    {
        $arrRecords = $this->getRecordsFromCsv();

        // Check #1: Check, if identifier exists.
        $hasError = false;
        $line = 1; // headline: line #1

        foreach ($arrRecords as $arrRecord) {
            ++$line;

            if (!\strlen($arrRecord[$this->identifier])) {
                $hasError = true;
                $this->message->addError(
                    sprintf(
                        'Line #%d: Table merge process aborted! No identifier found! Please check the spreadsheet on line #%d.',
                        $line,
                        $line,
                    )
                );
            }
        }

        if ($hasError) {
            return false;
        }

        // Check #2: Check, if identifier is unique.
        $arrIdentifier = array_column($arrRecords, $this->identifier);
        $arrIdentifierFiltered = array_filter(array_unique(array_column($arrRecords, $this->identifier)));

        if (\count($arrIdentifier) !== \count($arrIdentifierFiltered)) {
            $this->message->addError(
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
                $this->message->addError(
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
