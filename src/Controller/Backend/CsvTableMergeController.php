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
use Markocupic\ContaoCsvTableMerger\Merger\Merger;
use Markocupic\ContaoCsvTableMerger\Message\Message;
use Markocupic\ContaoCsvTableMerger\Model\CsvTableMergerModel;
use Markocupic\ContaoCsvTableMerger\Session\Attribute\ArrayAttributeBag;
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
    private Merger $merger;
    private Message $message;
    private UrlGeneratorInterface $router;
    private array $appConfig;

    public function __construct(UrlGeneratorInterface $router, ContaoFramework $framework, RequestStack $requestStack, TwigEnvironment $twig, Merger $merger, Message $message, array $appConfig)
    {
        $this->router = $router;
        $this->framework = $framework;
        $this->requestStack = $requestStack;
        $this->twig = $twig;
        $this->merger = $merger;
        $this->message = $message;
        $this->appConfig = $appConfig;
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
     * @throws Exception
     */
    public function appAction(DataContainer $dc): Response
    {
        // Get the session key from $_GET
        $sessionKey = $this->requestStack->getCurrentRequest()->get('session_key');

        if (!$sessionKey) {
            throw new \Exception('No session key provided.');
        }

        $csvTableMergerAdapter = $this->framework->getAdapter(CsvTableMergerModel::class);
        $model = $csvTableMergerAdapter->findByPk($dc->id);

        if (null === $model) {
            throw new \Exception(sprintf('Model with id %d not found.', $dc->id));
        }

        /** @var ArrayAttributeBag $session */
        $session = $this->requestStack->getCurrentRequest()->getSession()->getBag(ArrayAttributeBag::NAME);
        $sessionAttributes = [
            ArrayAttributeBag::KEY_MODEL => $model->row(),
            ArrayAttributeBag::KEY_INITIALIZED => false,
            ArrayAttributeBag::KEY_MAX_INSERTS_PER_REQUEST => $this->appConfig['max_inserts_per_request'],
            ArrayAttributeBag::KEY_RECORD_COUNT => -1,
            ArrayAttributeBag::KEY_REQUESTS_COMPLETED => 0,
            ArrayAttributeBag::KEY_REQUESTS_PENDING => -1,
            ArrayAttributeBag::KEY_REQUESTS_REQUIRED => -1,
            ArrayAttributeBag::KEY_MESSAGES => '',
            ArrayAttributeBag::KEY_MERGING_PROCESS_COMPLETED => false,
            ArrayAttributeBag::KEY_MERGING_PROCESS_STOPPED_WITH_ERROR => false,
        ];

        foreach ($sessionAttributes as $key => $value) {
            $session->set($key, $value);
        }

        // Load language file
        //$controllerAdapter = $this->framework->getAdapter(Controller::class);
        //$controllerAdapter->loadLanguageFile('tl_csv_table_merger');

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

    /**
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function renderSummaryAction(DataContainer $dc): Response
    {
        $controllerAdapter = $this->framework->getAdapter(Controller::class);
        $csvTableMergerAdapter = $this->framework->getAdapter(CsvTableMergerModel::class);

        // Load language file
        $controllerAdapter->loadLanguageFile('tl_csv_table_merger');

        $model = $csvTableMergerAdapter->findByPk($dc->id);

        $messages = [];

        $messages['has_info'] = $this->message->hasInfo();
        $messages['infos'] = $this->message->getInfo();
        $messages['has_error'] = $this->message->hasError();
        $messages['errors'] = $this->message->getError();

        return new Response($this->twig->render(
            '@MarkocupicContaoCsvTableMerger/backend/app.html.twig',
            [
                'import_table' => $model->importTable,
                'messages' => $messages,
            ]
        ));
    }
}
