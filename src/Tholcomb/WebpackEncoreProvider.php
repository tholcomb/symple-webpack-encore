<?php
/**
 * This file is part of the Symple Framework
 *
 * Copyright (c) Tyler Holcomb <tyler@tholcomb.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tholcomb\Symple\WebpackEncore;

use Pimple\Container;
use Pimple\Psr11\ServiceLocator;
use Symfony\Bridge\Twig\Extension\AssetExtension;
use Symfony\Component\Asset\Context\RequestStackContext;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Asset\PathPackage;
use Symfony\Component\Asset\VersionStrategy\EmptyVersionStrategy;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\WebLink\EventListener\AddLinkHeaderListener;
use Symfony\WebpackEncoreBundle\Asset\EntrypointLookup;
use Symfony\WebpackEncoreBundle\Asset\EntrypointLookupCollection;
use Symfony\WebpackEncoreBundle\Asset\TagRenderer;
use Symfony\WebpackEncoreBundle\EventListener\ExceptionListener;
use Symfony\WebpackEncoreBundle\EventListener\PreLoadAssetsEventListener;
use Symfony\WebpackEncoreBundle\Twig\EntryFilesTwigExtension;
use Tholcomb\Symple\Core\AbstractProvider;
use Tholcomb\Symple\Http\HttpProvider;
use Tholcomb\Symple\Twig\TwigProvider;
use Twig\Environment;
use function Tholcomb\Symple\Core\isset_and_true;

class WebpackEncoreProvider extends AbstractProvider
{
    protected const NAME = 'webpack';

    public function register(Container $c)
    {
        parent::register($c);
        HttpProvider::isRegistered($c, true);
        TwigProvider::isRegistered($c, true);
        $c->extend(TwigProvider::KEY_ENVIRONMENT, function (Environment $twig, Container $c) {
            $twig->addExtension($c['webpack.twig_ext']);
            $twig->addExtension(new AssetExtension($c['webpack.packages']));
            return $twig;
        });
        $c->extend(HttpProvider::KEY_DISPATCHER, function (EventDispatcherInterface $dispatcher, Container $c) {
           $dispatcher->addSubscriber($c['webpack.preload_listener']);
           $dispatcher->addSubscriber($c['webpack.add_link_header_listener']);
           $dispatcher->addListener(KernelEvents::EXCEPTION, [$c['webpack.exception_listener'], 'onKernelException']);
           return $dispatcher;
        });
        $c['webpack.add_link_header_listener'] = function () {
            return new AddLinkHeaderListener();
        };
        $c['webpack.preload_listener'] = function ($c) {
          return new PreLoadAssetsEventListener($c['webpack.tag_renderer']);
        };
        $c['webpack.exception_listener'] = function ($c) {
            return new ExceptionListener($c['webpack.entrypoint_col'], array_keys($c['webpack.entrypoints']));
        };
        $c['webpack.twig_ext'] = function ($c) {
          return new EntryFilesTwigExtension(new ServiceLocator($c, [
              'webpack_encore.entrypoint_lookup_collection' => 'webpack.entrypoint_col',
              'webpack_encore.tag_renderer' => 'webpack.tag_renderer',
          ]));
        };
        $c['webpack.entrypoints'] = [];
        $c['webpack.entrypoint_col'] = function ($c) { // EntrypointLookupCollectionInterface
            return new EntrypointLookupCollection(new ServiceLocator($c, $c['webpack.entrypoints']), '_default');
        };
        $c['webpack.tag_renderer'] = function ($c) { // TagRenderer
            return new TagRenderer($c['webpack.entrypoint_col'], $c['webpack.packages']);
        };
        $c['webpack.packages'] = function ($c) {
            return new Packages($c['webpack.base_package']);
        };
        $c['webpack.base_package'] = function ($c) {
            return new PathPackage('/', $c['webpack.version_strategy'], $c['webpack.request_stack_context']);
        };
        $c['webpack.version_strategy'] = function () {
            return new EmptyVersionStrategy();
        };
        $c['webpack.request_stack_context'] = function ($c) {
            return new RequestStackContext($c['http.request_stack']);
        };
    }

    public static function addEntryPoint(Container $c, string $file, string $buildName = '_default'): void
    {
        $key = "webpack.ep.$buildName";
        $c[$key] = function ($c) use ($file, $buildName) {
            $strictMode = (isset_and_true('webpack.disable_strict_mode', $c) === false);
            return new EntrypointLookup($file, new ArrayAdapter(), $buildName, $strictMode);
        };
        $c['webpack.entrypoints'] = array_merge($c['webpack.entrypoints'], [$buildName => $key]);
    }
}