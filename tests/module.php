<?php
/*
 * SPDX-License-Identifier: AGPL-3.0-only
 * Original project author: Kaspars Mednis (madKaspars)
 * AI-generated implementation; see NOTICE.md for provenance.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
error_reporting(E_ALL);
set_error_handler(function($level, $message, $file, $line) {
    throw new ErrorException($message, 0, $level, $file, $line);
});
$ui = realpath($argv[1] ?? getenv('ZABBIX_UI_ROOT') ?: '');
if (!$ui || !is_file($ui.'/include/defines.inc.php')) {
    throw new RuntimeException('Pass the Zabbix ui source directory as the first argument.');
}
require $ui.'/include/defines.inc.php';
if (!function_exists('_')) { function _($text) { return $text; } }
// A non-super-admin session tests module loading without a live frontend menu service.
class CWebUser { static function getType() { return USER_TYPE_ZABBIX_USER; } }
$source = dirname(__DIR__);
$root = sys_get_temp_dir().'/cep-window-inspector-'.bin2hex(random_bytes(6));
$installed = $root.'/modules/cep-window-inspector';
$classes = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ui.'/include/classes')) as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $classes[$file->getBasename('.php')] = $file->getPathname();
    }
}
spl_autoload_register(function($class) use ($classes, $installed) {
    $prefix = 'Modules\\CepWindowInspector\\';
    if (str_starts_with($class, $prefix)) {
        $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
        foreach (['Actions' => 'actions', 'Includes' => 'includes'] as $from => $to) {
            if (str_starts_with($relative, $from.'/')) {
                $relative = $to.substr($relative, strlen($from));
            }
        }
        require_once $installed.'/'.$relative.'.php';
    }
    else {
        $short = basename(str_replace('\\', '/', $class));
        if (isset($classes[$short])) { require_once $classes[$short]; }
    }
});
try {
    mkdir($installed, 0700, true);
    foreach (['Module.php', 'manifest.json', 'actions', 'includes', 'views', 'assets'] as $entry) {
        $paths = is_file($source.'/'.$entry) ? [$source.'/'.$entry]
            : new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source.'/'.$entry,
                FilesystemIterator::SKIP_DOTS));
        foreach ($paths as $file) {
            $path = (string) $file;
            $target = $installed.substr($path, strlen($source));
            if (!is_dir(dirname($target))) { mkdir(dirname($target), 0700, true); }
            copy($path, $target);
        }
    }
    $manager = new CModuleManager($root);
    $manifest = $manager->addModule('modules/cep-window-inspector', '1', 'cep-window-inspector');
    if (!$manifest || $manifest['namespace'] !== 'Modules\\CepWindowInspector') {
        throw new RuntimeException('Native Zabbix rejected the Inspector manifest or namespace.');
    }
    $manager->initModules();
    $module = $manager->getModules()['cep-window-inspector'] ?? null;
    if (!$module instanceof Modules\CepWindowInspector\Module || $module->getName() !== 'CEP Window Inspector') {
        throw new RuntimeException('Native Zabbix did not load the Inspector module class.');
    }
    $actions = $manager->getActions();
    foreach (['cep.window.inspect', 'cep.window.changes'] as $action) {
        if (!isset($actions[$action]) || !is_subclass_of($actions[$action]['class'], CController::class)) {
            throw new RuntimeException('Native Zabbix could not resolve action '.$action);
        }
    }
    echo 'PASS: Zabbix '.ZABBIX_VERSION.' loads the fresh cep-window-inspector installation, manifest, namespace and both controller classes.'.PHP_EOL;
}
finally {
    if (is_dir($root)) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,
                FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $file) {
            if ($file->isDir()) { rmdir($file->getPathname()); }
            else { unlink($file->getPathname()); }
        }
        rmdir($root);
    }
}
