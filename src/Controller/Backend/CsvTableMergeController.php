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

use Contao\Controller;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\DataContainer;
use Doctrine\DBAL\Exception;
use JustSteveKing\UriBuilder\Uri;
use Markocupic\ContaoCsvTableMerger\Controller\Ajax\VueAppController;
use Markocupic\ContaoCsvTableMerger\Merger\MergeMonitorProvider;
use Markocupic\ContaoCsvTableMerger\Merger\Merger;
use Markocupic\ContaoCsvTableMerger\Model\CsvTableMergerModel;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment as TwigEnvironment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class CsvTableMergeController
{
    private ContaoFramework $framework;
    private RequestStack $requestStack;
    private TwigEnvironment $twig;
    private UrlGeneratorInterface $router;
    private Merger $merger;
    private MergeMonitorProvider $mergeMonitorProvider;

    public function __construct(ContaoFramework $framework, RequestStack $requestStack, TwigEnvironment $twig, UrlGeneratorInterface $router, Merger $merger, MergeMonitorProvider $mergeMonitorProvider)
    {
        $this->framework = $framework;
        $this->requestStack = $requestStack;
        $this->twig = $twig;
        $this->router = $router;
        $this->merger = $merger;
        $this->mergeMonitorProvider = $mergeMonitorProvider;
    }

    /**
     * @throws Exception
     */
    public function runMergingProcessAction(DataContainer $dc): Response
    {
        $controllerAdapter = $this->framework->getAdapter(Controller::class);
        $csvTableMergerAdapter = $this->framework->getAdapter(CsvTableMergerModel::class);

        // Load language file
        $controllerAdapter->loadLanguageFile('tl_csv_table_merger');

        $model = $csvTableMergerAdapter->findByPk($dc->id);

        $this->merger->run($model);

        $request = $this->requestStack->getCurrentRequest();
        $url = Uri::fromString($request->getUri());
        $url->addQueryParam('key', 'renderSummaryAction');

        return new RedirectResponse($url->toString());
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
        $sessionKey = Uuid::uuid4()->toString();
        $this->mergeMonitorProvider->createNew($model, $sessionKey);

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
