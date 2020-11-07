<?php

namespace Doctrine\Tests\ORM\Functional\Ticket;

use Doctrine\ORM\Proxy\Proxy;
use Doctrine\Tests\Models\CMS\CmsGroup;
use Doctrine\Tests\VerifyDeprecations;

class DDC1734Test extends \Doctrine\Tests\OrmFunctionalTestCase
{
    use VerifyDeprecations;

    /**
     * {@inheritDoc}
     */
    protected function setUp() : void
    {
        $this->useModelSet('cms');
        parent::setUp();
    }

    /** @after */
    public function ensureTestGeneratedDeprecationMessages() : void
    {
        $this->assertHasDeprecationMessages();
    }

    /**
     * This test is DDC-1734 minus the serialization, i.e. it works
     *
     * @group DDC-1734
     */
    public function testMergeWorksOnNonSerializedProxies()
    {
        $group = new CmsGroup();

        $group->setName('Foo');
        $this->_em->persist($group);
        $this->_em->flush();
        $this->_em->clear();

        $proxy = $this->getProxy($group);

        $this->assertInstanceOf(Proxy::class, $proxy);
        $this->assertFalse($proxy->__isInitialized());

        $this->_em->detach($proxy);
        $this->_em->clear();

        $proxy = $this->_em->merge($proxy);

        $this->assertEquals('Foo', $proxy->getName(), 'The entity is broken');
    }

    /**
     * This test reproduces DDC-1734 which is:
     * - A non-initialized proxy is detached and serialized (the identifier of the proxy is *not* serialized)
     * - the object is deserialized and merged (to turn into an entity)
     * - the entity is broken because it has no identifier and no field defined
     *
     * @group DDC-1734
     */
    public function testMergeWorksOnSerializedProxies()
    {
        $group = new CmsGroup();

        $group->setName('Foo');
        $this->_em->persist($group);
        $this->_em->flush();
        $this->_em->clear();

        $proxy = $this->getProxy($group);

        $this->assertInstanceOf(Proxy::class, $proxy);
        $this->assertFalse($proxy->__isInitialized());

        $this->_em->detach($proxy);
        $serializedProxy = serialize($proxy);
        $this->_em->clear();

        $unserializedProxy = $this->_em->merge(unserialize($serializedProxy));
        $this->assertEquals('Foo', $unserializedProxy->getName(), 'The entity is broken');
    }

    /**
     * @param object $object
     *
     * @return \Doctrine\Common\Proxy\Proxy
     */
    private function getProxy($object)
    {
        $metadataFactory = $this->_em->getMetadataFactory();
        $className       = get_class($object);
        $identifier      = $metadataFactory->getMetadataFor($className)->getIdentifierValues($object);

        return $this->_em->getProxyFactory()->getProxy($className, $identifier);
    }

}
