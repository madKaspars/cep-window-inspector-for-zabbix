<?php declare(strict_types = 0);
/*
 * SPDX-License-Identifier: AGPL-3.0-only
 * Original project author: Kaspars Mednis (madKaspars)
 * AI-generated implementation; see NOTICE.md for provenance.
 */
namespace Modules\CepWindowInspector\Actions;

require_once dirname(__DIR__).'/includes/Repository.php';
require_once dirname(__DIR__).'/includes/Timing.php';
require_once dirname(__DIR__).'/includes/Changes.php';
use Modules\CepWindowInspector\Includes\ChangeRepository;
use Modules\CepWindowInspector\Includes\Repository;
use Modules\CepWindowInspector\Includes\Timing;

class Inspect extends \CController {
    protected function init(): void {
        // GET-only, read-only page. No state-changing operation is exposed.
        $this->disableCsrfValidation();
    }

    protected function checkInput(): bool {
        $fields = array_fill_keys(['windowid', 'ruleid', 'before', 'after_index', 'after_eventid'], 'string');
        $fields['details'] = 'in 1';
        $fields['view'] = 'in timeline,table';
        if (!$this->validateInput($fields)) {
            $this->setResponse(new \CControllerResponseFatal());
            return false;
        }
        if (!in_array($this->getInput('view', 'table'), ['timeline', 'table'], true)) {
            $this->setResponse(new \CControllerResponseFatal());
            return false;
        }
        foreach (['windowid', 'ruleid', 'before', 'after_eventid'] as $field) {
            if (!Repository::validId($this->getInput($field, ''))) {
                \error(_('Invalid numeric identifier.'));
                $this->setResponse(new \CControllerResponseFatal());
                return false;
            }
        }
        $index = $this->getInput('after_index', '');
        $eventid = $this->getInput('after_eventid', '');
        if (($index !== '0' && !Repository::validId($index)) || (($index === '') !== ($eventid === ''))) {
            \error(_('Invalid member page cursor.'));
            $this->setResponse(new \CControllerResponseFatal());
            return false;
        }
        return true;
    }

    protected function checkPermissions(): bool {
        // Direct SQL cannot apply normal API host permission filters.
        return \CWebUser::getType() == USER_TYPE_SUPER_ADMIN;
    }

    protected function doAction(): void {
        $data = ['windowid' => $this->getInput('windowid', ''), 'ruleid' => $this->getInput('ruleid', ''),
            'before' => $this->getInput('before', ''),
            'after_index' => $this->getInput('after_index', ''),
            'after_eventid' => $this->getInput('after_eventid', ''),
            'details' => $this->hasInput('details'),
            'view' => $this->getInput('view', 'table'),
            'window' => null, 'windows' => [], 'members' => [], 'counts' => null, 'more' => false,
            'changes' => [], 'changes_error' => '', 'error' => '', 'timing_error' => '', 'relationships' => null, 'sampled_at' => time()];
        try {
            if (!class_exists('CCepRuleHelper')) {
                throw new \RuntimeException('This module requires a compatible Zabbix CEP frontend and database schema.');
            }
            if ($data['windowid'] === '') {
                $result = Repository::windows($data['ruleid'], $this->getInput('before', ''));
                $data['windows'] = $result['rows'];
            }
            else {
                $data['window'] = Repository::window($data['windowid']);
                $result = ['rows' => [], 'more' => false];
                if ($data['window'] !== null) {
                    $result = Repository::members($data['windowid'], $this->getInput('after_index', ''),
                        $this->getInput('after_eventid', ''), (int) $data['window']['window_type'] === 2);
                    $data['members'] = $result['rows'];
                    $data['counts'] = Repository::counts($data['windowid']);
                }
            }
            $data['more'] = $result['more'];
        }
        catch (\RuntimeException $e) {
            $data['error'] = $e->getMessage();
        }
        if ($data['error'] === '') {
            $windows = $data['window'] !== null ? [$data['window']] : $data['windows'];
            $summaries = [];
            try {
                $summaries = Repository::expirySummaries(array_column($windows, 'cep_windowid'));
            }
            catch (\RuntimeException $e) {
                // Expiry is supplementary: keep the working member list available if this read fails.
                $data['timing_error'] = _('Window expiry estimates are temporarily unavailable.');
            }
            foreach ($data['windows'] as &$window) {
                $window['expiry'] = Timing::forWindow($window, $summaries[$window['cep_windowid']] ?? null);
            }
            unset($window);
            if ($data['window'] !== null) {
                if ((int) $data['window']['window_type'] === 2) {
                    try {
                        $data['relationships'] = Repository::relationships($data['windowid'], $data['members']);
                    }
                    catch (\RuntimeException $e) {
                        // Role links from the member read survive an unavailable supplementary summary.
                        $data['relationships'] = null;
                    }
                }
                try {
                    $data['changes'] = ChangeRepository::summaries($data['members'], $data['sampled_at'],
                        (string) $data['window']['cep_ruleid']);
                }
                catch (\RuntimeException $e) {
                    $data['changes_error'] = _('Change history is temporarily unavailable.');
                }
                $data['window']['expiry'] = Timing::forWindow($data['window'],
                    $summaries[$data['window']['cep_windowid']] ?? null);
                foreach ($data['members'] as &$member) {
                    $member['expiry'] = Timing::forEvent($data['window'], $member);
                }
                unset($member);
            }
        }
        $response = new \CControllerResponseData($data);
        $response->setTitle($data['windowid'] === '' ? _('CEP windows') : _('CEP window details'));
        $this->setResponse($response);
    }
}
