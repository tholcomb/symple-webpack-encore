<?php
/**
 * This file contains portions of IntegrationTest.php from the Symfony WebpackEncoreBundle package.
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This file is part of the Symple Framework
 * Copyright (c) Tyler Holcomb <tyler@tholcomb.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tholcomb\Symple\WebpackEncore\Tests;

use PHPUnit\Framework\TestCase;
use Pimple\Container as PimpleContainer;
use Pimple\Psr11\ServiceLocator;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\WebpackEncoreBundle\Asset\TagRenderer;
use Tholcomb\Symple\Http\HttpProvider;
use Tholcomb\Symple\Logger\LoggerProvider;
use Tholcomb\Symple\Twig\TwigProvider;
use Tholcomb\Symple\WebpackEncore\WebpackEncoreProvider;

class IntegrationTest extends TestCase
{
    private function getPimple(): PimpleContainer
    {
        $c = new PimpleContainer();
        $c->register(new LoggerProvider(), ['logger.path' => '/dev/null']);
        $c->register(new HttpProvider());
        $c->register(new TwigProvider());
        $c->register(new WebpackEncoreProvider());
        HttpProvider::addController($c, TestController::class, function () { return new TestController(); });
        $fixturesDir = __DIR__ . '/../Symfony/fixtures';
        TwigProvider::addTemplateDir($c, $fixturesDir, 'integration_test');
        WebpackEncoreProvider::addEntryPoint($c, $fixturesDir . '/build/entrypoints.json');
        WebpackEncoreProvider::addEntryPoint($c, $fixturesDir . '/different_build/entrypoints.json', 'different_build');

        return $c;
    }

    private function getContainer(?PimpleContainer $c = null): ContainerInterface
    {
        if ($c === null) $c = $this->getPimple();

        return new ServiceLocator($c, [
            'twig' => TwigProvider::KEY_ENVIRONMENT,
            'public.webpack_encore.tag_renderer' => 'webpack.tag_renderer'
        ]);
    }

    public function testTwigIntegration()
    {
        $container = $this->getContainer();

        $html1 = $container->get('twig')->render('@integration_test/template.twig');
        $this->assertStringContainsString(
            '<script src="/build/file1.js" integrity="sha384-Q86c+opr0lBUPWN28BLJFqmLhho+9ZcJpXHorQvX6mYDWJ24RQcdDarXFQYN8HLc"></script>',
            $html1
        );
        $this->assertStringContainsString(
            '<link rel="stylesheet" href="/build/styles.css" integrity="sha384-4g+Zv0iELStVvA4/B27g4TQHUMwZttA5TEojjUyB8Gl5p7sarU4y+VTSGMrNab8n">'.
            '<link rel="stylesheet" href="/build/styles2.css" integrity="sha384-hfZmq9+2oI5Cst4/F4YyS2tJAAYdGz7vqSMP8cJoa8bVOr2kxNRLxSw6P8UZjwUn">',
            $html1
        );
        $this->assertStringContainsString(
            '<script src="/build/other3.js"></script>',
            $html1
        );
        $this->assertStringContainsString(
            '<link rel="stylesheet" href="/build/styles3.css">'.
            '<link rel="stylesheet" href="/build/styles4.css">',
            $html1
        );

        $html2 = $container->get('twig')->render('@integration_test/manual_template.twig');
        $this->assertStringContainsString(
            '<script src="/build/file3.js"></script>',
            $html2
        );
        $this->assertStringContainsString(
            '<script src="/build/other4.js"></script>',
            $html2
        );
    }

    public function testEntriesAreNotRepeatedWhenAlreadyOutputIntegration()
    {
        $container = $this->getContainer();

        $html1 = $container->get('twig')->render('@integration_test/template.twig');
        $html2 = $container->get('twig')->render('@integration_test/manual_template.twig');
        $this->assertStringContainsString(
            '<script src="/build/file3.js"></script>',
            $html2
        );
        // file1.js is not repeated
        $this->assertStringNotContainsString(
            '<script src="/build/file1.js"></script>',
            $html2
        );
        // styles3.css is not repeated
        $this->assertStringNotContainsString(
            '<link rel="stylesheet" href="/build/styles3.css">',
            $html2
        );
        // styles4.css is not repeated
        $this->assertStringNotContainsString(
            '<link rel="stylesheet" href="/build/styles4.css">',
            $html2
        );
    }

    public function testEnabledStrictMode_throwsException_ifBuildMissing()
    {
        $this->expectException(\Twig\Error\RuntimeError::class);
        $this->expectExceptionMessageRegExp('/Could not find the entrypoints file/');

        $c = $this->getPimple();
        $c['webpack.entrypoints'] = [];
        WebpackEncoreProvider::addEntryPoint($c, 'missing_build');
        WebpackEncoreProvider::addEntryPoint($c, 'missing_build', 'different_build');
        $container = $this->getContainer($c);
        $container->get('twig')->render('@integration_test/template.twig');
    }

    public function testDisabledStrictMode_ignoresMissingBuild()
    {
        $c = $this->getPimple();
        $c['webpack.entrypoints'] = [];
        $c['webpack.disable_strict_mode'] = true;
        WebpackEncoreProvider::addEntryPoint($c, 'missing_build');
        WebpackEncoreProvider::addEntryPoint($c, 'missing_build', 'different_build');
        $container = $this->getContainer($c);
        $html = $container->get('twig')->render('@integration_test/template.twig');
        self::assertSame('', trim($html));
    }

    public function testPreload()
    {
        $c = $this->getPimple();
        $c[HttpProvider::KEY_REQUEST] = function () { return Request::create('/foo'); };
        $kernel = HttpProvider::getKernel($c);
        $container = $this->getContainer($c);

        /** @var TagRenderer $tagRenderer */
        $tagRenderer = $container->get('public.webpack_encore.tag_renderer');
        $tagRenderer->renderWebpackLinkTags('my_entry');
        $tagRenderer->renderWebpackScriptTags('my_entry');

        $response = $kernel->handle(HttpProvider::getRequest($c));
        $this->assertStringContainsString('</build/file1.js>; rel="preload"; as="script"', $response->headers->get('Link'));
    }
}