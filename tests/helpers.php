<?php

use PHPUnit\Framework\Assert;

/** Strict comparison of the expected and the actual value. */
function expect(mixed $expected, mixed $actual, string $what): void
{
	Assert::assertSame($expected, $actual, $what);
}

/** The condition holds. */
function holds(bool $ok, string $what): void
{
	Assert::assertTrue($ok, $what);
}
