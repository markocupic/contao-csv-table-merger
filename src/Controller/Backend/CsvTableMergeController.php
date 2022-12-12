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

namespace Markocupic\ContaoCsvTableMerger\Controller\Backend;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\DataContainer;
use Markocupic\ContaoCsvTableMerger\Controller\Ajax\VueAppController;
use Markocupic\ContaoCsvTableMerger\Merger\MergeMonitorFactory;
use Markocupic\ContaoCsvTableMerger\Model\CsvTableMergerModel;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment as TwigEnvironment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class CsvTableMergeController
{
    private ContaoFramework $framework;
    private TwigEnvironment $twig;
    private UrlGeneratorInterface $router;
    private MergeMonitorFactory $mergeMonitorFactory;

    public function __construct(ContaoFramework $framework, TwigEnvironment $twig, UrlGeneratorInterface $router, MergeMonitorFactory $mergeMonitorFactory)
    {
        $this->framework = $framework;
        $this->twig = $twig;
        $this->router = $router;
        $this->mergeMonitorFactory = $mergeMonitorFactory;
    }

    /**
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function appAction(DataContainer $dc): Response
    {
        $csvTableMergerAdapter = $this->framework->getAdapter(CsvTableMergerModel::class);
        $model = $csvTableMergerAdapter->findByPk($dc->id);

        if (null === $model) {
            throw new \Exception(sprintf('Model with id %d not found.', $dc->id));
        }

        // Create new MergeMonitor object
        $sessionKey = md5(Uuid::uuid4()->toString());
        $this->mergeMonitorFactory->createNew($model, $sessionKey);

        return new Response($this->twig->render(
            '@MarkocupicContaoCsvTableMerger/backend/app.html.twig',
            [
                'model' => $model->row(),
                'options' => [
                    'delimiters' => "'[[',']]'",
                    'session_key' => $sessionKey,
                    'routes' => [
                        'initialize' => $this->router->generate(VueAppController::INITIALIZE_ROUTE, ['session_key' => $sessionKey]),
                        'merge' => $this->router->generate(VueAppController::MERGE_ROUTE, ['session_key' => $sessionKey]),
                    ],
                ],
            ]
        ));
    }
}
