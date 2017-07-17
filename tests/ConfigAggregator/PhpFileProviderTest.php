<?php
/**
 * @see       https://github.com/zendframework/zend-config-aggregator for the canonical source repository
 * @copyright Copyright (c) 2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @copyright Copyright (c) 2015-2016 Mateusz Tymek (http://mateusztymek.pl)
 * @license   https://github.com/zendframework/zend-config-aggregator/blob/master/LICENSE.md New BSD License
 */
namespace ZendServerTest\DepH\ConfigAggregator;

use PHPUnit_Framework_TestCase as TestCase;
use ZendServer\DepH\ConfigAggregator\PhpFileProvider;
use Zend\Stdlib\ArrayUtils;

class PhpFileProviderTest extends TestCase
{
    public function testProviderLoadsConfigFromFiles()
    {
        $provider = new PhpFileProvider(__DIR__ . '/Resources/config/{{,*.}global,{,*.}local}.php');
        $merged = array();
        foreach ($provider() as $item) {
            $merged = ArrayUtils::merge($merged, $item);
        }
        $this->assertEquals(array('fruit' => 'banana', 'vegetable' => 'potato'), $merged);
    }
}