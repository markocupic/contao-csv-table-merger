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

use Contao\DataContainer;
use Contao\DC_Table;
use Ramsey\Uuid\Uuid;

$GLOBALS['TL_DCA']['tl_csv_table_merger'] = [
    'config'   => [
        'dataContainer'    => DC_Table::class,
        'enableVersioning' => true,
        'sql'              => [
            'keys' => [
                'id' => 'primary',
            ],
        ],
    ],
    'list'     => [
        'sorting'           => [
            'mode'        => DataContainer::MODE_SORTABLE,
            'fields'      => ['importTable ASC'],
            'panelLayout' => 'filter;sort,search,limit',
        ],
        'label'             => [
            'fields' => [
                'title',
                'importTable',
            ],
            'format' => '%s [%s]',
        ],
        'global_operations' => [
            'all' => [
                'href'       => 'act=select',
                'class'      => 'header_edit_all',
                'attributes' => 'onclick="Backend.getScrollOffset()" accesskey="e"',
            ],
        ],
        'operations'        => [
            'edit'   => [
                'href' => 'act=edit',
                'icon' => 'edit.gif',
            ],
            'copy'   => [
                'href' => 'act=copy',
                'icon' => 'copy.svg',
            ],
            'delete' => [
                'href'       => 'act=delete',
                'icon'       => 'delete.gif',
                'attributes' => 'onclick="if (!confirm(\''.($GLOBALS['TL_LANG']['MSC']['deleteConfirm'] ?? null).'\')) return false; Backend.getScrollOffset();"',
            ],
            'show'   => [
                'href' => 'act=show',
                'icon' => 'show.gif',
            ],
            'merge'  => [
                'href'       => 'key=appAction&session_key='.Uuid::uuid4()->toString(),
                'icon'       => 'bundles/markocupiccontaocsvtablemerger/icons/merge_16.svg',
                'attributes' => 'onclick="if (!confirm(\''.($GLOBALS['TL_LANG']['CCTM_MSC']['mergeConfirm'] ?? null).'\')) return false; Backend.getScrollOffset();"',
            ],
        ],
    ],
    'palettes' => [
        'default' => '
            {title_legend},title;
            {settings_legend},importTable,identifier,allowedFields,skipValidationFields,deleteNonExistentRecords;
            {text_file_legend},fileSRC,delimiter,enclosure,arrayDelimiter
        ',
    ],
    'fields'   => [
        'id'                       => [
            'sql' => 'int(10) unsigned NOT NULL auto_increment',
        ],
        'tstamp'                   => [
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        'title'                    => [
            'exclude'   => true,
            'search'    => true,
            'sorting'   => true,
            'filter'    => true,
            'inputType' => 'text',
            'eval'      => ['mandatory' => true, 'decodeEntities' => true, 'maxlength' => 255, 'tl_class' => 'w50', 'tl_class' => 'w50'],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'importTable'              => [
            'exclude'   => true,
            'search'    => true,
            'sorting'   => true,
            'filter'    => true,
            'inputType' => 'select',
            'eval'      => ['multiple' => false, 'mandatory' => true, 'includeBlankOption' => true, 'submitOnChange' => true, 'tl_class' => 'w50'],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'allowedFields'            => [
            'exclude'   => true,
            'inputType' => 'checkbox',
            'eval'      => ['multiple' => true, 'mandatory' => true, 'tl_class' => 'w50'],
            'sql'       => 'blob NULL',
        ],
        'identifier'               => [
            'exclude'   => true,
            'inputType' => 'select',
            'eval'      => ['multiple' => false, 'mandatory' => true, 'tl_class' => 'w50'],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'skipValidationFields'     => [
            'inputType' => 'select',
            'eval'      => ['multiple' => true, 'chosen' => true, 'mandatory' => false, 'tl_class' => 'clr w50'],
            'sql'       => 'blob NULL',
        ],
        'deleteNonExistentRecords' => [
            'exclude'   => true,
            'inputType' => 'checkbox',
            'eval'      => ['tl_class' => 'w50'],
            'sql'       => "char(1) NOT NULL default ''",
        ],
        'fileSRC'                  => [
            'exclude'   => true,
            'inputType' => 'fileTree',
            'eval'      => ['multiple' => false, 'fieldType' => 'radio', 'files' => true, 'filesOnly' => true, 'mandatory' => true, 'extensions' => 'csv', 'submitOnChange' => true, 'tl_class' => 'clr'],
            'sql'       => 'binary(16) NULL',
        ],
        'delimiter'                => [
            'exclude'   => true,
            'inputType' => 'text',
            'default'   => ';',
            'eval'      => ['mandatory' => true, 'maxlength' => 1, 'tl_class' => 'w50'],
            'sql'       => "varchar(4) NOT NULL default ';'",
        ],
        'enclosure'                => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['mandatory' => true, 'useRawRequestData' => true, 'maxlength' => 1, 'tl_class' => 'w50'],
            'sql'       => "varchar(4) NOT NULL default '\"'",
        ],
        'arrayDelimiter'           => [
            'exclude'   => true,
            'inputType' => 'text',
            'default'   => '||',
            'eval'      => ['mandatory' => true, 'useRawRequestData' => true, 'maxlength' => 2, 'tl_class' => 'w50'],
            'sql'       => "varchar(4) NOT NULL default '||'",
        ],
    ],
];
