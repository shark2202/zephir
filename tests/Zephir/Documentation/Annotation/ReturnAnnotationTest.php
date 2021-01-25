<?php

/*
 * This file is part of the Zephir.
 *
 * (c) Phalcon Team <team@zephir-lang.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Zephir\Test\Documentation\Annotation;

use PHPUnit\Framework\TestCase;
use Zephir\Documentation\Annotation\ReturnAnnotation;

class ReturnAnnotationTest extends TestCase
{
    const ANNOTATION_NAME = 'return';

    public function testShouldGetReturnAnnotation()
    {
        $link = new ReturnAnnotation(self::ANNOTATION_NAME, '  sample with leading space  ');

        $this->assertSame(self::ANNOTATION_NAME, $link->getName());
        $this->assertSame(
            'sample with leading space',
            $link->getString(),
            'Return types annotation should be without leading spaces'
        );

        $link->setString('array - Example description');
        $this->assertSame('array - Example description', $link->getString());
    }

    public function testShouldGetReturnType()
    {
        $testString = 'Zephir\EventInterface - Sample Event';

        $link = new ReturnAnnotation(self::ANNOTATION_NAME, $testString);

        $this->assertSame(self::ANNOTATION_NAME, $link->getName());
        $this->assertSame($testString, $link->getString());
        $this->assertSame('Zephir\EventInterface', $link->getReturnType());
    }

    public function testShouldGetLinkText()
    {
        $testString = 'Zephir\EventInterface - Sample Event';

        $link = new ReturnAnnotation(self::ANNOTATION_NAME, $testString);

        $this->assertSame(self::ANNOTATION_NAME, $link->getName());
        $this->assertSame($testString, $link->getString());
        $this->assertSame('- Sample Event', $link->getDescription());
    }
}
