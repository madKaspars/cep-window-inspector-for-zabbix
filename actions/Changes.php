<?php declare(strict_types = 0);
/*
 * SPDX-License-Identifier: AGPL-3.0-only
 * Original project author: Kaspars Mednis (madKaspars)
 * AI-generated implementation; see NOTICE.md for provenance.
 */
namespace Modules\CepWindowInspector\Actions;
require_once dirname(__DIR__).'/includes/Changes.php';
use Modules\CepWindowInspector\Includes\Repository;
use Modules\CepWindowInspector\Includes\ChangeRepository;
use Modules\CepWindowInspector\Includes\Changes as Presenter;

class Changes extends \CController {
    protected function init(): void {
        // Read-only hintbox POST; no settings, event state or user profile writes.
        $this->disableCsrfValidation();
    }

    private function json(array $value): void {
        $this->setResponse((new \CControllerResponseData(['main_block' => json_encode($value,
            JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR)]))->disableView());
    }

    protected function checkInput(): bool {
        $valid = $this->validateInput(array_fill_keys(['windowid', 'eventid', 'snapshot', 'direction', 'anchor', 'focus'], 'string'));
        if ($valid) {
            foreach (['windowid', 'eventid', 'snapshot'] as $field) {
                $valid = $valid && Repository::validId($this->getInput($field, ''), false);
            }
            foreach (['anchor', 'focus'] as $field) {
                $valid = $valid && Repository::validId($this->getInput($field, ''));
            }
            $direction = $this->getInput('direction', 'first');
            $valid = $valid && (int) $this->getInput('snapshot', '0') <= time()
                && in_array($direction, ['first', 'next', 'previous', 'focus'], true)
                && (($direction === 'first') === ($this->getInput('anchor', '') === ''));
        }
        if (!$valid) $this->json(['error' => ['messages' => [_('Invalid change history request.')]]]);
        return $valid;
    }

    protected function checkPermissions(): bool {
        return \CWebUser::getType() == USER_TYPE_SUPER_ADMIN;
    }

    protected function doAction(): void {
        $request = [];
        foreach (['windowid', 'eventid', 'snapshot', 'direction', 'anchor', 'focus'] as $field) {
            $request[$field] = $this->getInput($field, $field === 'direction' ? 'first' : '');
        }
        try {
            $history = ChangeRepository::history($request['windowid'], $request['eventid'], (int) $request['snapshot'],
                $request['direction'], $request['anchor']);
            $this->json(['data' => Presenter::popup($history, $request)]);
        }
        catch (\RuntimeException | \InvalidArgumentException $e) {
            $this->json(['error' => ['messages' => [$e->getMessage()]]]);
        }
    }
}
