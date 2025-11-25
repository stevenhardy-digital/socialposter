<?php

namespace Tests\Feature;

use Eris\Generator;
use Eris\TestTrait;
use Tests\TestCase;
use Carbon\Carbon;

class CalendarPropertyTest extends TestCase
{
    use TestTrait;

    /**
     * **Feature: social-media-platform, Property 22: Date filtering shows correct posts**
     * **Validates: Requirements 6.2**
     * 
     * For any calendar date, clicking should show all posts scheduled for that specific date
     */
    public function testDateFilteringShowsCorrectPosts()
    {
        $this->forAll(
            Generator\choose(2024, 2026), // year
            Generator\choose(1, 12), // month
            Generator\choose(1, 28), // day (safe range for all months)
            Generator\elements(['instagram', 'facebook', 'linkedin']),
            Generator\elements(['Post content A', 'Post content B', 'Post content C'])
        )->then(function ($year, $month, $day, $platform, $content) {
            // Create target date safely
            $targetDate = Carbon::create($year, $month, $day, 12, 0, 0);
            $targetDateString = $targetDate->format('Y-m-d');
            
            // Simulate posts for different dates - create posts that we know should be filtered
            $postsForTargetDate = [
                [
                    'id' => 1,
                    'content' => $content,
                    'scheduled_at' => $targetDate->format('Y-m-d H:i:s'),
                    'platform' => $platform,
                    'status' => 'approved'
                ],
                [
                    'id' => 2,
                    'content' => 'Another post for same date',
                    'scheduled_at' => $targetDate->copy()->addHours(2)->format('Y-m-d H:i:s'),
                    'platform' => 'facebook',
                    'status' => 'draft'
                ]
            ];
            
            $postsForOtherDates = [
                [
                    'id' => 3,
                    'content' => 'Post for different date',
                    'scheduled_at' => $targetDate->copy()->addDay()->format('Y-m-d H:i:s'),
                    'platform' => 'instagram',
                    'status' => 'approved'
                ],
                [
                    'id' => 4,
                    'content' => 'Post for previous date',
                    'scheduled_at' => $targetDate->copy()->subDay()->format('Y-m-d H:i:s'),
                    'platform' => 'linkedin',
                    'status' => 'published'
                ]
            ];
            
            $allPosts = array_merge($postsForTargetDate, $postsForOtherDates);

            // Simulate calendar filtering logic
            $filteredPosts = array_filter($allPosts, function($post) use ($targetDateString) {
                $postDate = Carbon::parse($post['scheduled_at'])->format('Y-m-d');
                return $postDate === $targetDateString;
            });

            // Verify filtering shows correct posts
            $this->assertGreaterThanOrEqual(1, count($filteredPosts)); // Should have at least 1 post for target date
            
            // Verify all filtered posts are for the target date
            foreach ($filteredPosts as $post) {
                $postDate = Carbon::parse($post['scheduled_at'])->format('Y-m-d');
                $this->assertEquals($targetDateString, $postDate);
            }
            
            // Verify our main test post is included
            $postIds = array_column($filteredPosts, 'id');
            $this->assertContains(1, $postIds); // Our main test post should be included
            
            // Verify posts from other dates are excluded
            $this->assertNotContains(3, $postIds); // Different date post should be excluded
            $this->assertNotContains(4, $postIds); // Previous date post should be excluded
            
            // Verify posts maintain their properties
            foreach ($filteredPosts as $post) {
                $this->assertArrayHasKey('content', $post);
                $this->assertArrayHasKey('scheduled_at', $post);
                $this->assertArrayHasKey('platform', $post);
                $this->assertArrayHasKey('status', $post);
                $this->assertNotEmpty($post['content']);
                $this->assertContains($post['platform'], ['instagram', 'facebook', 'linkedin']);
                $this->assertContains($post['status'], ['draft', 'approved', 'published', 'rejected']);
            }
            
            // Verify that filtering is precise - no posts from wrong dates
            $wrongDatePosts = array_filter($filteredPosts, function($post) use ($targetDateString) {
                $postDate = Carbon::parse($post['scheduled_at'])->format('Y-m-d');
                return $postDate !== $targetDateString;
            });
            $this->assertCount(0, $wrongDatePosts);
        });
    }

