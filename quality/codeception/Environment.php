<?php
declare(strict_types=1);

/*
 * This file is (c) 2026 by Cyberhouse GmbH
 *
 * It is free software; you can redistribute it and/or
 * modify it under the terms of the GPLv3 license
 *
 * For the full copyright and license information see
 * <https://www.gnu.org/licenses/gpl-3.0.html>
 */

// #ddev-generated

use Codeception\Configuration;
use Composer\InstalledVersions;
use TYPO3\TestingFramework\Core\Acceptance\Extension\BackendEnvironment;
use TYPO3\TestingFramework\Core\Testbase;

/**
 * Sets up the test instance once per suite, on sqlite and by convention:
 * - every extension the project installs, the same set production runs
 * - the records of every Acceptance/Fixtures/*.csv of the tests
 * - Acceptance/Fixtures/sites as site configuration, each entry of Acceptance/Fixtures/fileadmin in its fileadmin
 * - the production configuration (config/system/additional.php), the frontend (BUILD_FRONTEND) and the
 *   directories the project links into public/ (BUILD_LINKS of ddev-typo3base, e.g. the built frontend)
 * Further links and fixtures can be added in codeception.yml.
 */
final class Environment extends BackendEnvironment
{
    private string $root;

    private string $instance;

    public function _initialize(): void
    {
        $this->root = (string)realpath(dirname(__DIR__, 3));
        $this->instance = rtrim((string)realpath((new Testbase())->getWebRoot()), '/') . '/typo3temp/var/tests/acceptance';
        $fixtures = Configuration::testsDir() . 'Acceptance/Fixtures';

        $links = [];
        if (is_dir($fixtures . '/sites')) {
            $links[$fixtures . '/sites'] = 'typo3conf/sites';
        }
        // its entries only, the processed files are written to the fileadmin of the instance
        foreach (glob($fixtures . '/fileadmin/*') ?: [] as $path) {
            $links[$path] = 'fileadmin/' . basename($path);
        }
        // the project path of the instance is the instance itself
        if (is_file($this->root . '/config/system/additional.php')) {
            $links[$this->root . '/config/system/additional.php'] = 'typo3conf/system/additional.php';
        }
        $frontend = (string)getenv('BUILD_FRONTEND');
        if ($frontend !== '') {
            $links[$this->root . '/' . $frontend] = $frontend;
        }
        // directories only: the setup of the instance writes e.g. its own .htaccess, through a link into the
        // file of the project
        foreach (array_filter(explode(' ', (string)getenv('BUILD_LINKS'))) as $pattern) {
            foreach (glob($this->root . '/' . $pattern, GLOB_BRACE | GLOB_ONLYDIR) ?: [] as $path) {
                $links[$path] = basename($path);
            }
        }

        $this->localConfig = [
            'typo3DatabaseDriver' => 'pdo_sqlite',
            'coreExtensionsToLoad' => InstalledVersions::getInstalledPackagesByType('typo3-cms-framework'),
            'testExtensionsToLoad' => InstalledVersions::getInstalledPackagesByType('typo3-cms-extension'),
            // created before the links, the instance's settings.php is only written afterwards
            'additionalFoldersToCreate' => array_merge(['/typo3conf/system'], $this->config['additionalFoldersToCreate']),
            'pathsToLinkInTestInstance' => array_replace(
                array_combine(array_map($this->fromInstance(...), array_keys($links)), $links),
                $this->config['pathsToLinkInTestInstance']
            ),
            'csvDatabaseFixtures' => array_merge(glob($fixtures . '/*.csv') ?: [], $this->config['csvDatabaseFixtures']),
        ];
        parent::_initialize();
    }

    /**
     * The testing framework resolves the paths to link relative to the instance
     */
    private function fromInstance(string $path): string
    {
        return str_repeat('../', substr_count(substr($this->instance, strlen($this->root)), '/'))
            . substr((string)realpath($path), strlen($this->root) + 1);
    }
}
