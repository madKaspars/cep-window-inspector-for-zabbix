<?php declare(strict_types = 0);
/*
 * SPDX-License-Identifier: AGPL-3.0-only
 * Original project author: Kaspars Mednis (madKaspars)
 * AI-generated implementation; see NOTICE.md for provenance.
 */
namespace Modules\CepWindowInspector;

use Zabbix\Core\CModule;

class Module extends CModule {
    public function init(): void {
        if (\CWebUser::getType() != USER_TYPE_SUPER_ADMIN) {
            return;
        }
        $monitoring = \APP::Component()->get('menu.main')->find('Monitoring');
        if ($monitoring !== null) {
            $monitoring->getSubMenu()->add(
                (new \CMenuItem(_('CEP windows')))->setAction('cep.window.inspect')
            );
        }
    }
}
