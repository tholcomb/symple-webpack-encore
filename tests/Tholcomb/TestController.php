<?php
/**
 * This file is part of the Symple PHP Framework
 * (c) Tyler Holcomb <tyler@tholcomb.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tholcomb\Symple\WebpackEncore\Tests;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Tholcomb\Rw\Http\AbstractController;

/**
 * @Route("/")
 */
class TestController extends AbstractController {
    /**
     * @Route("/foo")
     */
    public function foo() {
        return new Response('I am a page!');
    }
}