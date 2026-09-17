<?php

declare(strict_types=1);

namespace BothDecks\Tests\Unit\Taxonomy\Ingestion;

use BothDecks\Taxonomy\Internal\Ingestion\LabelNormaliser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(LabelNormaliser::class)]
final class LabelNormaliserTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function spellingsThatMustCompareEqual(): iterable
    {
        yield 'case' => ['Software Architect', 'software architect'];
        yield 'accents' => ['développeur', 'developpeur'];
        yield 'punctuation and spacing' => ['Full-Stack  (senior)', 'full stack senior'];
        yield 'ligature' => ['ﬁnance', 'finance'];
        yield 'compatibility width' => ['ｓｏｆｔｗａｒｅ', 'software'];
        yield 'greek tonos' => ['αρχιτέκτονας', 'αρχιτεκτονας'];
        yield 'cyrillic case' => ['Софтуерен Архитект', 'софтуерен архитект'];
        yield 'surrounding whitespace' => ["  welder\t", 'welder'];
    }

    #[DataProvider('spellingsThatMustCompareEqual')]
    public function testTwoSpellingsOfTheSameWordNormaliseToTheSameText(string $spelling, string $expected): void
    {
        // Act
        $normalised = LabelNormaliser::normalise($spelling);

        // Assert
        self::assertSame($expected, $normalised);
    }

    public function testItIsIdempotent(): void
    {
        // A label normalised at import and a query normalised at resolution must meet in the middle; if a
        // second pass changed the text, the two would be one pass apart.

        // Arrange
        $once = LabelNormaliser::normalise('Softwarearchitekt/Softwarearchitektin (m/w/d)');

        // Act
        $twice = LabelNormaliser::normalise($once);

        // Assert
        self::assertSame($once, $twice);
    }

    public function testLettersWithoutADecomposedFormAreKept(): void
    {
        // A stroked l and a sharp s carry no combining mark to strip; dropping them would delete letters.

        // Act
        $normalised = LabelNormaliser::normalise('Łódź Straße');

        // Assert
        self::assertSame('łodz straße', $normalised);
    }

    public function testTextMadeOfNoLettersOrDigitsBecomesEmpty(): void
    {
        // Act
        $normalised = LabelNormaliser::normalise(' - / ( ) ');

        // Assert
        self::assertSame('', $normalised);
    }

    public function testDigitsSurvive(): void
    {
        // Act
        $normalised = LabelNormaliser::normalise('ISO 9001 auditor');

        // Assert
        self::assertSame('iso 9001 auditor', $normalised);
    }
}
