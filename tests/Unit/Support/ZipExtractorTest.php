<?php

// SPDX-License-Identifier: Apache-2.0 OR GPL-2.0-or-later

declare(strict_types=1);

namespace OpenSalesTax\PrestaShop\Tests\Unit\Support;

use OpenSalesTax\PrestaShop\Support\ZipExtractor;
use PHPUnit\Framework\TestCase;

final class ZipExtractorTest extends TestCase
{
    public function testFiveDigitZipPassesThrough(): void
    {
        self::assertSame('55401', (new ZipExtractor())->extract('55401'));
    }

    public function testZipPlusFourReturnsFirstFive(): void
    {
        self::assertSame('55401', (new ZipExtractor())->extract('55401-1234'));
    }

    public function testZipPlusFourSpaceSeparatedReturnsFirstFive(): void
    {
        self::assertSame('55401', (new ZipExtractor())->extract('55401 1234'));
    }

    public function testWhitespacePaddingIsTolerated(): void
    {
        self::assertSame('55401', (new ZipExtractor())->extract(' 55401 '));
    }

    public function testStateAbbreviationPrefixIsStripped(): void
    {
        self::assertSame('55401', (new ZipExtractor())->extract('MN 55401'));
    }

    public function testEmptyStringReturnsNull(): void
    {
        self::assertNull((new ZipExtractor())->extract(''));
    }

    public function testCanadianPostalCodeReturnsNull(): void
    {
        self::assertNull((new ZipExtractor())->extract('K1A 0B1'));
    }

    public function testFourDigitNumberReturnsNull(): void
    {
        self::assertNull((new ZipExtractor())->extract('1234'));
    }
}
