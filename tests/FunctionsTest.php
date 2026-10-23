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

    public function testDbDateIsReadAsUtcWhateverTheDefaultZone(): void
    {
        $previous = date_default_timezone_get();
        date_default_timezone_set('America/Vancouver');
        try {
            // 23:59 in BC on 2026-12-01 (permanent UTC-7) is 06:59Z the next day. With PHP 8.3's
            // bundled tz data, parsing BC wall time would give 07:59Z instead.
            $this->assertSame('2026-12-02T06:59:00+00:00', utcDbDateToAtom('2026-12-02 06:59:00'));
            $this->assertTrue(isSameInstant(utcDbDateToAtom('2026-12-02 06:59:00'), '2026-12-01T23:59:00-07:00'));
        } finally {
            date_default_timezone_set($previous);
        }
    }

    public function testUnparseableDbDateThrows(): void
    {
        $this->expectException(Exception::class);
        utcDbDateToAtom('not a date');
    }

    public function testMissingOrUnparseableValuesAreNeverEqual(): void
    {
        $this->assertFalse(isSameInstant(null, '2026-09-21T06:59:00Z'));
        $this->assertFalse(isSameInstant('2026-09-21T06:59:00Z', null));
        $this->assertFalse(isSameInstant('not a date', '2026-09-21T06:59:00Z'));
        $this->assertFalse(isSameInstant('not a date', 'not a date'));
    }
}
