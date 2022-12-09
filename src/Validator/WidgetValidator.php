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
use Markocupic\ContaoCsvTableMerger\Merger\MergeMonitor;
use Markocupic\ContaoCsvTableMerger\Model\CsvTableMergerModel;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;
use function Safe\json_encode;

class WidgetValidator
{
    private ContaoFramework $framework;
    private Connection $connection;
    private RequestStack $requestStack;
    private TranslatorInterface $translator;
    private Formatter $formatter;

    private Adapter $controllerAdapter;
    private Adapter $inputAdapter;
    private Adapter $stringUtilAdapter;
    private Adapter $validatorAdapter;

    public function __construct(ContaoFramework $framework, Connection $connection, RequestStack $requestStack, TranslatorInterface $translator, Formatter $formatter)
    {
        $this->framework = $framework;
        $this->connection = $connection;
        $this->requestStack = $requestStack;
        $this->translator = $translator;
        $this->formatter = $formatter;

        // Adapters
        $this->controllerAdapter = $this->framework->getAdapter(Controller::class);
        $this->inputAdapter = $this->framework->getAdapter(Input::class);
        $this->stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);
        $this->validatorAdapter = $this->framework->getAdapter(Validator::class);

    }

    /**
     * @return array|mixed|string|array<string>
     */
    public function validate(string $fieldName, string $tableName, $varValue, CsvTableMergerModel $model, MergeMonitor $mergeMonitor, int $line, string $mode = 'insert')
    {
        // Set $_POST, so the content can be validated
        $request = $this->requestStack->getCurrentRequest();

        // Get data container array
        $arrDca = $this->getDca($fieldName, $tableName);

        // Map checkboxWizards to regular checkbox widgets
        if ('checkboxWizard' === $arrDca['inputType']) {
            $arrDca['inputType'] = 'checkbox';
        }

        // Convert strings to array 1||2 => [1,2]
        $varValue = $this->formatter->convertToArray($varValue, $arrDca, $model->arrayDelimiter);

        // Set the correct date format See Config::get('date') and Config::get('datim')
        $varValue = $this->formatter->convertToCorrectDateFormat($varValue, $arrDca);

        // Special treatment for password
        if ('password' === $arrDca['inputType']) {
            $this->inputAdapter->setPost('password_confirm', $varValue);
            $request->request->set('password_confirm', $varValue);
        }

        // Important! Widget::validate() takes the field value from $_POST.
        $this->inputAdapter->setPost($fieldName, $varValue);
        $request->request->set($fieldName, $varValue);

        // Get the correct widget for input validation, etc.
        $widget = $this->getWidgetFromDca($arrDca, $fieldName, $tableName, $varValue);

        // Skip validation for selected fields
        $arrSkipValidation = $this->stringUtilAdapter->deserialize($model->skipValidationFields, true);

        if (!\in_array($widget->strField, $arrSkipValidation, true)) {
            // Validate input
            $widget->validate();
        }

        $widget->value = $this->formatter->convertDateToTimestamp($widget->value, $arrDca);
        $widget->value = $this->formatter->replaceNewlineTags($widget->value);

        // Set correct empty value
        if (empty($widget->value)) {
            $widget->value = $widget->getEmptyValue();
        }

        $intLimit = 'insert' === $mode ? 0 : 1;
        $widget = $this->validateIsUnique($widget, $arrDca, $line, $intLimit);

        if ($widget->hasErrors()) {
            foreach ($widget->getErrors() as $error) {
                $mergeMonitor->addErrorMessage(sprintf('Line #%d "%s" => "%s": ', $line, $widget->strField, \is_array($widget->value) ? json_encode($widget->value) : (string) $widget->value).$error);
            }
        }

        return $widget->value;
    }

    /**
     * @return array<string>
     */
    private function getDca(string $fieldName, string $tableName): array
    {
        $this->controllerAdapter->loadDataContainer($tableName);

        if (!empty($GLOBALS['TL_DCA'][$tableName]['fields'][$fieldName]) && \is_array($GLOBALS['TL_DCA'][$tableName]['fields'][$fieldName])) {
            $arrDca = &$GLOBALS['TL_DCA'][$tableName]['fields'][$fieldName];

            if (!empty($arrDca['inputType']) && \is_string($arrDca['inputType'])) {
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

    /**
     * @param $line
     *
     * @return Widget
     *                Not used at the moment
     */
    private function validateDate(Widget $widget, array $arrDca, $line): Widget
    {
        $varValue = $widget->value;
        $rgxp = $arrDca['eval']['rgxp'] ?? null;

        if (!$rgxp || !\strlen((string) $varValue)) {
            return $widget;
        }

        if ('date' === $rgxp || 'datim' === $rgxp || 'time' === $rgxp) {
            if (!$this->validatorAdapter->{'is'.ucfirst($rgxp)}($varValue)) {
                $widget->addError(
                    $this->translator->trans('ERR.invalidDate', [], 'contao_default'),
                );
            }
        }

        return $widget;
    }

    /**
     * @throws Exception
     */
    private function validateIsUnique(Widget $widget, array $arrDca, $line, int $limit): Widget
    {
        // Make sure that unique fields are unique
        if (isset($arrDca['eval']['unique']) && true === $arrDca['eval']['unique']) {
            $varValue = $widget->value;

            if (\strlen((string) $varValue)) {
                $query = sprintf(
                    'SELECT COUNT(%s) as counter FROM %s WHERE %s = ?',
                    $widget->strField,
                    $widget->strTable,
                    $widget->strField,
                );

                $result = $this->connection->fetchOne($query, [$varValue]);
                $count = !$result ? 0 : $result;

                if ($limit < $count) {
                    $widget->addError(
                        $this->translator->trans('ERR.unique', [], 'contao_default'),
                    );
                }
            }
        }

        return $widget;
    }
}
