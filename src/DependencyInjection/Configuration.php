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

namespace Markocupic\ContaoCsvTableMerger\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public const ROOT_KEY = 'markocupic_contao_csv_table_merger';

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(self::ROOT_KEY);

        $treeBuilder->getRootNode()
            ->children()
                ->integerNode('max_inserts_per_request')
                    ->defaultValue(50)
                ->end()
                ->scalarNode('google_api_key')
                    ->defaultValue('')
                ->end()
                ->arrayNode('fields_newer_update')
                    ->info('A list of table fields that are not allowed to update.')
                    ->example(['id', 'dateAdded', 'password'])
                    ->defaultValue(['id', 'password', 'dateAdded', 'addedOn'])
                    ->scalarPrototype()->end()
                ->end()
             ->end()
        ;

        return $treeBuilder;
    }
}
