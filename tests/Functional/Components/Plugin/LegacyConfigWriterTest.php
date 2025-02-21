<?php
/**
 * Shopware 5
 * Copyright (c) shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

namespace Shopware\Tests\Functional\Components\Plugin;

use DateTime;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Components\Model\ModelManager;
use Shopware\Components\Plugin\Configuration\ReaderInterface;
use Shopware\Components\Plugin\Configuration\WriterInterface;
use Shopware\Components\Plugin\ConfigWriter;
use Shopware\Components\Plugin\DBALConfigReader;
use Shopware\Models\Plugin\Plugin;
use Shopware\Models\Shop\Shop;
use Shopware\Tests\Functional\Traits\DatabaseTransactionBehaviour;

class LegacyConfigWriterTest extends TestCase
{
    use DatabaseTransactionBehaviour;

    private const PLUGIN_NAME = 'swConfigWriterPluginTest';

    private const NUMBER_CONFIGURATION_NAME = 'numberConfiguration';

    private const ELEMENT_DEFAULT_VALUE = 1;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var ModelManager
     */
    private $modelManager;

    /**
     * @var Plugin
     */
    private $plugin;

    /**
     * @var Shop
     */
    private $installationShop;

    /**
     * @var Shop
     */
    private $subShop;

    /**
     * @var Shop
     */
    private $languageShop;

    /**
     * @var ConfigWriter
     */
    private $configWriter;

    /**
     * @var DBALConfigReader
     */
    private $configReader;

    public function setUp(): void
    {
        $this->connection = Shopware()->Container()->get('dbal_connection');
        $this->modelManager = Shopware()->Container()->get('models');

        // setup plugin
        $this->connection->insert('s_core_plugins', [
            'namespace' => 'Core',
            'name' => self::PLUGIN_NAME,
            'label' => 'This is a config reader test plugin',
            'source' => 'php unit',
            'active' => 0,
            'added' => new DateTime(),
            'version' => '1.0.0',
            'capability_update' => 0,
            'capability_install' => 0,
            'capability_enable' => 1,
            'capability_secure_uninstall' => 1,
        ], [
            'added' => 'datetime',
        ]);
        $this->plugin = $this->modelManager->find(Plugin::class, $this->connection->lastInsertId());

        // setup plugin configuration
        $parentFormId = $this->connection
            ->executeQuery('SELECT id FROM s_core_config_forms WHERE `name` = ?', ['Core'])
            ->fetchColumn();

        $this->connection->insert('s_core_config_forms', [
            'name' => self::PLUGIN_NAME,
            'label' => 'This is a config reader test plugin',
            'position' => 0,
            'plugin_id' => $this->plugin->getId(),
            'parent_id' => $parentFormId,
        ]);
        $formId = $this->connection->lastInsertId();

        $this->connection->insert('s_core_config_elements', [
            'form_id' => $formId,
            'name' => self::NUMBER_CONFIGURATION_NAME,
            'value' => serialize(self::ELEMENT_DEFAULT_VALUE),
            'type' => 'number',
            'required' => 0,
            'position' => 0,
            'scope' => 1,
        ]);

        // setup shops
        // assume shop by id 1 exists
        $this->installationShop = $this->modelManager->find(Shop::class, 1);

        $this->connection->insert('s_core_shops', [
            'name' => 'Sub Shop',
            'position' => 0,
            'hosts' => '',
            'secure' => 1,
            'customer_scope' => 0,
            '`default`' => 0,
            'active' => 1,
        ]);
        $this->subShop = $this->modelManager->find(Shop::class, $this->connection->lastInsertId());

        $this->connection->insert('s_core_shops', [
            'name' => 'Sub Shop',
            'position' => 0,
            'hosts' => '',
            'secure' => 1,
            'customer_scope' => 0,
            '`default`' => 0,
            'active' => 1,
            'main_id' => $this->subShop->getId(),
        ]);
        $this->languageShop = $this->modelManager->find(Shop::class, $this->connection->lastInsertId());

        $this->configWriter = new ConfigWriter(Shopware()->Container()->get(WriterInterface::class));
        $this->configReader = new DBALConfigReader(Shopware()->Container()->get(ReaderInterface::class));
    }

    public function testWriteValueForInstallation()
    {
        $this->configWriter->saveConfigElement($this->plugin, self::NUMBER_CONFIGURATION_NAME, 2, $this->installationShop);

        static::assertSame(
            $this->configReader->getByPluginName(self::PLUGIN_NAME),
            [self::NUMBER_CONFIGURATION_NAME => 2]
        );

        static::assertSame(
            $this->configReader->getByPluginName(self::PLUGIN_NAME, $this->installationShop),
            [self::NUMBER_CONFIGURATION_NAME => 2]
        );
    }

    public function testWriteValueForSubShop()
    {
        $this->configWriter->saveConfigElement($this->plugin, self::NUMBER_CONFIGURATION_NAME, 2, $this->subShop);

        static::assertSame(
            $this->configReader->getByPluginName(self::PLUGIN_NAME),
            [self::NUMBER_CONFIGURATION_NAME => self::ELEMENT_DEFAULT_VALUE]
        );

        static::assertSame(
            $this->configReader->getByPluginName(self::PLUGIN_NAME, $this->installationShop),
            [self::NUMBER_CONFIGURATION_NAME => self::ELEMENT_DEFAULT_VALUE]
        );

        static::assertSame(
            $this->configReader->getByPluginName(self::PLUGIN_NAME, $this->subShop),
            [self::NUMBER_CONFIGURATION_NAME => 2]
        );
    }

    public function testWriteValueForLanguageShop()
    {
        $this->configWriter->saveConfigElement($this->plugin, self::NUMBER_CONFIGURATION_NAME, 2, $this->languageShop);

        static::assertSame(
            $this->configReader->getByPluginName(self::PLUGIN_NAME),
            [self::NUMBER_CONFIGURATION_NAME => self::ELEMENT_DEFAULT_VALUE]
        );

        static::assertSame(
            $this->configReader->getByPluginName(self::PLUGIN_NAME, $this->installationShop),
            [self::NUMBER_CONFIGURATION_NAME => self::ELEMENT_DEFAULT_VALUE]
        );

        static::assertSame(
            $this->configReader->getByPluginName(self::PLUGIN_NAME, $this->subShop),
            [self::NUMBER_CONFIGURATION_NAME => self::ELEMENT_DEFAULT_VALUE]
        );

        static::assertSame(
            $this->configReader->getByPluginName(self::PLUGIN_NAME, $this->languageShop),
            [self::NUMBER_CONFIGURATION_NAME => 2]
        );
    }

    public function testWriteDefaultValueForSubShop()
    {
        $this->configWriter->saveConfigElement($this->plugin, self::NUMBER_CONFIGURATION_NAME, 2, $this->installationShop);
        $this->configWriter->saveConfigElement($this->plugin, self::NUMBER_CONFIGURATION_NAME, self::ELEMENT_DEFAULT_VALUE, $this->subShop);

        static::assertSame(
            $this->configReader->getByPluginName(self::PLUGIN_NAME),
            [self::NUMBER_CONFIGURATION_NAME => 2]
        );

        static::assertSame(
            $this->configReader->getByPluginName(self::PLUGIN_NAME, $this->installationShop),
            [self::NUMBER_CONFIGURATION_NAME => 2]
        );

        static::assertSame(
            $this->configReader->getByPluginName(self::PLUGIN_NAME, $this->subShop),
            [self::NUMBER_CONFIGURATION_NAME => self::ELEMENT_DEFAULT_VALUE]
        );

        static::assertSame(
            $this->configReader->getByPluginName(self::PLUGIN_NAME, $this->languageShop),
            [self::NUMBER_CONFIGURATION_NAME => self::ELEMENT_DEFAULT_VALUE]
        );
    }

    public function testWriteDefaultValueForLanguageShop()
    {
        $this->configWriter->saveConfigElement($this->plugin, self::NUMBER_CONFIGURATION_NAME, 2, $this->subShop);
        $this->configWriter->saveConfigElement($this->plugin, self::NUMBER_CONFIGURATION_NAME, self::ELEMENT_DEFAULT_VALUE, $this->languageShop);

        static::assertSame(
            $this->configReader->getByPluginName(self::PLUGIN_NAME),
            [self::NUMBER_CONFIGURATION_NAME => self::ELEMENT_DEFAULT_VALUE]
        );

        static::assertSame(
            $this->configReader->getByPluginName(self::PLUGIN_NAME, $this->installationShop),
            [self::NUMBER_CONFIGURATION_NAME => self::ELEMENT_DEFAULT_VALUE]
        );

        static::assertSame(
            $this->configReader->getByPluginName(self::PLUGIN_NAME, $this->subShop),
            [self::NUMBER_CONFIGURATION_NAME => 2]
        );

        static::assertSame(
            $this->configReader->getByPluginName(self::PLUGIN_NAME, $this->languageShop),
            [self::NUMBER_CONFIGURATION_NAME => self::ELEMENT_DEFAULT_VALUE]
        );
    }
}
