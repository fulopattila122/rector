<?php declare(strict_types=1);

final class MyTest extends \PHPUnit\Framework\TestCase
{
    public function test()
    {
        $this->assertFalse(! ($something === false));
        $this->assertTrue(! $something);
        self::assertTrue(! $something);
    }
}
