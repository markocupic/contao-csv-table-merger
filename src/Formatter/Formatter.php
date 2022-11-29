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

namespace Markocupic\ContaoCsvTableMerger\Formatter;

use Contao\Config;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\StringUtil;
use Contao\Widget;

class Formatter
{
    private ContaoFramework $framework;

    public function __construct(ContaoFramework $framework)
    {
        $this->framework = $framework;
    }

    public function convertToCorrectDateFormat($varValue, array $arrDca)
    {
        $rgxp = $arrDca['eval']['rgxp'] ?? null;

        if (('date' === $rgxp || 'datim' === $rgxp || 'time' === $rgxp) && '' !== $varValue) {
            $configAdapter = $this->framework->getAdapter(Config::class);
            $dateFormat = $configAdapter->get($rgxp.'Format');

            if (false !== ($tstamp = strtotime($varValue))) {
                $varValue = date($dateFormat, $tstamp);
            }
        }

        return $varValue;
    }

    public function convertToArray($varValue, array $arrDca, string $arrayDelimiter)
    {
        /** @var StringUtil $stringUtilAdapter */
        $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);

        if (!\is_array($varValue) && isset($arrDca['eval']['multiple']) && true === $arrDca['eval']['multiple']) {
            $varValue = (string) $varValue;

            if ('' === $varValue) {
                $varValue = [];
            } elseif (isset($arrDca['eval']['csv']) && \strlen((string) $arrDca['eval']['csv'])) {
                $varValue = $stringUtilAdapter->trimsplit($arrDca['eval']['csv'], $varValue);
            } elseif (\strlen($arrayDelimiter) && false !== strpos($varValue, $arrayDelimiter)) {
                // Value is e.g. 3||4
                $varValue = explode($arrayDelimiter, $varValue);
            } else {
                // The value is a serialized array or simple value e.g 3
                $varValue = $stringUtilAdapter->deserialize($varValue, true);
            }
        }

        return $varValue;
    }

    public function convertDateToTimestamp($varValue, array $arrDca)
    {
        $rgxp = $arrDca['eval']['rgxp'] ?? null;

        if ('date' === $rgxp || 'datim' === $rgxp || 'time' === $rgxp) {
            $varValue = trim((string) $varValue);

            if (empty($varValue)) {
                /** @var Widget $widgetAdapter */
                $widgetAdapter = $this->framework->getAdapter(Widget::class);

                return $widgetAdapter->getEmptyValueByFieldType($arrDca['sql'] ?? null);
            }

            if (false !== ($varValue = strtotime($varValue))) {
                return $varValue;
            }
        }

        return $varValue;
    }

    public function replaceNewlineTags($varValue)
    {
        if (\is_string($varValue)) {
            // Replace all '[NEWLINE]' tags with the end of line tag
            $varValue = str_replace('[NEWLINE]', PHP_EOL, $varValue);
        }

        return $varValue;
    }
}
