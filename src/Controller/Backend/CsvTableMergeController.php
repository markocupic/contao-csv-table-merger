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
use Haste\Util\Url;
use Markocupic\ContaoCsvTableMerger\Merger\Merger;
use Markocupic\ContaoCsvTableMerger\Message\Message;
use Markocupic\ContaoCsvTableMerger\Model\CsvTableMergerModel;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment as TwigEnvironment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class CsvTableMergeController
{
    private ContaoFramework $framework;
    private TwigEnvironment $twig;
    private Merger $merger;
    private Message $message;

    public function __construct(ContaoFramework $framework, TwigEnvironment $twig, Merger $merger, Message $message)
    {
        $this->framework = $framework;
        $this->twig = $twig;
        $this->merger = $merger;
        $this->message = $message;
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

        $url = Url::addQueryString('key=renderSummaryAction');

        return new RedirectResponse($url);
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