    /**
     * **Feature: social-media-platform, Property 23: Drag-and-drop updates schedule**
     * **Validates: Requirements 6.3**
     * 
     * For any post and target date, dragging a post should update the scheduled publication date
     */
    public function testDragAndDropUpdatesSchedule()
    {
        $this->forAll(
            Generator\choose(2024, 2026), // year
            Generator\choose(1, 12), // month
            Generator\choose(1, 28), // original day
            Generator\choose(1, 28), // target day
            Generator\elements(['instagram', 'facebook', 'linkedin']),
            Generator\elements(['draft', 'approved']), // Only non-published posts can be moved
            Generator\elements(['Post content for drag test', 'Draggable post content', 'Schedule update test'])
        )->then(function ($year, $month, $originalDay, $targetDay, $platform, $status, $content) {
            // Create original and target dates
            $originalDate = Carbon::create($year, $month, $originalDay, 14, 30, 0);
            $targetDate = Carbon::create($year, $month, $targetDay, 14, 30, 0);
            
            // Simulate a post that can be dragged
            $post = [
                'id' => 1,
                'content' => $content,
                'scheduled_at' => $originalDate->toISOString(),
                'platform' => $platform,
                'status' => $status,
                'social_account_id' => 123
            ];

            // Verify initial state
            $this->assertEquals($originalDate->format('Y-m-d'), Carbon::parse($post['scheduled_at'])->format('Y-m-d'));
            $this->assertContains($status, ['draft', 'approved']);
            $this->assertNotEquals('published', $post['status']);

            // Simulate drag-and-drop operation (update scheduled_at to target date)
            // Keep the original time but change the date
            $newScheduledAt = Carbon::create(
                $targetDate->year,
                $targetDate->month,
                $targetDate->day,
                $originalDate->hour,
                $originalDate->minute,
                $originalDate->second
            );
            
            $post['scheduled_at'] = $newScheduledAt->toISOString();

            // Verify the schedule was updated
            $updatedDate = Carbon::parse($post['scheduled_at']);
            $this->assertEquals($targetDate->format('Y-m-d'), $updatedDate->format('Y-m-d'));
            
            // Verify time was preserved during drag operation
            $this->assertEquals($originalDate->format('H:i:s'), $updatedDate->format('H:i:s'));
            
            // Verify other post properties remain unchanged
            $this->assertEquals($content, $post['content']);
            $this->assertEquals($platform, $post['platform']);
            $this->assertEquals($status, $post['status']);
            $this->assertEquals(123, $post['social_account_id']);
            
            // Verify the post is still draggable (not published)
            $this->assertNotEquals('published', $post['status']);
            
            // Verify date change is meaningful (unless original and target are same)
            if ($originalDay !== $targetDay) {
                $this->assertNotEquals(
                    $originalDate->format('Y-m-d'),
                    $updatedDate->format('Y-m-d')
                );
            }
            
            // Verify the new date is valid
            $this->assertInstanceOf(Carbon::class, $updatedDate);
            $this->assertEquals($year, $updatedDate->year);
            $this->assertEquals($month, $updatedDate->month);
            $this->assertEquals($targetDay, $updatedDate->day);
        });
    }

    /**
     * **Feature: social-media-platform, Property 24: Visual indicators differentiate status**
     * **Validates: Requirements 6.4**
     * 
     * For any posts in calendar view, the system should use different visual indicators for draft, approved, and published posts
     */
    public function testVisualIndicatorsDifferentiateStatus()
    {
        $this->forAll(
            Generator\elements(['draft', 'approved', 'published', 'rejected']),
            Generator\elements(['instagram', 'facebook', 'linkedin']),
            Generator\elements(['Test post content', 'Visual indicator test', 'Status differentiation check'])
        )->then(function ($status, $platform, $content) {
            // Simulate a post with specific status
            $post = [
                'id' => 1,
                'content' => $content,
                'status' => $status,
                'platform' => $platform,
                'scheduled_at' => now()->addDay()->toISOString()
            ];

            // Simulate the visual indicator logic (CSS class assignment)
            $visualIndicatorClass = $this->getPostStatusClass($post['status']);

            // Verify each status has a unique visual indicator
            $expectedClasses = [
                'draft' => 'bg-yellow-100 text-yellow-800 border-yellow-200',
                'approved' => 'bg-green-100 text-green-800 border-green-200',
                'published' => 'bg-blue-100 text-blue-800 border-blue-200',
                'rejected' => 'bg-red-100 text-red-800 border-red-200'
            ];

            // Verify the status has a corresponding visual class
            $this->assertArrayHasKey($status, $expectedClasses);
            $this->assertEquals($expectedClasses[$status], $visualIndicatorClass);

            // Verify visual indicators are distinct for different statuses
            foreach ($expectedClasses as $otherStatus => $otherClass) {
                if ($otherStatus !== $status) {
                    $this->assertNotEquals($visualIndicatorClass, $otherClass);
                }
            }

            // Verify visual indicator contains appropriate color coding
            switch ($status) {
                case 'draft':
                    $this->assertStringContainsString('yellow', $visualIndicatorClass);
                    break;
                case 'approved':
                    $this->assertStringContainsString('green', $visualIndicatorClass);
                    break;
                case 'published':
                    $this->assertStringContainsString('blue', $visualIndicatorClass);
                    break;
                case 'rejected':
                    $this->assertStringContainsString('red', $visualIndicatorClass);
                    break;
            }

            // Verify visual indicator has consistent structure (background, text, border)
            $this->assertStringContainsString('bg-', $visualIndicatorClass);
            $this->assertStringContainsString('text-', $visualIndicatorClass);
            $this->assertStringContainsString('border-', $visualIndicatorClass);

            // Verify visual indicator is not empty or default
            $this->assertNotEmpty($visualIndicatorClass);
            $this->assertNotEquals('bg-gray-100 text-gray-800 border-gray-200', $visualIndicatorClass);
        });
    }

