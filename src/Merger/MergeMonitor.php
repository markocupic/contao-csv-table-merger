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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionBagInterface;

/**
 * This class acts as an intermediate layer between the application
 * and the session.
 *
 * The session key is generated in the list view of the merging module in the Contao backend
 * and added as GET parameter to the "start merging process" href.
 *
 * All error- and info messages are stored in the session under the key "$session_key".
 * This is why the session key has to be transmitted with every ajax request.
 */
class MergeMonitor
{
    public const KEY_MODEL = 'model';
    public const KEY_MAX_INSERTS_PER_REQUEST = 'max_inserts_per_request';
    public const KEY_INITIALIZED = 'initialized';
    public const KEY_RECORD_COUNT = 'record_count';
    public const KEY_REQUESTS_REQUIRED = 'requests_required';
    public const KEY_REQUESTS_COMPLETED = 'requests_completed';
    public const KEY_REQUESTS_PENDING = 'requests_pending';
    public const KEY_MERGING_PROCESS_COMPLETED = 'merging_process_completed';
    public const KEY_MERGING_PROCESS_STOPPED_WITH_ERROR = 'merging_process_stopped_with_error';
    public const KEY_COUNT_INSERTS = 'count_inserts';
    public const KEY_COUNT_UPDATES = 'count_updates';
    public const KEY_COUNT_DELETIONS = 'count_deletions';

    public const KEY_MESSAGES = 'messages';

    private ContaoFramework $framework;
    private CsvTableMergerModel $model;
    private string $sessionKey;
    private Request $request;
    private array $appConfig;
    private SessionBagInterface $session;

    public function __construct(ContaoFramework $framework, CsvTableMergerModel $model, Request $request, string $sessionKey, array $appConfig, bool $blnForceInitialization = false)
    {
        $this->framework = $framework;
        $this->model = $model;
        $this->request = $request;
        $this->sessionKey = $sessionKey;
        $this->appConfig = $appConfig;

        $this->session = $this->request->getSession()->getBag(ArrayAttributeBag::NAME);

        if ($blnForceInitialization) {
            $this->initialize();
        }

        $this->addMessagesToArchive();
    }

    public function initialize(): void
    {
        $sessionAttributes = [
            self::KEY_MODEL => $this->model->row(),
            self::KEY_INITIALIZED => false,
            self::KEY_MAX_INSERTS_PER_REQUEST => $this->appConfig['max_inserts_per_request'],
            self::KEY_RECORD_COUNT => -1,
            self::KEY_REQUESTS_COMPLETED => 0,
            self::KEY_REQUESTS_PENDING => -1,
            self::KEY_REQUESTS_REQUIRED => -1,
            self::KEY_MERGING_PROCESS_COMPLETED => false,
            self::KEY_MERGING_PROCESS_STOPPED_WITH_ERROR => false,
            self::KEY_COUNT_INSERTS => 0,
            self::KEY_COUNT_UPDATES => 0,
            self::KEY_COUNT_DELETIONS => 0,
            self::KEY_MESSAGES => [
                'archive' => [],
                'current_request' => [],
            ],
        ];

        $this->session->set($this->getSessionKey(), $sessionAttributes);
    }

    public function getSessionKey(): string
    {
        return $this->sessionKey;
    }

    public function getModel(): CsvTableMergerModel
    {
        $arrModel = $this->get(self::KEY_MODEL);
        $csvTableMergerModelAdapter = $this->framework->getAdapter(CsvTableMergerModel::class);

        return $csvTableMergerModelAdapter->findByPk($arrModel['id']);
    }

    public function addInfoMessage(string $message): void
    {
        $this->addMessageOfType('info', $message);
    }

    public function addErrorMessage(string $message): void
    {
        $this->addMessageOfType('error', $message);
    }

    public function hasInfoMessage(): bool
    {
        return $this->hasMessageOfType('info');
    }

    public function hasErrorMessage(): bool
    {
        return $this->hasMessageOfType('error');
    }

    public function getMessagesAll(): array
    {
        $arrBag = $this->session->get($this->getSessionKey());

        return $arrBag[self::KEY_MESSAGES]['current_request'];
    }

    public function getInfoMessagesAll(): array
    {
        return $this->getAllMessagesOfType('info');
    }

    public function getErrorMessagesAll(): array
    {
        return $this->getAllMessagesOfType('error');
    }

    /**
     * @return mixed|null
     */
    public function get(string $key)
    {
        $arrBag = $this->session->get($this->getSessionKey());

        return $arrBag[$key] ?? null;
    }

    /**
     * @param $varValue
     */
    public function set(string $key, $varValue): void
    {
        $arrBag = $this->session->get($this->getSessionKey());
        $arrBag[$key] = $varValue;

        $this->session->set($this->getSessionKey(), $arrBag);
    }

    public function addMessagesToArchive(): void
    {
        $arrBag = $this->session->get($this->getSessionKey());
        $arrMessagesCurrent = $arrBag[self::KEY_MESSAGES]['current_request'];
        $arrMessagesArchive = $arrBag[self::KEY_MESSAGES]['archive'];
        $arrBag[self::KEY_MESSAGES]['archive'] = array_merge($arrMessagesArchive, $arrMessagesCurrent);
        $arrBag[self::KEY_MESSAGES]['current_request'] = [];

        $this->session->set($this->getSessionKey(), $arrBag);
    }

    private function addMessageOfType(string $type, string $message): void
    {
        $arrBag = $this->session->get($this->getSessionKey());

        $arrMsg = [
            'type' => $type,
            'request' => $this->request->getRequestUri(),
            'message' => $message,
        ];

        $arrBag[self::KEY_MESSAGES]['current_request'][] = $arrMsg;
        $this->session->set($this->getSessionKey(), $arrBag);
    }

    private function hasMessageOfType(string $type): bool
    {
        $arrBag = $this->session->get($this->getSessionKey());
        $arrMessages = $arrBag[self::KEY_MESSAGES]['current_request'];

        foreach ($arrMessages as $arrMessage) {
            if ($arrMessage['type'] === $type) {
                return true;
            }
        }

        return false;
    }

    private function getAllMessagesOfType(string $type): array
    {
        $arrBag = $this->session->get($this->getSessionKey());
        $arrMessages = $arrBag[self::KEY_MESSAGES]['current_request'];

        $arrMessagesReturn = [];

        foreach ($arrMessages as $arrMessage) {
            if ($arrMessage['type'] === $type) {
                $arrMessagesReturn[] = $arrMessage;
            }
        }

        return $arrMessagesReturn;
    }
}
