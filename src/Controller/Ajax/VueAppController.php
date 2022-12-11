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
use Contao\CoreBundle\Security\ContaoCorePermissions;
use Doctrine\DBAL\Exception;
use Markocupic\ContaoCsvTableMerger\Merger\MergeMonitor;
use Markocupic\ContaoCsvTableMerger\Merger\MergeMonitorFactory;
use Markocupic\ContaoCsvTableMerger\Merger\Merger;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;

class VueAppController extends AbstractController
{
    public const INITIALIZE_ROUTE = 'contao_table_merger_action_initialize';
    public const MERGE_ROUTE = 'contao_table_merger_action_merge';

    private ContaoFramework $framework;
    private Security $security;
    private Merger $merger;
    private MergeMonitorFactory $mergeMonitorFactory;
    private array $appConfig;

    public function __construct(ContaoFramework $framework, Security $security, Merger $merger, MergeMonitorFactory $mergeMonitorFactory, array $appConfig)
    {
        $this->framework = $framework;
        $this->security = $security;
        $this->merger = $merger;
        $this->mergeMonitorFactory = $mergeMonitorFactory;
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
        $this->framework->initialize(false);

        $mergeMonitor = $this->mergeMonitorFactory->getFromSessionKey($session_key);

        // Check if backend user has access.
        $this->checkIsAllowed();

        $json = [];
        $json['success'] = false;

        if (true === $mergeMonitor->get(MergeMonitor::KEY_INITIALIZED)) {
            $mergeMonitor->addErrorMessage('Application has already been initialized. Please go back and restart the application from the beginning.');
            $mergeMonitor->set(MergeMonitor::KEY_MERGING_PROCESS_STOPPED_WITH_ERROR, true);
            $json[MergeMonitor::KEY_MERGING_PROCESS_STOPPED_WITH_ERROR] = true;
            $json['success'] = false;

            return $this->send($json, $mergeMonitor);
        }

        if ($this->merger->validate($mergeMonitor)) {
            $json['success'] = true;

            // Count records
            $recordCount = $this->merger->getRecordsCount($mergeMonitor);
            $mergeMonitor->addInfoMessage('Count records: '.$recordCount);
            $json[MergeMonitor::KEY_RECORD_COUNT] = $recordCount;
            $mergeMonitor->set(MergeMonitor::KEY_RECORD_COUNT, $recordCount);

            // Calculate required requests
            $requiredRequests = ceil($recordCount / $this->appConfig['max_inserts_per_request']);
            $mergeMonitor->addInfoMessage('Required requests: '.$requiredRequests);
            $json[MergeMonitor::KEY_REQUESTS_REQUIRED] = $requiredRequests;
            $mergeMonitor->set(MergeMonitor::KEY_REQUESTS_REQUIRED, $requiredRequests);

            $json[MergeMonitor::KEY_REQUESTS_PENDING] = $requiredRequests;
            $mergeMonitor->set(MergeMonitor::KEY_REQUESTS_PENDING, $requiredRequests);

            $json[MergeMonitor::KEY_REQUESTS_COMPLETED] = 0;
            $mergeMonitor->set(MergeMonitor::KEY_REQUESTS_COMPLETED, 0);
        }

        // Set the initialized flag
        $mergeMonitor->set(MergeMonitor::KEY_INITIALIZED, true);

        return $this->send($json, $mergeMonitor);
    }

