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

namespace Markocupic\ContaoCsvTableMerger\EventListener\ContaoHook;

abstract class AbstractContaoHook implements ContaoHookInterface
{
    protected static bool $disableHook = false;

    public static function disableHook(): void
    {
        static::$disableHook = true;
    }

    public static function enableHook(): void
    {
        static::$disableHook = false;
    }

    public static function isEnabled(): bool
    {
        return static::$disableHook;
    }
}
