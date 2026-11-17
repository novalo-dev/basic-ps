<?php
/**
 * CI check: config/services.yml and config/admin/services.yml load and compile,
 * and every route in config/routes.yml points at a class and method that exist.
 *
 * PrestaShop compiles every active module's services into the one container of
 * the back office: a service that cannot be built there breaks the whole back
 * office, not just the module. Run once on the Symfony line of PrestaShop 8 (4.4)
 * and once on that of PrestaShop 9 (6.4).
 *
 * Usage: php compile-container.php <composer vendor dir> <module root>
 */
declare(strict_types=1);

error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

[, $vendor, $module] = $argv + [null, null, null];
$module = $module === null ? null : (string) realpath($module);
if ($vendor === null || $module === null) {
    fwrite(STDERR, "usage: php compile-container.php <vendor dir> <module root>\n");
    exit(2);
}

define('_PS_VERSION_', '0.0.0');

require rtrim($vendor, '/') . '/autoload.php';
require rtrim($module, '/') . '/vendor/autoload.php';

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader as ServicesLoader;
use Symfony\Component\Routing\Loader\YamlFileLoader as RoutesLoader;

// The module's services reference these core classes and services by name only;
// stand-ins let the container compile without a whole PrestaShop.
if (!class_exists('Module', false)) {
    eval('class Module { public static function getInstanceByName($name) { return new ps_edition_basic(); } }');
}
if (!class_exists('ps_edition_basic', false)) {
    eval('class ps_edition_basic extends Module {}');
}
// The parent of the module controllers, which are services so that PrestaShop 9
// hands them its container; autowiring reads it, so it has to exist.
if (!class_exists('PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController')) {
    eval('namespace PrestaShopBundle\Controller\Admin; abstract class FrameworkBundleAdminController {}');
}

$symfony = class_exists(Composer\InstalledVersions::class)
    ? (string) Composer\InstalledVersions::getPrettyVersion('symfony/dependency-injection')
    : 'unknown';
$failed = false;

try {
    $container = new ContainerBuilder();
    (new ServicesLoader($container, new FileLocator($module . '/config/admin')))->load('services.yml');
    $container->compile();
    $count = 0;
    foreach ($container->getServiceIds() as $id) {
        if (strpos($id, 'PrestaShop\\Module\\PsEditionBasic\\') !== 0 && strpos($id, 'ps_edition_basic.') !== 0) {
            continue;
        }
        $container->get($id);
        ++$count;
    }
    printf("ok   Symfony %s: %d service(s) built\n", $symfony, $count);
} catch (Throwable $e) {
    printf("FAIL Symfony %s: %s: %s\n", $symfony, get_class($e), $e->getMessage());
    $failed = true;
}

try {
    $routes = (new RoutesLoader(new FileLocator($module . '/config')))->load('routes.yml');
    foreach ($routes->all() as $name => $route) {
        $controller = (string) $route->getDefault('_controller');
        if ($controller === '') {
            printf("ok   route %s (no controller)\n", $name);
            continue;
        }
        [$class, $method] = explode('::', $controller, 2) + [1 => '__invoke'];
        // Read from the source rather than loaded: the controllers extend PrestaShop
        // classes this script does not have. Parents inside the module are followed.
        $found = false;
        $current = $class;
        for ($depth = 0; $depth < 5 && !$found && $current !== ''; ++$depth) {
            $file = controllerFile($module, $current);
            if ($file === null) {
                break;
            }
            $source = (string) file_get_contents($file);
            $found = (bool) preg_match('/function\s+' . preg_quote($method, '/') . '\s*\(/', $source);
            $current = preg_match('/class\s+\w+\s+extends\s+(\w+)/', $source, $m)
                ? substr($current, 0, (int) strrpos($current, '\\') + 1) . $m[1]
                : '';
        }
        if (!$found) {
            printf("FAIL route %s: %s does not exist\n", $name, $controller);
            $failed = true;
            continue;
        }
        printf("ok   route %s -> %s\n", $name, $controller);
    }
} catch (Throwable $e) {
    printf("FAIL routes.yml: %s: %s\n", get_class($e), $e->getMessage());
    $failed = true;
}

exit($failed ? 1 : 0);

function controllerFile(string $module, string $class): ?string
{
    $prefix = 'PrestaShop\\Module\\PsEditionBasic\\';
    if (strpos($class, $prefix) !== 0) {
        return null;
    }
    $file = $module . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';

    return is_file($file) ? $file : null;
}
