<?php

/*
 * This file is part of the Symfony WebpackEncoreBundle package.
 * (c) Fabien Potencier <fabien@symfony.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\WebpackEncoreBundle\Tests\Asset;

use PHPUnit\Framework\TestCase;
use Pimple\Container;
use Pimple\Psr11\ServiceLocator;
use Symfony\WebpackEncoreBundle\Asset\EntrypointLookupCollection;
use Symfony\WebpackEncoreBundle\Asset\EntrypointLookupInterface;

class EntrypointLookupCollectionTest extends TestCase
{
    public function testExceptionOnMissingEntry()
    {
        $this->expectException(\Symfony\WebpackEncoreBundle\Exception\UndefinedBuildException::class);
        $this->expectExceptionMessage('The build "something" is not configured');

        $collection = new EntrypointLookupCollection(new ServiceLocator(new Container(), []));
        $collection->getEntrypointLookup('something');
    }

    public function testExceptionOnMissingDefaultBuildEntry()
    {
        $this->expectException(\Symfony\WebpackEncoreBundle\Exception\UndefinedBuildException::class);
        $this->expectExceptionMessage('There is no default build configured: please pass an argument to getEntrypointLookup().');

        $collection = new EntrypointLookupCollection(new ServiceLocator(new Container(), []));
        $collection->getEntrypointLookup();
    }

    public function testDefaultBuildIsReturned()
    {
        $lookup = $this->createMock(EntrypointLookupInterface::class);
        $name = 'the_default';
        $collection = new EntrypointLookupCollection(new ServiceLocator(new Container([$name => function () use ($lookup) { return $lookup; }]), [$name]), $name);

        $this->assertSame($lookup, $collection->getEntrypointLookup());
    }
}
