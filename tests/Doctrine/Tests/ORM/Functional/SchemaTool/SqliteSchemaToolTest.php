<?php

declare(strict_types=1);

namespace Doctrine\Tests\ORM\Functional\SchemaTool;

use Doctrine\DBAL\Configuration;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Tests\OrmFunctionalTestCase;
use function array_filter;
use function current;
use function method_exists;
use function sprintf;
use function strpos;

class SqliteSchemaToolTest extends OrmFunctionalTestCase
{
    protected function setUp()
    {
        parent::setUp();

        if ($this->_em->getConnection()->getDatabasePlatform()->getName() !== 'sqlite') {
            $this->markTestSkipped('The ' . self::class . ' requires the use of sqlite.');
        }
    }

    protected function tearDown()
    {
        $this->_em->getConnection()->exec('DROP TABLE IF EXISTS my_entity');
        $this->_em->getConnection()->exec('DROP TABLE IF EXISTS my_other_entity');

        parent::tearDown();
    }

    private function filterCreateTable(array $sqls, string $tableName) : array
    {
        return array_filter($sqls, static function (string $sql) use ($tableName) : bool {
            return strpos($sql, sprintf('CREATE TABLE %s (', $tableName)) === 0;
        });
    }

    public function testUpdateSchemaSql()
    {
        $classes = [
            $this->_em->getClassMetadata(MyEntity::class),
        ];
        $tool    = new SchemaTool($this->_em);
        $sqls    = $tool->getUpdateSchemaSql($classes);
        $sqls    = $this->filterCreateTable($sqls, 'my_entity');
        $this->assertCount(1, $sqls);

        $this->_em->getConnection()->exec(current($sqls));
        $sqls = $tool->getUpdateSchemaSql($classes);
        $sqls = array_filter($sqls, static function (string $sql) : bool {
            return (bool) strpos($sql, 'my_entity');
        });
        $this->assertCount(0, $sqls);

        $classes[] = $this->_em->getClassMetadata(MyOtherEntity::class);
        $sqls      = $tool->getUpdateSchemaSql($classes);
        $this->assertCount(0, $this->filterCreateTable($sqls, 'my_entity'));
        $this->assertCount(1, $this->filterCreateTable($sqls, 'my_other_entity'));
    }

    public function provideUpdateSchemaSqlWithSchemaAssetFilter() : array
    {
        return [
            ['/^(?!my_enti)/', null],
            [
                null,
                static function ($assetName) : bool {
                    return $assetName !== 'my_entity';
                },
            ],
        ];
    }

    /**
     * @dataProvider provideUpdateSchemaSqlWithSchemaAssetFilter
     */
    public function testUpdateSchemaSqlWithSchemaAssetFilter(?string $filterRegex, ?callable $filterCallback)
    {
        if (! method_exists(Configuration::class, 'setSchemaAssetsFilter')) {
            $this->markTestSkipped(sprintf('Test require %s::setSchemaAssetsFilter method', Configuration::class));
        }

        if ($filterRegex && ! method_exists(Configuration::class, 'setFilterSchemaAssetsExpression')) {
            $this->markTestSkipped(sprintf('Test require %s::setFilterSchemaAssetsExpression method', Configuration::class));
        }

        $classes = [$this->_em->getClassMetadata(MyEntity::class)];

        $tool = new SchemaTool($this->_em);
        $tool->createSchema($classes);

        $config = $this->_em->getConnection()->getConfiguration();
        if ($filterRegex) {
            $config->setFilterSchemaAssetsExpression($filterRegex);
        } else {
            $config->setSchemaAssetsFilter($filterCallback);
        }

        $sqls = $tool->getUpdateSchemaSql($classes);
        $sqls = array_filter($sqls, static function (string $sql) : bool {
            return (bool) strpos($sql, 'my_entity');
        });
        $this->assertCount(0, $sqls);

        if ($filterRegex) {
            $this->assertEquals($filterRegex, $config->getFilterSchemaAssetsExpression());
        } else {
            $this->assertSame($filterCallback, $config->getSchemaAssetsFilter());
        }
    }
}

/**
 * @Entity
 * @Table(name="my_entity")
 */
class MyEntity
{
    /**
     * @Id @Column(type="integer")
     * @GeneratedValue(strategy="AUTO")
     */
    public $id;
}

/**
 * @Entity
 * @Table(name="my_other_entity")
 */
class MyOtherEntity
{
    /**
     * @Id @Column(type="integer")
     * @GeneratedValue(strategy="AUTO")
     */
    public $id;
}
