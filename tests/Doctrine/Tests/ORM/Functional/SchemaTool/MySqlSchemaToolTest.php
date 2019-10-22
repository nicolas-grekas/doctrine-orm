<?php

namespace Doctrine\Tests\ORM\Functional\SchemaTool;

use Doctrine\DBAL\Configuration;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Tests\OrmFunctionalTestCase;
use Doctrine\Tests\Models;
use function array_filter;
use function current;
use function method_exists;
use function sprintf;
use function strpos;

class MySqlSchemaToolTest extends OrmFunctionalTestCase
{
    protected function setUp()
    {
        parent::setUp();

        if ($this->_em->getConnection()->getDatabasePlatform()->getName() !== 'mysql') {
            $this->markTestSkipped('The ' . __CLASS__ .' requires the use of mysql.');
        }
    }

    protected function tearDown()
    {
        $this->_em->getConnection()->exec('DROP TABLE IF EXISTS entity_to_remove');
        $this->_em->getConnection()->exec('DROP TABLE IF EXISTS other_entity_to_remove');

        parent::tearDown();
    }

    public function testGetCreateSchemaSql()
    {
        $classes = [
            $this->_em->getClassMetadata(Models\CMS\CmsGroup::class),
            $this->_em->getClassMetadata(Models\CMS\CmsUser::class),
            $this->_em->getClassMetadata(Models\CMS\CmsTag::class),
            $this->_em->getClassMetadata(Models\CMS\CmsAddress::class),
            $this->_em->getClassMetadata(Models\CMS\CmsEmail::class),
            $this->_em->getClassMetadata(Models\CMS\CmsPhonenumber::class),
        ];

        $tool = new SchemaTool($this->_em);
        $sql = $tool->getCreateSchemaSql($classes);

        $this->assertEquals("CREATE TABLE cms_groups (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(50) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB", $sql[0]);
        $this->assertEquals("CREATE TABLE cms_users (id INT AUTO_INCREMENT NOT NULL, email_id INT DEFAULT NULL, status VARCHAR(50) DEFAULT NULL, username VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_3AF03EC5F85E0677 (username), UNIQUE INDEX UNIQ_3AF03EC5A832C1C9 (email_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB", $sql[1]);
        $this->assertEquals("CREATE TABLE cms_users_groups (user_id INT NOT NULL, group_id INT NOT NULL, INDEX IDX_7EA9409AA76ED395 (user_id), INDEX IDX_7EA9409AFE54D947 (group_id), PRIMARY KEY(user_id, group_id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB", $sql[2]);
        $this->assertEquals("CREATE TABLE cms_users_tags (user_id INT NOT NULL, tag_id INT NOT NULL, INDEX IDX_93F5A1ADA76ED395 (user_id), INDEX IDX_93F5A1ADBAD26311 (tag_id), PRIMARY KEY(user_id, tag_id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB", $sql[3]);
        $this->assertEquals("CREATE TABLE cms_tags (id INT AUTO_INCREMENT NOT NULL, tag_name VARCHAR(50) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB", $sql[4]);
        $this->assertEquals("CREATE TABLE cms_addresses (id INT AUTO_INCREMENT NOT NULL, user_id INT DEFAULT NULL, country VARCHAR(50) NOT NULL, zip VARCHAR(50) NOT NULL, city VARCHAR(50) NOT NULL, UNIQUE INDEX UNIQ_ACAC157BA76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB", $sql[5]);
        $this->assertEquals("CREATE TABLE cms_emails (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(250) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB", $sql[6]);
        $this->assertEquals("CREATE TABLE cms_phonenumbers (phonenumber VARCHAR(50) NOT NULL, user_id INT DEFAULT NULL, INDEX IDX_F21F790FA76ED395 (user_id), PRIMARY KEY(phonenumber)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB", $sql[7]);
        $this->assertEquals("ALTER TABLE cms_users ADD CONSTRAINT FK_3AF03EC5A832C1C9 FOREIGN KEY (email_id) REFERENCES cms_emails (id)", $sql[8]);
        $this->assertEquals("ALTER TABLE cms_users_groups ADD CONSTRAINT FK_7EA9409AA76ED395 FOREIGN KEY (user_id) REFERENCES cms_users (id)", $sql[9]);
        $this->assertEquals("ALTER TABLE cms_users_groups ADD CONSTRAINT FK_7EA9409AFE54D947 FOREIGN KEY (group_id) REFERENCES cms_groups (id)", $sql[10]);
        $this->assertEquals("ALTER TABLE cms_users_tags ADD CONSTRAINT FK_93F5A1ADA76ED395 FOREIGN KEY (user_id) REFERENCES cms_users (id)", $sql[11]);
        $this->assertEquals("ALTER TABLE cms_users_tags ADD CONSTRAINT FK_93F5A1ADBAD26311 FOREIGN KEY (tag_id) REFERENCES cms_tags (id)", $sql[12]);
        $this->assertEquals("ALTER TABLE cms_addresses ADD CONSTRAINT FK_ACAC157BA76ED395 FOREIGN KEY (user_id) REFERENCES cms_users (id)", $sql[13]);
        $this->assertEquals("ALTER TABLE cms_phonenumbers ADD CONSTRAINT FK_F21F790FA76ED395 FOREIGN KEY (user_id) REFERENCES cms_users (id)", $sql[14]);

        $this->assertEquals(15, count($sql));
    }

