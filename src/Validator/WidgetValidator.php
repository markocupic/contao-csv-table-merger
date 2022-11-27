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

namespace Markocupic\ContaoCsvTableMerger\Validator;

use Contao\Controller;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\DC_Table;
use Contao\Input;
use Contao\StringUtil;
use Contao\Validator;
use Contao\Widget;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Markocupic\ContaoCsvTableMerger\Formatter\Formatter;
use Markocupic\ContaoCsvTableMerger\Message\Message;
use Markocupic\ContaoCsvTableMerger\Model\CsvTableMergerModel;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

class WidgetValidator
{
    private ContaoFramework $framework;
    private Connection $connection;
    private RequestStack $requestStack;
    private TranslatorInterface $translator;
    private Formatter $formatter;
    private Message $message;

    private Adapter $controllerAdapter;
    private Adapter $inputAdapter;
    private Adapter $stringUtilAdapter;

    public function __construct(ContaoFramework $framework, Connection $connection, RequestStack $requestStack, TranslatorInterface $translator, Formatter $formatter, Message $message)
    {
        $this->framework = $framework;
        $this->connection = $connection;
        $this->requestStack = $requestStack;
        $this->translator = $translator;
        $this->formatter = $formatter;
        $this->message = $message;

        // Adapters
        $this->controllerAdapter = $this->framework->getAdapter(Controller::class);
        $this->inputAdapter = $this->framework->getAdapter(Input::class);
        $this->stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);
    }

    /**
     * @return array|mixed|string|array<string>
     */
    public function validate(string $fieldName, string $tableName, $varValue, CsvTableMergerModel $model, int $line, string $mode = 'insert')
    {
        // Set $_POST, so the content can be validated
        $request = $this->requestStack->getCurrentRequest();
        $request->request->set($fieldName, $varValue);
        $this->inputAdapter->setPost($fieldName, $varValue);

        // Get data container array
        $arrDca = $this->getDca($fieldName, $tableName);

        // Map checkboxWizards to regular checkbox widgets
        if ('checkboxWizard' === $arrDca['inputType']) {
            $arrDca['inputType'] = 'checkbox';
        }

        // Get the correct widget for input validation, etc.
        $widget = $this->getWidgetFromDca($arrDca, $fieldName, $tableName, $varValue);

        // Convert strings to array
        $widget = $this->formatter->convertToArray($widget, $arrDca, $model->arrayDelimiter);

        // Set the correct date format
        $widget = $this->formatter->getCorrectDateFormat($widget, $arrDca);

        // Validate date, datim or time values
        $widget = $this->validateDate($widget, $arrDca, $line);

        // Special treatment for password
        if ('password' === $arrDca['inputType']) {
            $this->inputAdapter->setPost('password_confirm', $widget->value);
        }

        $arrSkipValidation = $this->stringUtilAdapter->deserialize($model->skipValidationFields, true);

        // Skip validation for selected fields
        if (!\in_array($widget->strField, $arrSkipValidation, true)) {
            // Validate input
            $widget->validate();
        }

        if ('insert' === $mode) {
            $widget = $this->validateIsUnique($widget, $arrDca, $line);
        }

        $widget = $this->formatter->convertDateToTimestamp($widget, $arrDca);
        $widget = $this->formatter->replaceNewlineTags($widget);

        if ($widget->hasErrors()) {
            $this->message->addError($widget->getErrorsAsString(' '));
        }

        // Set correct empty value
        if (empty($widget->value)) {
            $widget->value = $widget->getEmptyValue();
        }

        return $widget->value;
    }

    /**
     * @return array<string>
     */
    private function getDca(string $fieldName, string $tableName): array
    {
        $this->controllerAdapter->loadDataContainer($tableName);

        if (\is_array($GLOBALS['TL_DCA'][$tableName]['fields'][$fieldName])) {
            $arrDca = &$GLOBALS['TL_DCA'][$tableName]['fields'][$fieldName];

            if (\is_string($arrDca['inputType']) && !empty($arrDca['inputType'])) {
                return $arrDca;
            }
        }

        return ['inputType' => 'text'];
    }

    private function getWidgetFromDca(array $arrDca, string $fieldName, string $tableName, $varValue): Widget
    {
        $inputType = $arrDca['inputType'] ?? '';

        $objDca = new DC_Table($tableName);

        $strClass = $GLOBALS['BE_FFL'][$inputType] ?? '';

        if (!empty($strClass) && class_exists($strClass)) {
            return new $strClass($strClass::getAttributesFromDca($arrDca, $fieldName, $varValue, $fieldName, $tableName, $objDca));
        }

        $strClass = $GLOBALS['BE_FFL']['text'];

        return new $strClass($strClass::getAttributesFromDca($arrDca, $fieldName, $varValue, $fieldName, $tableName, $objDca));
    }

    private function validateDate(Widget $widget, array $arrDca, $line): Widget
    {
        $varValue = $widget->value;
        $rgxp = $arrDca['eval']['rgxp'] ?? null;

        if (!$rgxp || !\strlen((string) $varValue)) {
            return $widget;
        }

        $validatorAdapter = $this->framework->getAdapter(Validator::class);

        if ('date' === $rgxp || 'datim' === $rgxp || 'time' === $rgxp) {
            if (!$validatorAdapter->{'is'.ucfirst($rgxp)}($varValue)) {
                $widget->addError(
                    'Line #'.$line.': '.sprintf(
                        $this->translator->trans('ERR.invalidDate', [], 'contao_default'),
                        $widget->value,
                    )
                );
            }
        }

        return $widget;
    }

    /**
     * @throws Exception
     */
    private function validateIsUnique(Widget $widget, array $arrDca, $line): Widget
    {
        // Make sure that unique fields are unique
        if (isset($arrDca['eval']['unique']) && true === $arrDca['eval']['unique']) {
            $varValue = $widget->value;

            if (\strlen((string) $varValue)) {
                $query = sprintf(
                    'SELECT id FROM %s WHERE %s = ?',
                    $widget->strTable,
                    $widget->strField,
                );

                if ($this->connection->fetchOne($query, [$varValue])) {
                    $widget->addError(
                        sprintf(
                            'Line #'.$line.': '.$this->translator->trans('ERR.unique', [], 'contao_default'),
                            $widget->strField,
                        )
                    );
                }
            }
        }

        return $widget;
    }
}