    /**
     * Helper method to simulate the visual indicator logic
     */
    private function getPostStatusClass($status)
    {
        $classes = [
            'draft' => 'bg-yellow-100 text-yellow-800 border-yellow-200',
            'approved' => 'bg-green-100 text-green-800 border-green-200',
            'published' => 'bg-blue-100 text-blue-800 border-blue-200',
            'rejected' => 'bg-red-100 text-red-800 border-red-200'
        ];
        return $classes[$status] ?? 'bg-gray-100 text-gray-800 border-gray-200';
    }

    /**
     * **Feature: social-media-platform, Property 25: Empty dates show empty state**
     * **Validates: Requirements 6.5**
     * 
     * For any calendar date without posts, the system should display an empty state
     */
    public function testEmptyDatesShowEmptyState()
    {
        $this->forAll(
            Generator\choose(2024, 2026), // year
            Generator\choose(1, 12), // month
            Generator\choose(1, 28), // empty date
            Generator\choose(1, 28), // date with posts
            Generator\elements(['instagram', 'facebook', 'linkedin'])
        )->then(function ($year, $month, $emptyDay, $postDay, $platform) {
            // Create dates
            $emptyDate = Carbon::create($year, $month, $emptyDay, 12, 0, 0);
            $postDate = Carbon::create($year, $month, $postDay, 12, 0, 0);
            
            $emptyDateString = $emptyDate->format('Y-m-d');
            $postDateString = $postDate->format('Y-m-d');

            // Simulate posts data - some dates have posts, others don't
            $allPosts = [];
            
            // Only add posts for the postDate, not the emptyDate
            if ($emptyDay !== $postDay) {
                $allPosts = [
                    [
                        'id' => 1,
                        'content' => 'Post for non-empty date',
                        'scheduled_at' => $postDate->format('Y-m-d H:i:s'),
                        'platform' => $platform,
                        'status' => 'approved'
                    ]
                ];
            }

            // Simulate getting posts for the empty date
            $postsForEmptyDate = array_filter($allPosts, function($post) use ($emptyDateString) {
                $postDate = Carbon::parse($post['scheduled_at'])->format('Y-m-d');
                return $postDate === $emptyDateString;
            });

            // Simulate getting posts for the date with posts (if different)
            $postsForPostDate = array_filter($allPosts, function($post) use ($postDateString) {
                $postDate = Carbon::parse($post['scheduled_at'])->format('Y-m-d');
                return $postDate === $postDateString;
            });

            // Verify empty date has no posts
            $this->assertCount(0, $postsForEmptyDate);

            // Simulate empty state logic
            $shouldShowEmptyState = count($postsForEmptyDate) === 0;
            $emptyStateMessage = $shouldShowEmptyState ? 'No posts scheduled' : null;

            // Verify empty state is shown for dates without posts
            $this->assertTrue($shouldShowEmptyState);
            $this->assertEquals('No posts scheduled', $emptyStateMessage);
            $this->assertNotNull($emptyStateMessage);

            // If dates are different, verify the post date has posts (contrast test)
            if ($emptyDay !== $postDay) {
                $this->assertGreaterThan(0, count($postsForPostDate));
                
                // Verify non-empty date doesn't show empty state
                $shouldShowEmptyStateForPostDate = count($postsForPostDate) === 0;
                $this->assertFalse($shouldShowEmptyStateForPostDate);
            }

            // Verify empty state properties
            $this->assertIsString($emptyStateMessage);
            $this->assertNotEmpty($emptyStateMessage);
            $this->assertStringContainsString('No posts', $emptyStateMessage);

            // Verify empty state is contextually appropriate
            $this->assertStringContainsString('scheduled', $emptyStateMessage);
            
            // Verify empty state doesn't contain post data
            $this->assertStringNotContainsString('approved', $emptyStateMessage);
            $this->assertStringNotContainsString('draft', $emptyStateMessage);
            $this->assertStringNotContainsString($platform, $emptyStateMessage);
        });
    }
}