    /**
     * @param string $session_key
     *
     * @throws Exception
     *
     * @return Response
     */
    #[Route('/_contao_table_merger_action_merge/{session_key}', name: self::MERGE_ROUTE, defaults: ['_scope' => 'backend', '_token_check' => true])]
    public function mergeAction(string $session_key): Response
    {
        $this->framework->initialize(false);

        $mergeMonitor = $this->mergeMonitorFactory->getFromSessionKey($session_key);

        // Check if backend user has access.
        $this->checkIsAllowed();

        if (true !== $mergeMonitor->get(MergeMonitor::KEY_INITIALIZED)) {
            throw new \Exception('You can not call VueAppController::mergeAction before VueAppController::initializeAction has been called!');
        }

        $json = [];
        $json['success'] = false;

        $requestsRequired = $mergeMonitor->get(MergeMonitor::KEY_REQUESTS_REQUIRED);

        if (0 === $requestsRequired) {
            $mergeMonitor->set(MergeMonitor::KEY_MERGING_PROCESS_COMPLETED, true);
            $json[MergeMonitor::KEY_MERGING_PROCESS_COMPLETED] = true;
            $json['success'] = true;
            $mergeMonitor->addInfoMessage('Import process completed. No data records found. Please close the window.');

            return $this->send($json, $mergeMonitor);
        }

        $requestsRemained = $mergeMonitor->get(MergeMonitor::KEY_REQUESTS_PENDING);

        if ($requestsRemained <= 0) {
            $mergeMonitor->set(MergeMonitor::KEY_MERGING_PROCESS_COMPLETED, true);
            $json[MergeMonitor::KEY_MERGING_PROCESS_COMPLETED] = true;
            $json['success'] = true;
            $mergeMonitor->addInfoMessage('Import process completed. Please close the window.');

            return $this->send($json, $mergeMonitor);
        }

        $requestsRequired = $mergeMonitor->get(MergeMonitor::KEY_REQUESTS_REQUIRED) - 1;
        $mergeMonitor->set(MergeMonitor::KEY_REQUESTS_REQUIRED, $requestsRequired);

        $requestsCompleted = $mergeMonitor->get(MergeMonitor::KEY_REQUESTS_COMPLETED);
        $offset = $requestsCompleted * $this->appConfig['max_inserts_per_request'];
        $limit = $this->appConfig['max_inserts_per_request'];

        $this->merger->run($mergeMonitor, $offset, $limit);

        if ($mergeMonitor->hasErrorMessage()) {
            $mergeMonitor->set(MergeMonitor::KEY_MERGING_PROCESS_STOPPED_WITH_ERROR, true);
            $json[MergeMonitor::KEY_MERGING_PROCESS_STOPPED_WITH_ERROR] = true;
            $json['success'] = false;
        } else {
            ++$requestsCompleted;
            $mergeMonitor->set(MergeMonitor::KEY_REQUESTS_COMPLETED, $requestsCompleted);
            $json[MergeMonitor::KEY_REQUESTS_COMPLETED] = $requestsCompleted;
            --$requestsRemained;
            $mergeMonitor->set(MergeMonitor::KEY_REQUESTS_PENDING, $requestsRemained);
            $json[MergeMonitor::KEY_REQUESTS_PENDING] = $requestsRemained;

            // Delete records from the db, if they do no more exist in the text file.
            if ($requestsRemained < 1) {
                $model = $mergeMonitor->getModel();

                if ($model->deleteNonExistentRecords) {
                    $mergeMonitor->addInfoMessage('Deleting no more existent records. Please wait...');
                    $this->merger->deleteNonExistentRecords($mergeMonitor);
                }

                if (!$mergeMonitor->hasErrorMessage()) {
                    $mergeMonitor->addInfoMessage('Merging process successfully completed.');
                } else {
                    $json['success'] = false;
                    $mergeMonitor->set(MergeMonitor::KEY_MERGING_PROCESS_STOPPED_WITH_ERROR, true);
                    $json[MergeMonitor::KEY_MERGING_PROCESS_STOPPED_WITH_ERROR] = true;

                    return $this->send($json, $mergeMonitor);
                }
            }

            $json['success'] = true;
        }

        return $this->send($json, $mergeMonitor);
    }

    /**
     * @throws \Exception
     */
    private function checkIsAllowed(): void
    {
        if (!$this->security->isGranted(ContaoCorePermissions::USER_CAN_ACCESS_MODULE, 'csv_table_merger')) {
            throw new \Exception('Access denied to the Contao backend module "csv_table_merger"');
        }
    }

    private function send(array $json, MergeMonitor $mergeMonitor): JsonResponse
    {
        // Add messages and stats to json response
        $json[MergeMonitor::KEY_MESSAGES] = $mergeMonitor->getMessagesAll();
        $json[MergeMonitor::KEY_COUNT_INSERTS] = $mergeMonitor->get(MergeMonitor::KEY_COUNT_INSERTS);
        $json[MergeMonitor::KEY_COUNT_UPDATES] = $mergeMonitor->get(MergeMonitor::KEY_COUNT_UPDATES);
        $json[MergeMonitor::KEY_COUNT_DELETIONS] = $mergeMonitor->get(MergeMonitor::KEY_COUNT_DELETIONS);

        // Move messages to the archive
        $mergeMonitor->addMessagesToArchive();

        return new JsonResponse($json);
    }
}
