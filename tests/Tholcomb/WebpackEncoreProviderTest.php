<?php
/**
 * This file is part of the Symple Framework
 *
 * Copyright (c) Tyler Holcomb <tyler@tholcomb.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tholcomb\Symple\WebpackEncore\Tests;

use PHPUnit\Framework\TestCase;
use Pimple\Container;
use Tholcomb\Symple\Http\HttpProvider;
use Tholcomb\Symple\Twig\TwigProvider;
use Tholcomb\Symple\Core\UnregisteredProviderException;
use Tholcomb\Symple\WebpackEncore\WebpackEncoreProvider;

class WebpackEncoreProviderTest extends TestCase
{
    public function testMissingHttp()
    {
        $this->expectException(UnregisteredProviderException::class);
        $this->expectExceptionMessageRegExp('/' . preg_quote(HttpProvider::class, '/') . '/');

        $c = new Container();
        $c->register(new TwigProvider());
        $c->register(new WebpackEncoreProvider());
    }

    public function testMissingTwig()
    {
        $this->expectException(UnregisteredProviderException::class);
        $this->expectExceptionMessageRegExp('/' . preg_quote(TwigProvider::class, '/') . '/');

        $c = new Container();
        $c->register(new HttpProvider());
        $c->register(new WebpackEncoreProvider());
    }
}