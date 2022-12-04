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

namespace Markocupic\ContaoCsvTableMerger\Controller\Ajax;

use Contao\CoreBundle\Framework\ContaoFramework;
use Markocupic\ContaoCsvTableMerger\Merger\Merger;
use Markocupic\ContaoCsvTableMerger\Message\Message;
use Markocupic\ContaoCsvTableMerger\Model\CsvTableMergerModel;
use Markocupic\ContaoCsvTableMerger\Session\Attribute\ArrayAttributeBag;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionBagInterface;
use Symfony\Component\Routing\Annotation\Route;

class VueAppController extends AbstractController
{
    public const INITIALIZE_ROUTE = 'contao_table_merger_action_initialize';
    public const MERGE_ROUTE = 'contao_table_merger_action_merge';

    private ContaoFramework $framework;
    private RequestStack $requestStack;
    private Merger $merger;
    private Message $message;
    private array $appConfig;

    public function __construct(ContaoFramework $framework, RequestStack $requestStack, Merger $merger, Message $message, array $appConfig)
    {
        $this->framework = $framework;
        $this->requestStack = $requestStack;
        $this->merger = $merger;
        $this->message = $message;
        $this->appConfig = $appConfig;
    }

    /**
     * @param string $session_key
     *
     * @throws \Exception
     *
     * @return Response
     */
    #[Route('/_contao_table_merger_action_initialize/{session_key}', name: self::INITIALIZE_ROUTE, defaults: ['_scope' => 'backend', '_token_check' => true])]
    public function initializeAction(string $session_key): Response
    {
        // The ArrayAttributeBag class will get the session key from GET/POST request
        $this->requestStack->getCurrentRequest()->query->set('session_key', $session_key);
        $this->framework->initialize(false);

        $model = $this->getModelFromSession($session_key);

        // Initialize session
        $session = $this->getSessionBag($session_key);

        $json = [];
        $json['success'] = false;

        if ($this->merger->validate($model)) {
            $json['success'] = true;

            // Count records
            $recordCount = $this->merger->getRecordsCount($model);
            $this->message->addInfo('Count records: '.$recordCount);
            $json[ArrayAttributeBag::KEY_RECORD_COUNT] = $recordCount;
            $session->set(ArrayAttributeBag::KEY_RECORD_COUNT, $recordCount);

            // Calculate required requests
            $requiredRequests = ceil($recordCount / $this->appConfig['max_inserts_per_request']);
            $this->message->addInfo('Required requests: '.$requiredRequests);
            $json[ArrayAttributeBag::KEY_REQUESTS_REQUIRED] = $requiredRequests;
            $session->set(ArrayAttributeBag::KEY_REQUESTS_REQUIRED, $requiredRequests);

            $json[ArrayAttributeBag::KEY_REQUESTS_REMAINED] = $requiredRequests;
            $session->set(ArrayAttributeBag::KEY_REQUESTS_REMAINED, $requiredRequests);

            $json[ArrayAttributeBag::KEY_REQUESTS_COMPLETED] = 0;
            $session->set(ArrayAttributeBag::KEY_REQUESTS_COMPLETED, 0);
        }

        // Set the initialized flag
        $session->set(ArrayAttributeBag::KEY_INITIALIZED, true);

        return $this->send($json, $session);
    }

    #[Route('/_contao_table_merger_action_merge/{session_key}', name: self::MERGE_ROUTE, defaults: ['_scope' => 'backend', '_token_check' => true])]
    public function mergeAction(string $session_key): Response
    {
        // The ArrayAttributeBag class will get the session key from GET/POST request
        $this->requestStack->getCurrentRequest()->query->set('session_key', $session_key);
        $this->framework->initialize(false);
        $session = $this->getSessionBag($session_key);
        $model = $this->getModelFromSession($session_key);

        if (true !== $session->get(ArrayAttributeBag::KEY_INITIALIZED)) {
            throw new \Exception('You cannot call VueAppController::mergeAction before VueAppController::initializeAction has been called!');
        }

        $json = [];
        $json['success'] = false;

        $requestsRequired = $session->get(ArrayAttributeBag::KEY_REQUESTS_REQUIRED);

        if (0 === $requestsRequired) {
            $session->set(ArrayAttributeBag::KEY_IMPORT_PROCESS_COMPLETED, true);
            $json[ArrayAttributeBag::KEY_IMPORT_PROCESS_COMPLETED] = true;
            $json['success'] = true;
            $this->message->addInfo('Import process completed. No data records found. Please close the window.');

            return $this->send($json, $session);
        }

        $requestsRemained = $session->get(ArrayAttributeBag::KEY_REQUESTS_REMAINED);

        if ($requestsRemained <= 0) {
            // Delete not used records
            // Delete not used records
            // Delete not used records

            $session->set(ArrayAttributeBag::KEY_IMPORT_PROCESS_COMPLETED, true);
            $json[ArrayAttributeBag::KEY_IMPORT_PROCESS_COMPLETED] = true;
            $json['success'] = true;
            $this->message->addInfo('Import process completed. Please close the window.');

            return $this->send($json, $session);
        }

        $requestsRequired = $session->get(ArrayAttributeBag::KEY_REQUESTS_REQUIRED) - 1;
        $session->set(ArrayAttributeBag::KEY_REQUESTS_REQUIRED, $requestsRequired);

        $requestsCompleted = $session->get(ArrayAttributeBag::KEY_REQUESTS_COMPLETED);
        $offset = $requestsCompleted * $this->appConfig['max_inserts_per_request'];
        $limit = $this->appConfig['max_inserts_per_request'];

        $this->merger->run($model, $offset, $limit);
        sleep(1);
        if ($this->message->hasError()) {
            $session->set(ArrayAttributeBag::KEY_IMPORT_PROCESS_STOPPED_WITH_ERROR, true);
            $json[ArrayAttributeBag::KEY_IMPORT_PROCESS_STOPPED_WITH_ERROR] = true;
            $json['success'] = false;
        } else {
            ++$requestsCompleted;
            $session->set(ArrayAttributeBag::KEY_REQUESTS_COMPLETED, $requestsCompleted);
            $json[ArrayAttributeBag::KEY_REQUESTS_COMPLETED] = $requestsCompleted;
            --$requestsRemained;
            $session->set(ArrayAttributeBag::KEY_REQUESTS_REMAINED, $requestsRemained);
            $json[ArrayAttributeBag::KEY_REQUESTS_REMAINED] = $requestsRemained;

            $json['success'] = true;
        }

        return $this->send($json, $session);
    }

    /**
     * @throws \Exception
     */
    private function getModelFromSession(string $session_key): CsvTableMergerModel
    {
        $session = $this->getSessionBag($session_key);

        $arrModel = $session->get(ArrayAttributeBag::KEY_MODEL);
        $csvTableMergerModelAdapter = $this->framework->getAdapter(CsvTableMergerModel::class);

        return $csvTableMergerModelAdapter->findByPk($arrModel['id']);
    }

    private function getSessionBag(string $session_key): SessionBagInterface
    {
        return $this->requestStack->getCurrentRequest()->getSession()->getBag(ArrayAttributeBag::NAME);
    }

    private function getMessages(): void
    {
        $this->message->getAll();
    }

    private function send(array $json, $session): JsonResponse
    {
        // Add messages
        $messages = \Contao\Message::generateUnwrapped();
        $session->set(ArrayAttributeBag::KEY_MESSAGES, $session->get('messages', '').$messages);
        $json[ArrayAttributeBag::KEY_MESSAGES] = $messages;

        return new JsonResponse($json);
    }
}
