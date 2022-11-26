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

namespace Markocupic\ContaoCsvTableMerger\EventListener\ContaoHook\BeforeUpdate;

use Contao\CoreBundle\ServiceAnnotation\Hook;
use Markocupic\ContaoCsvTableMerger\DataRecord\DataRecord;
use Markocupic\ContaoCsvTableMerger\EventListener\ContaoHook\AbstractContaoHook;

//use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;

/**
 * @Hook(BeforeUpdateListener::HOOK, priority=BeforeUpdateListener::PRIORITY)
 */
//#[AsHook(BeforeUpdateListener::HOOK, priority: BeforeUpdateListener::PRIORITY)]
class BeforeUpdateListener extends AbstractContaoHook
{
    public const HOOK = 'csvTableMergerBeforeUpdate';
    public const PRIORITY = 500;

    public function __construct()
    {
    }

    public function __invoke(DataRecord $dataRecord): DataRecord
    {
        if (static::$disableHook) {
            return $dataRecord;
        }

        // No actions at the moment.

        return $dataRecord;
    }
}
