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

namespace Markocupic\ContaoCsvTableMerger\Merger;

use Contao\CoreBundle\Framework\ContaoFramework;
use Markocupic\ContaoCsvTableMerger\Model\CsvTableMergerModel;
use Markocupic\ContaoCsvTableMerger\Session\Attribute\ArrayAttributeBag;
use Symfony\Component\HttpFoundation\RequestStack;

class MergeMonitorFactory
{
    private RequestStack $requestStack;
    private array $appConfig;
    private ContaoFramework $framework;

    public function __construct(ContaoFramework $framework, RequestStack $requestStack, array $appConfig)
    {
        $this->framework = $framework;
        $this->requestStack = $requestStack;
        $this->appConfig = $appConfig;
    }

    /**
     * @param $sessionKey
     *
     * @throws \Exception
     */
    public function createNew(CsvTableMergerModel $model, $sessionKey): MergeMonitor
    {
        $request = $this->requestStack->getCurrentRequest();

        return new MergeMonitor($this->framework, $model, $request, $sessionKey, $this->appConfig, true);
    }

    /**
     * @param $sessionKey
     *
     * @throws \Exception
     */
    public function getFromSessionKey($sessionKey): MergeMonitor
    {
        $session = $this->requestStack->getCurrentRequest()->getSession()->getBag(ArrayAttributeBag::NAME);

        if (null === ($arrMonitor = $session->get($sessionKey)) || !isset($arrMonitor[MergeMonitor::KEY_MODEL]['id'])) {
            throw new \Exception('Merge Monitor has not been initialized.');
        }
        $request = $this->requestStack->getCurrentRequest();

        $csvTableMergerModelAdapter = $this->framework->getAdapter(CsvTableMergerModel::class);
        $model = $csvTableMergerModelAdapter->findByPk($arrMonitor[MergeMonitor::KEY_MODEL]['id']);

        return new MergeMonitor($this->framework, $model, $request, $sessionKey, $this->appConfig);
    }
}
