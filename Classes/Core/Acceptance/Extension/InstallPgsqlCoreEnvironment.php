<?php
declare(strict_types=1);
namespace TYPO3\TestingFramework\Core\Acceptance\Extension;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use Codeception\Event\SuiteEvent;
use Codeception\Event\TestEvent;
use Codeception\Events;
use Codeception\Extension;
use Doctrine\DBAL\DriverManager;
use TYPO3\TestingFramework\Core\Testbase;

/**
 * This codeception extension creates a basic TYPO3 instance within
 * typo3temp. It is used as a basic acceptance test that clicks through
 * the TYPO3 installation steps.
 */
class InstallPgsqlCoreEnvironment extends Extension
{
    protected $config = [
        'path' => null,
        'typo3DatabaseHost' => '127.0.0.1',
        'typo3DatabasePassword' => null,
        'typo3DatabaseUsername' => null,
        'typo3DatabaseName' => null,
    ];

    public function _initialize()
    {
        $this->config['typo3DatabasePassword'] = $this->config['typo3DatabasePassword'] ?? getenv( 'typo3DatabasePassword');
        $this->config['typo3DatabaseUsername'] = $this->config['typo3DatabaseUsername'] ?? getenv( 'typo3DatabaseUsername');
        $this->config['typo3DatabasePort'] = $this->config['typo3DatabasePort'] ?? getenv('typo3DatabasePort');
        $this->config['typo3DatabaseName'] = $this->config['typo3DatabaseName'] ?? getenv( 'typo3DatabaseName');
    }


    /**
     * Events to listen to
     */
    public static $events = [
        Events::TEST_BEFORE => 'bootstrapTypo3Environment',
    ];

    /**
     * Handle SUITE_BEFORE event.
     *
     * Create a full standalone TYPO3 instance within typo3temp/var/tests/acceptance,
     * create a database and create database schema.
     */
    public function bootstrapTypo3Environment(TestEvent $event)
    {
        $testbase = new Testbase();
        $testbase->enableDisplayErrors();
        $testbase->defineBaseConstants();
        $testbase->defineOriginalRootPath();
        $testbase->setTypo3TestingContext();

        $instancePath = ORIGINAL_ROOT . $this->config['path'];
        $testbase->removeOldInstanceIfExists($instancePath);
        putenv('TYPO3_PATH_ROOT=' . $instancePath);

        // Drop db from a previous run if exists
        $connectionParameters = [
            'driver' => 'pdo_pgsql',
            'host' => $this->config['typo3DatabaseHost'],
            'password' => $this->config['typo3DatabasePassword'],
            'user' => $this->config['typo3DatabaseUsername'],
        ];
        $port = $this->config['typo3DatabasePort'];
        if (!empty($port)) {
            $connectionParameters['port'] = $port;
        }
        $this->output->debug("Connecting to PgSQL: " . json_encode($connectionParameters));
        $schemaManager = DriverManager::getConnection($connectionParameters)->getSchemaManager();
        $databaseName = mb_strtolower(trim($this->config['typo3DatabaseName'])) . '_atipgsql';

        $this->output->debug("Database: $databaseName");
        if (in_array($databaseName, $schemaManager->listDatabases(), true)) {
            $this->output->debug("Dropping database $databaseName");
            $schemaManager->dropDatabase($databaseName);
        }
        $schemaManager->createDatabase($databaseName);

        $testbase->createDirectory($instancePath);
        $testbase->setUpInstanceCoreLinks($instancePath);
        touch($instancePath . '/FIRST_INSTALL');

        $event->getTest()->getMetadata()->setCurrent($this->config);
    }
}
