<?php declare(strict_types=1);

namespace Rector\Sensio\Tests\Rector\FrameworkExtraBundle\TemplateAnnotationRector;

use Iterator;
use Rector\Testing\PHPUnit\AbstractRectorTestCase;

/**
 * @covers \Rector\Sensio\Rector\FrameworkExtraBundle\TemplateAnnotationRector
 */
final class TemplateAnnotationVersion3RectorTest extends AbstractRectorTestCase
{
    protected function setUp(): void
    {
        $this->rebuildFreshContainer = true;

        parent::setUp();
    }

    /**
     * @dataProvider provideWrongToFixedFiles()
     */
    public function test(string $wrong, string $fixed): void
    {
        $this->doTestFileMatchesExpectedContent($wrong, $fixed);
    }

    public function provideWrongToFixedFiles(): Iterator
    {
        yield [__DIR__ . '/Wrong/wrong.php.inc', __DIR__ . '/Correct/Version3/correct.php.inc'];
        yield [__DIR__ . '/Wrong/wrong2.php.inc', __DIR__ . '/Correct/Version3/correct2.php.inc'];
        yield [__DIR__ . '/Wrong/wrong3.php.inc', __DIR__ . '/Correct/Version3/correct3.php.inc'];
        yield [__DIR__ . '/Wrong/wrong4.php.inc', __DIR__ . '/Correct/Version3/correct4.php.inc'];
        yield [__DIR__ . '/Wrong/wrong5.php.inc', __DIR__ . '/Correct/Version3/correct5.php.inc'];
        yield [__DIR__ . '/Wrong/wrong6.php.inc', __DIR__ . '/Correct/Version3/correct6.php.inc'];
        yield [__DIR__ . '/Wrong/wrong7.php.inc', __DIR__ . '/Correct/Version3/correct7.php.inc'];
        yield [__DIR__ . '/Wrong/wrong8.php.inc', __DIR__ . '/Correct/Version3/correct8.php.inc'];
    }

    protected function provideConfig(): string
    {
        return __DIR__ . '/config-version3.yml';
    }
}
