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

namespace Markocupic\ContaoCsvTableMerger\Session\Attribute;

use Contao\CoreBundle\Framework\ContaoFramework;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBag;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Class ArrayAttributeBag.
 *
 * ##session_key## is mandatory and is sent as a post or get parameter on every request.
 *
 * The session data of each rbb instance is stored under $_SESSION[_markocupic_contao_csv_table_merger_attributes][##session_key##]
 *
 * The module key (#moduleId_#moduleIndex f.ex. 33_0) contains the module id and the module index
 * The module index is 0, if the current module is the first rbb module on the current page
 * The module index is 1, if the current module is the first rbb module on the current page, etc.
 *
 * Do only run once ModuleIndex::generateModuleIndex() per module instance;
 */
class ArrayAttributeBag extends AttributeBag implements \ArrayAccess
{
    public const KEY = '_markocupic_import_from_csv_attributes';
    public const NAME = 'markocupic_contao_csv_table_merger';

    public const KEY_MODEL = 'model';
    public const KEY_MAX_INSERTS_PER_REQUEST = 'max_inserts_per_request';
    public const KEY_INITIALIZED = 'initialized';
    public const KEY_RECORD_COUNT = 'record_count';
    public const KEY_REQUESTS_REQUIRED = 'requests_required';
    public const KEY_REQUESTS_COMPLETED = 'requests_completed';
    public const KEY_REQUESTS_REMAINED = 'requests_remained';
    public const KEY_MESSAGES = 'messages';
    public const KEY_IMPORT_PROCESS_COMPLETED = 'import_process_completed';
    public const KEY_IMPORT_PROCESS_STOPPED_WITH_ERROR = 'import_process_stopped_with_error';

    private ContaoFramework $framework;
    private RequestStack $requestStack;
    private ?SessionInterface $session;

    public function __construct(RequestStack $requestStack, string $storageKey = '')
    {
        $this->requestStack = $requestStack;
        parent::__construct(!empty($storageKey) ? $storageKey : self::KEY);
    }

    /**
     * @param mixed $key
     *
     * @throws \Exception
     */
    public function offsetExists($key): bool
    {
        return $this->has($key);
    }

    /**
     * @param mixed $key
     */
    public function &offsetGet($key): mixed
    {
        return $this->attributes[$key];
    }

    /**
     * @param mixed $key
     * @param mixed $value
     *
     * @throws \Exception
     */
    public function offsetSet($key, $value): void
    {
        $this->set($key, $value);
    }

    /**
     * @param mixed $key
     *
     * @throws \Exception
     */
    public function offsetUnset($key): void
    {
        $this->remove($key);
    }

    /**
     * @param $key
     *
     * @throws \Exception
     *
     * @return bool
     */
    public function has($key)
    {
        $sessKey = $this->getSessionBagSubkey();
        $arrSession = parent::get($sessKey, []);

        return isset($arrSession[$key]) ? true : false;
    }

    /**
     * @param $key
     * @param null $mixed
     *
     * @throws \Exception
     *
     * @return mixed|null
     */
    public function get($key, $mixed = null)
    {
        $sessKey = $this->getSessionBagSubkey();
        $arrSession = parent::get($sessKey, []);

        return $arrSession[$key] ?? null;
    }

    /**
     * @param $key
     * @param $value
     *
     * @throws \Exception
     */
    public function set($key, $value)
    {
        $sessKey = $this->getSessionBagSubkey();
        $arrSession = parent::get($sessKey, []);
        $arrSession[$key] = $value;

        return parent::set($sessKey, $arrSession);
    }

    /**
     * @throws \Exception
     */
    public function replace(array $arrAttributes): void
    {
        $sessKey = $this->getSessionBagSubkey();
        $arrSession = parent::get($sessKey, []);
        $arrNew = array_merge($arrSession, $arrAttributes);
        parent::set($sessKey, $arrNew);
    }

    /**
     * @param $key
     *
     * @throws \Exception
     *
     * @return mixed|void|null
     */
    public function remove($key)
    {
        $sessKey = $this->getSessionBagSubkey();
        $arrSession = parent::get($sessKey, []);

        if (isset($arrSession[$key])) {
            unset($arrSession[$key]);
            parent::set($sessKey, $arrSession);
        }
    }

    /**
     * @throws \Exception
     *
     * @return array|mixed|void
     */
    public function clear()
    {
        $sessKey = $this->getSessionBagSubkey();
        $arrSessionAll = parent::all();

        if (isset($arrSessionAll[$sessKey])) {
            unset($arrSessionAll[$sessKey]);

            foreach ($arrSessionAll as $k => $v) {
                parent::set($k, $v);
            }
        }
    }

    /**
     * @throws \Exception
     *
     * @return int
     */
    public function count()
    {
        $sessKey = $this->getSessionBagSubkey();
        $arrSessionAll = parent::all();

        if (isset($arrSessionAll[$sessKey]) && \is_array($arrSessionAll)) {
            return \count($arrSessionAll[$sessKey]);
        }

        return 0;
    }

    private function getSessionBagSubkey(): string
    {
        return $this->requestStack->getCurrentRequest()->get('session_key', 'xxx');
    }
}
