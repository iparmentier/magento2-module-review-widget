<?php
/**
 * Schema Manager Unit Test
 *
 * Tests for the Schema.org manager to prevent duplicates
 *
 * @category  Amadeco
 * @package   Amadeco_ReviewWidget
 * @author    Ilan Parmentier <contact@amadeco.fr>
 * @copyright Copyright (c) 2025 Amadeco
 * @license   Proprietary License
 */
declare(strict_types=1);

namespace Amadeco\ReviewWidget\Test\Unit\Helper;

use Amadeco\ReviewWidget\Helper\Config;
use Amadeco\ReviewWidget\Helper\SchemaManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Class SchemaManagerTest
 *
 * Unit tests for SchemaManager helper
 *
 * @coversDefaultClass \Amadeco\ReviewWidget\Helper\SchemaManager
 */
class SchemaManagerTest extends TestCase
{
    /**
     * @var SchemaManager
     */
    private SchemaManager $schemaManager;

    /**
     * @var Config|MockObject
     */
    private $config;

    /**
     * @var LoggerInterface|MockObject
     */
    private $logger;

    /**
     * Set up test fixtures
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->schemaManager = new SchemaManager(
            $this->config,
            $this->logger
        );
    }

    /**
     * Test that schema generation is prevented when disabled in config
     *
     * @covers ::shouldGenerateSchema
     * @covers ::isSchemaEnabled
     * @return void
     */
    public function testShouldNotGenerateSchemaWhenDisabled(): void
    {
        // Arrange
        $this->config
            ->expects($this->once())
            ->method('isSchemaOrgEnabled')
            ->willReturn(false);

        // Act
        $result = $this->schemaManager->shouldGenerateSchema('badge');

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test that schema generation is allowed for first widget
     *
     * @covers ::shouldGenerateSchema
     * @covers ::isBadgeSchemaGenerated
     * @return void
     */
    public function testShouldGenerateSchemaForFirstWidget(): void
    {
        // Arrange
        $this->config
            ->method('isSchemaOrgEnabled')
            ->willReturn(true);

        // Act
        $result = $this->schemaManager->shouldGenerateSchema('badge');

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test that schema generation is prevented for subsequent widgets
     *
     * @covers ::shouldGenerateSchema
     * @covers ::markBadgeSchemaAsGenerated
     * @covers ::isBadgeSchemaGenerated
     * @return void
     */
    public function testShouldNotGenerateSchemaForSecondWidget(): void
    {
        // Arrange
        $this->config
            ->method('isSchemaOrgEnabled')
            ->willReturn(true);

        // First widget - should generate
        $firstResult = $this->schemaManager->shouldGenerateSchema('badge');
        $this->assertTrue($firstResult);

        // Mark as generated
        $this->schemaManager->markBadgeSchemaAsGenerated();

        // Act - Second widget
        $secondResult = $this->schemaManager->shouldGenerateSchema('badge');

        // Assert
        $this->assertFalse($secondResult);
    }

    /**
     * Test adding review to combined collection
     *
     * @covers ::addReviewToCombined
     * @covers ::getCombinedReviews
     * @return void
     */
    public function testAddReviewToCombined(): void
    {
        // Arrange
        $reviewData = [
            '@type' => 'Review',
            'author' => ['@type' => 'Person', 'name' => 'John Doe'],
            'reviewBody' => 'Great product!'
        ];

        // Act
        $this->schemaManager->addReviewToCombined($reviewData);
        $result = $this->schemaManager->getCombinedReviews();

        // Assert
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertEquals('John Doe', $result[0]['author']['name']);
    }

    /**
     * Test getting combined review data
     *
     * @covers ::getCombinedReviews
     * @covers ::addReviewToCombined
     * @return void
     */
    public function testGetCombinedReviewData(): void
    {
        // Arrange
        $review1 = ['@type' => 'Review', 'author' => 'John'];
        $review2 = ['@type' => 'Review', 'author' => 'Jane'];

        $this->schemaManager->addReviewToCombined($review1);
        $this->schemaManager->addReviewToCombined($review2);

        // Act
        $result = $this->schemaManager->getCombinedReviews();

        // Assert
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertEquals('John', $result[0]['author']);
        $this->assertEquals('Jane', $result[1]['author']);
    }

    /**
     * Test duplicate review prevention in combined collection
     *
     * @covers ::addReviewToCombined
     * @covers ::getCombinedReviews
     * @return void
     */
    public function testDuplicateReviewPrevention(): void
    {
        // Arrange
        $reviewData = [
            '@type' => 'Review',
            'author' => 'John Doe',
            'reviewBody' => 'Great product!'
        ];

        // Act - Add same review twice
        $this->schemaManager->addReviewToCombined($reviewData);
        $this->schemaManager->addReviewToCombined($reviewData);

        $result = $this->schemaManager->getCombinedReviews();

        // Assert - Should only have one review
        $this->assertCount(1, $result);
    }

    /**
     * Test that badge and reviews schemas are tracked independently
     *
     * @covers ::shouldGenerateSchema
     * @covers ::markBadgeSchemaAsGenerated
     * @covers ::markReviewsSchemaAsGenerated
     * @return void
     */
    public function testBadgeAndReviewsSchemasAreIndependent(): void
    {
        // Arrange
        $this->config->method('isSchemaOrgEnabled')->willReturn(true);

        // Mark badge as generated
        $this->schemaManager->markBadgeSchemaAsGenerated();

        // Act & Assert
        $this->assertFalse($this->schemaManager->shouldGenerateSchema('badge'));
        $this->assertTrue($this->schemaManager->shouldGenerateSchema('reviews'));
    }
}