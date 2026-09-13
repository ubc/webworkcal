<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/functions.php';

final class FunctionsTest extends TestCase
{
    public function testSplitCourseIdStripsLeadingStudentCount(): void
    {
        $this->assertSame('MATH_V_100_101-WW1', splitCourseId('298-MATH_V_100_101-WW1'));
    }

    public function testSplitCourseIdKeepsSummaryWithoutNumericPrefix(): void
    {
        $this->assertSame('MATH_V_100_101-WW1', splitCourseId('MATH_V_100_101-WW1'));
        $this->assertSame('Office hours', splitCourseId('Office hours'));
    }

    public function testSameInstantAcrossTimeZoneRepresentations(): void
    {
        // What the script sends (local ATOM) versus what Google hands back (UTC).
        $this->assertTrue(isSameInstant('2026-09-20T23:59:00-07:00', '2026-09-21T06:59:00Z'));
        $this->assertTrue(isSameInstant('2026-09-20T23:59:00-07:00', '2026-09-20T23:59:00-07:00'));
    }

    public function testDifferentInstantsAreNotEqual(): void
    {
        $this->assertFalse(isSameInstant('2026-09-20T23:59:00-07:00', '2026-09-21T23:59:00-07:00'));
        $this->assertFalse(isSameInstant('2026-09-20T23:59:00-07:00', '2026-09-20T23:59:00Z'));
    }

    public function testMissingOrUnparseableValuesAreNeverEqual(): void
    {
        $this->assertFalse(isSameInstant(null, '2026-09-21T06:59:00Z'));
        $this->assertFalse(isSameInstant('2026-09-21T06:59:00Z', null));
        $this->assertFalse(isSameInstant('not a date', '2026-09-21T06:59:00Z'));
        $this->assertFalse(isSameInstant('not a date', 'not a date'));
    }
}
