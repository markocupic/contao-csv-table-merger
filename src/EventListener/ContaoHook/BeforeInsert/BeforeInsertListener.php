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
use Contao\Database;
use Markocupic\ContaoCsvTableMerger\DataRecord\DataRecord;
use Markocupic\ContaoCsvTableMerger\EventListener\ContaoHook\AbstractContaoHook;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

#[AsHook(BeforeInsertListener::HOOK, priority: BeforeInsertListener::PRIORITY)]
class BeforeInsertListener extends AbstractContaoHook
{
    public const HOOK = 'csvTableMergerBeforeInsert';
    public const PRIORITY = 500;

    private PasswordHasherFactoryInterface $passwordHasherFactory;

    public function __construct(PasswordHasherFactoryInterface $passwordHasherFactory)
    {
        $this->passwordHasherFactory = $passwordHasherFactory;
    }

    public function __invoke(DataRecord $dataRecord): DataRecord
    {
        if (static::$disableHook) {
            return $dataRecord;
        }

        $arrRecord = $dataRecord->getData();

        if (!isset($arrRecord['tstamp']) || empty($arrRecord['tstamp'])) {
            if (Database::getInstance()->fieldExists('tstamp', $dataRecord->getImportTable())) {
                $arrRecord['tstamp'] = time();
            }
        }

        if (!isset($arrRecord['addedOn']) || empty($arrRecord['addedOn'])) {
            if (Database::getInstance()->fieldExists('addedOn', $dataRecord->getImportTable())) {
                $arrRecord['addedOn'] = time();
            }
        }

        if (!isset($arrRecord['dateAdded']) || empty($arrRecord['dateAdded'])) {
            if (Database::getInstance()->fieldExists('dateAdded', $dataRecord->getImportTable())) {
                $arrRecord['dateAdded'] = time();
            }
        }

        $dataRecord->setData($arrRecord);

        return $dataRecord;
    }
}
