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

    public function getCorrectDateFormat(Widget $widget, array $arrDca): Widget
    {
        $varValue = $widget->value;

        $rgxp = $arrDca['eval']['rgxp'] ?? null;

        if (('date' === $rgxp || 'datim' === $rgxp || 'time' === $rgxp) && '' !== $varValue) {
            $configAdapter = $this->framework->getAdapter(Config::class);
            $dateFormat = $configAdapter->get($rgxp.'Format');

            if (false !== ($tstamp = strtotime($varValue))) {
                $varValue = date($dateFormat, $tstamp);
            }
        }

        $widget->value = $varValue;

        return $widget;
    }

    public function convertToArray(Widget $widget, array $arrDca, string $arrayDelimiter): Widget
    {
        $varValue = $widget->value;

        if (!\is_array($varValue) && isset($arrDca['eval']['multiple']) && $arrDca['eval']['multiple']) {
            // Convert CSV fields
            if (isset($arrDca['eval']['csv'])) {
                if (null === $varValue || '' === $varValue) {
                    $varValue = [];
                } else {
                    $varValue = explode($arrDca['eval']['csv'], $varValue);
                }
            } elseif (false !== strpos($varValue, $arrayDelimiter)) {
                // Value is e.g. 3||4
                $varValue = explode($arrayDelimiter, $varValue);
            } else {
                /** @var StringUtil $stringUtilAdapter */
                $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);

                // The value is a serialized array or simple value e.g 3
                $varValue = $stringUtilAdapter->deserialize($varValue, true);
            }
        }

        $widget->value = $varValue;

        return $widget;
    }

    public function convertDateToTimestamp(Widget $widget, array $arrDca): Widget
    {
        $rgxp = $arrDca['eval']['rgxp'] ?? null;

        if ('date' === $rgxp || 'datim' === $rgxp || 'time' === $rgxp) {
            $widget->value = trim((string) $widget->value);

            if (empty($widget->value)) {
                $widget->value = $widget->getEmptyValue();

                return $widget;
            }

            if (false !== ($widget->value = strtotime($widget->value))) {
                return $widget;
            }

            $widget->addError(sprintf('Invalid value "%s" set for field "%s.%s".', $widget->value, $widget->strTable, $widget->strField));
        }

        return $widget;
    }

    public function replaceNewlineTags(Widget $widget): Widget
    {
        if (\is_string($widget->value)) {
            // Replace all '[NEWLINE]' tags with the end of line tag
            $widget->value = str_replace('[NEWLINE]', PHP_EOL, $widget->value);
        }

        return $widget;
    }
}
