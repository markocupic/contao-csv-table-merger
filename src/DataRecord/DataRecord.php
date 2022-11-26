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

namespace Markocupic\ContaoCsvTableMerger\DataRecord;

use Markocupic\ContaoCsvTableMerger\Model\CsvTableMergerModel;

class DataRecord
{
    private array $arrData;
    private ?array $arrTargetDataRecord;
    private CsvTableMergerModel $model;
    private string $importTable;
    private string $identifier;
    private int $currentLine;
    private bool $blnStore = true;

    public function __construct(array $arrData, CsvTableMergerModel $model, int $currentLine)
    {
        $this->arrData = $arrData;

        $this->model = $model;
        $this->importTable = $model->importTable;
        $this->identifier = $model->identifier;
        $this->currentLine = $currentLine;
    }

    public function getData(): array
    {
        return $this->arrData;
    }

    public function setData(array $arrData): void
    {
        $this->arrData = $arrData;
    }

    public function getModel(): CsvTableMergerModel
    {
        return $this->model;
    }

    public function getImportTable(): string
    {
        return $this->importTable;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function setStoreData(bool $blnStore): void
    {
        $this->blnStore = $blnStore;
    }

    public function getStoreData(): bool
    {
        return $this->blnStore;
    }

    public function setTargetRecord(?array $arrData): void
    {
        $this->arrTargetDataRecord = $arrData;
    }

    public function getTargetRecord(): ?array
    {
        return $this->arrTargetDataRecord;
    }

    public function getCurrentLine(): int
    {
        return $this->currentLine;
    }
}