    public function testGetCreateSchemaSql2()
    {
        $classes = [
            $this->_em->getClassMetadata(Models\Generic\DecimalModel::class)
        ];

        $tool = new SchemaTool($this->_em);
        $sql = $tool->getCreateSchemaSql($classes);

        $this->assertEquals(1, count($sql));
        $this->assertEquals("CREATE TABLE decimal_model (id INT AUTO_INCREMENT NOT NULL, `decimal` NUMERIC(5, 2) NOT NULL, `high_scale` NUMERIC(14, 4) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB", $sql[0]);
    }

    public function testGetCreateSchemaSql3()
    {
        $classes = [
            $this->_em->getClassMetadata(Models\Generic\BooleanModel::class)
        ];

        $tool = new SchemaTool($this->_em);
        $sql = $tool->getCreateSchemaSql($classes);

        $this->assertEquals(1, count($sql));
        $this->assertEquals("CREATE TABLE boolean_model (id INT AUTO_INCREMENT NOT NULL, booleanField TINYINT(1) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB", $sql[0]);
    }

    /**
     * @group DBAL-204
     */
    public function testGetCreateSchemaSql4()
    {
        $classes = [
            $this->_em->getClassMetadata(MysqlSchemaNamespacedEntity::class)
        ];

        $tool = new SchemaTool($this->_em);
        $sql = $tool->getCreateSchemaSql($classes);

        $this->assertEquals(0, count($sql));
    }

    public function testUpdateSchemaSql()
    {
        $classes = [
            $this->_em->getClassMetadata(MyEntityToRemove::class),
        ];
        $tool    = new SchemaTool($this->_em);
        $sqls    = $tool->getUpdateSchemaSql($classes);
        $sqls    = $this->filterSqls($sqls, ['entity_to_remove']);
        $this->assertCount(1, $sqls);
        $this->assertContains('CREATE TABLE entity_to_remove (id INT AUTO_INCREMENT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB', $sqls);

        $this->_em->getConnection()->exec(current($sqls));
        $sqls = $tool->getUpdateSchemaSql($classes);
        $sqls = $this->filterSqls($sqls, ['entity_to_remove']);
        $this->assertCount(0, $sqls);

        $classes[] = $this->_em->getClassMetadata(MyOtherEntityToRemove::class);
        $sqls      = $tool->getUpdateSchemaSql($classes);
        $sqls      = $this->filterSqls($sqls, ['entity_to_remove', 'other_entity_to_remove']);
        $this->assertCount(1, $sqls);
        $this->assertContains('CREATE TABLE other_entity_to_remove (id INT AUTO_INCREMENT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB', $sqls);
    }

    public function provideUpdateSchemaSqlWithSchemaAssetFilter() : array
    {
        return [
            ['/^(?!entity_to_r)/', null],
            [
                null,
                static function ($assetName) : bool {
                    return $assetName !== 'entity_to_remove';
                },
            ],
        ];
    }

    /**
     * @dataProvider provideUpdateSchemaSqlWithSchemaAssetFilter
     */
    public function testUpdateSchemaSqlWithSchemaAssetFilter(?string $filterRegex, ?callable $filterCallback)
    {
        if ($filterRegex && ! method_exists(Configuration::class, 'setFilterSchemaAssetsExpression')) {
            $this->markTestSkipped(sprintf('Test require %s::setFilterSchemaAssetsExpression method', Configuration::class));
        }

        if ($filterCallback && ! method_exists(Configuration::class, 'setSchemaAssetsFilter')) {
            $this->markTestSkipped(sprintf('Test require %s::setSchemaAssetsFilter method', Configuration::class));
        }

        $classes = [$this->_em->getClassMetadata(MyEntityToRemove::class)];

        $tool = new SchemaTool($this->_em);
        $tool->createSchema($classes);

        $config = $this->_em->getConnection()->getConfiguration();
        if ($filterRegex) {
            $config->setFilterSchemaAssetsExpression($filterRegex);
        } else {
            $config->setSchemaAssetsFilter($filterCallback);
        }

        $sqls = $tool->getUpdateSchemaSql($classes);
        $sqls = $this->filterSqls($sqls, ['entity_to_remove']);
        $this->assertCount(0, $sqls);

        if ($filterRegex) {
            $this->assertEquals($filterRegex, $config->getFilterSchemaAssetsExpression());
        } else {
            $this->assertSame($filterCallback, $config->getSchemaAssetsFilter());
        }
    }

    private function filterSqls(array $sqls, array $needles) : array
    {
        return array_filter($sqls, static function ($sql) use ($needles) {
            foreach ($needles as $needle) {
                if (strpos($sql, $needle) !== false) {
                    return true;
                }
            }
            return false;
        });
    }
}

/**
 * @Entity
 * @Table("namespace.entity")
 */
class MysqlSchemaNamespacedEntity
{
    /** @Column(type="integer") @Id @GeneratedValue */
    public $id;
}

/**
 * @Entity
 * @Table(name="entity_to_remove")
 */
class MyEntityToRemove
{
    /**
     * @Id @Column(type="integer")
     * @GeneratedValue(strategy="AUTO")
     */
    public $id;
}

/**
 * @Entity
 * @Table(name="other_entity_to_remove")
 */
class MyOtherEntityToRemove
{
    /**
     * @Id @Column(type="integer")
     * @GeneratedValue(strategy="AUTO")
     */
    public $id;
}
