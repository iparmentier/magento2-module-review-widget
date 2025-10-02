<?php
/**
 * Rating Calculator Unit Test
 *
 * Tests for the rating calculation service
 *
 * @category  Amadeco
 * @package   Amadeco_ReviewWidget
 * @author    Ilan Parmentier <contact@amadeco.fr>
 * @copyright Copyright (c) 2025 Amadeco
 * @license   Proprietary License
 */
declare(strict_types=1);

namespace Amadeco\ReviewWidget\Test\Unit\Service;

use Amadeco\ReviewWidget\Api\Data\StoreRatingInterface;
use Amadeco\ReviewWidget\Api\Data\StoreRatingInterfaceFactory;
use Amadeco\ReviewWidget\Api\ReviewRepositoryInterface;
use Amadeco\ReviewWidget\Helper\Config;
use Amadeco\ReviewWidget\Model\Data\StoreRating;
use Amadeco\ReviewWidget\Service\RatingCalculator;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Review\Model\ResourceModel\Review\Product\Collection;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Class RatingCalculatorTest
 *
 * Unit tests for RatingCalculator service
 *
 * @coversDefaultClass \Amadeco\ReviewWidget\Service\RatingCalculator
 */
class RatingCalculatorTest extends TestCase
{
    /**
     * @var RatingCalculator
     */
    private RatingCalculator $ratingCalculator;

    /**
     * @var StoreRatingInterfaceFactory|MockObject
     */
    private $storeRatingFactory;

    /**
     * @var ReviewRepositoryInterface|MockObject
     */
    private $reviewRepository;

    /**
     * @var StoreManagerInterface|MockObject
     */
    private $storeManager;

    /**
     * @var CacheInterface|MockObject
     */
    private $cache;

    /**
     * @var SerializerInterface|MockObject
     */
    private $serializer;

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
        $this->storeRatingFactory = $this->createMock(StoreRatingInterfaceFactory::class);
        $this->reviewRepository = $this->createMock(ReviewRepositoryInterface::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->cache = $this->createMock(CacheInterface::class);
        $this->serializer = $this->createMock(SerializerInterface::class);
        $this->config = $this->createMock(Config::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->ratingCalculator = new RatingCalculator(
            $this->storeRatingFactory,
            $this->reviewRepository,
            $this->storeManager,
            $this->cache,
            $this->serializer,
            $this->config,
            $this->logger
        );
    }

    /**
     * Test successful rating calculation
     *
     * @covers ::calculateStoreRating
     * @return void
     */
    public function testCalculateStoreRatingSuccess(): void
    {
        // Arrange
        $reviewCollection = $this->createReviewCollection([
            ['sum' => 400], // 80% = 4 stars
            ['sum' => 450], // 90% = 4.5 stars
            ['sum' => 500], // 100% = 5 stars
        ]);

        $expectedAverage = (400 + 450 + 500) / 3; // 450
        $expectedNote = round($expectedAverage / 100 * 5, 2); // 4.5

        $storeRating = new StoreRating();
        $this->storeRatingFactory
            ->expects($this->once())
            ->method('create')
            ->willReturn($storeRating);

        // Act
        $result = $this->ratingCalculator->calculateStoreRating($reviewCollection);

        // Assert
        $this->assertInstanceOf(StoreRatingInterface::class, $result);
        $this->assertEquals(3, $result->getTotal());
        $this->assertEquals($expectedAverage, $result->getPercentage());
        $this->assertEquals($expectedNote, $result->getNote());
        $this->assertEquals($expectedAverage / 20, $result->getAverageRating());
    }

    /**
     * Test rating calculation with insufficient reviews
     *
     * @covers ::calculateStoreRating
     * @return void
     */
    public function testCalculateStoreRatingInsufficientData(): void
    {
        // Arrange
        $emptyCollection = $this->createReviewCollection([]);

        // Act
        $result = $this->ratingCalculator->calculateStoreRating($emptyCollection);

        // Assert
        $this->assertNull($result);
    }

    /**
     * Test getting store rating from cache
     *
     * @covers ::getStoreRating
     * @return void
     */
    public function testGetStoreRatingFromCache(): void
    {
        // Arrange
        $storeId = 1;
        $cachedData = [
            'total' => 10,
            'percentage' => 85.5,
            'note' => 4.28,
            'average_rating' => 4.275
        ];

        $store = $this->createMock(\Magento\Store\Model\Store::class);
        $store->method('getId')->willReturn($storeId);
        $this->storeManager->method('getStore')->willReturn($store);

        $this->cache
            ->expects($this->once())
            ->method('load')
            ->with('amadeco_review_widget_rating_' . $storeId)
            ->willReturn('serialized_data');

        $this->serializer
            ->expects($this->once())
            ->method('unserialize')
            ->with('serialized_data')
            ->willReturn($cachedData);

        $storeRating = new StoreRating(['data' => $cachedData]);
        $this->storeRatingFactory
            ->expects($this->once())
            ->method('create')
            ->with(['data' => $cachedData])
            ->willReturn($storeRating);

        // Act
        $result = $this->ratingCalculator->getStoreRating($storeId);

        // Assert
        $this->assertInstanceOf(StoreRatingInterface::class, $result);
        $this->assertEquals(10, $result->getTotal());
        $this->assertEquals(85.5, $result->getPercentage());
    }

    /**
     * Test cache clearing for specific store
     *
     * @covers ::clearCache
     * @return void
     */
    public function testClearCacheForSpecificStore(): void
    {
        // Arrange
        $storeId = 1;
        $cacheKey = 'amadeco_review_widget_rating_' . $storeId;

        $this->cache
            ->expects($this->once())
            ->method('remove')
            ->with($cacheKey);

        // Act
        $this->ratingCalculator->clearCache($storeId);

        // Assert - expects called via mock
        $this->assertTrue(true);
    }

    /**
     * Test cache clearing for all stores
     *
     * @covers ::clearCache
     * @return void
     */
    public function testClearCacheForAllStores(): void
    {
        // Arrange
        $this->cache
            ->expects($this->once())
            ->method('clean')
            ->with(['AMADECO_REVIEW_WIDGET']);

        // Act
        $this->ratingCalculator->clearCache();

        // Assert - expects called via mock
        $this->assertTrue(true);
    }

    /**
     * Test error handling in calculation
     *
     * @covers ::calculateStoreRating
     * @return void
     */
    public function testCalculateStoreRatingHandlesException(): void
    {
        // Arrange
        $reviewCollection = $this->createMock(Collection::class);
        $reviewCollection
            ->method('getIterator')
            ->willThrowException(new \Exception('Database error'));

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with(
                $this->stringContains('Error calculating store rating'),
                $this->arrayHasKey('exception')
            );

        // Act
        $result = $this->ratingCalculator->calculateStoreRating($reviewCollection);

        // Assert
        $this->assertNull($result);
    }

    /**
     * Create mock review collection
     *
     * @param array $reviewsData
     * @return Collection|MockObject
     */
    private function createReviewCollection(array $reviewsData): Collection
    {
        $collection = $this->createMock(Collection::class);
        $reviews = [];

        foreach ($reviewsData as $data) {
            $review = new DataObject($data);
            $reviews[] = $review;
        }

        $collection
            ->method('getIterator')
            ->willReturn(new \ArrayIterator($reviews));

        return $collection;
    }
}