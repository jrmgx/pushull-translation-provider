<?php
/*
 * This file is part of the pushull-translation-provider package.
 *
 * (c) 2022 m2m server software gmbh <tech@m2m.at>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Pushull\PushullTranslationProvider\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Pushull\PushullTranslationProvider\DependencyInjection\Configuration;
use Symfony\Component\Config\Definition\Processor;

class ConfigurationTest extends TestCase
{
    public function testDefault(): void
    {
        $config = $this->getConfig([]);

        $this->assertEquals(true, $config['verify_peer']);
    }

    public function testDisableVerifcation(): void
    {
        $config = $this->getConfig([
            'verify_peer' => false,
        ]);

        $this->assertEquals(false, $config['verify_peer']);
    }

    /**
     * @param array<string,bool> $input
     *
     * @return array<string,bool>
     */
    private function getConfig(array $input): array
    {
        return (new Processor())->processConfiguration(new Configuration(), [$input]);
    }
}
