<?php

declare(strict_types=1);

namespace TheFrosty\WpMissedSchedulePublisher\Integration;

use TheFrosty\WpMissedSchedulePublisher\WpAdmin\MissedSchedulePublisher;
use TheFrosty\WpUtilities\Plugin\HooksTrait;
use TheFrosty\WpUtilities\Plugin\WpHooksInterface;
use function array_map;
use function esc_html__;
use function get_post_status;
use function get_the_title;
use function implode;
use function sprintf;

/**
 * Class SimpleHistory
 * @package TheFrosty\Integration
 */
class SimpleHistory implements WpHooksInterface
{

    use HooksTrait;

    /**
     * Add class hooks.
     */
    public function addHooks(): void
    {
        $this->addAction(MissedSchedulePublisher::ACTION_SCHEDULE_MISSED, [$this, 'scheduleMissed']);
        $this->addAction(MissedSchedulePublisher::ACTION_SCHEDULE_PUBLISH, [$this, 'schedulePublish']);
    }

    /**
     * Log all IDs that have missed their schedule.
     * @param array $post_ids
     */
    protected function scheduleMissed(array $post_ids): void
    {
        apply_filters(
            'simple_history_log',
            sprintf(esc_html__('Missed scheduled posts: %1$s', 'missed-schedule-publisher'), '{posts}'),
            [
                'posts' => implode(
                    ', ',
                    array_map(static fn(int $id): string => sprintf('%s (%d)', get_the_title($id), $id), $post_ids)
                ),
            ],
            'alert'
        );
    }

    /**
     * Log all IDs that have missed their schedule.
     * @param int $post_id
     * @param false|string $old_status
     */
    protected function schedulePublish(int $post_id, false | string $old_status): void
    {
        $new_status = get_post_status($post_id);

        // Maybe there was an issue publishing?
        if ($old_status === $new_status) {
            return;
        }

        apply_filters(
            'simple_history_log',
            sprintf(
                esc_html__(
                    'Missed scheduled post %1$s status change -- new: "%2$s", old: "%3$s"',
                    'missed-schedule-publisher'
                ),
                '{post}',
                '{new_status}',
                '{old_status}'
            ),
            [
                'post' => sprintf('%s (%d)', get_the_title($post_id), $post_id),
                'new_status' => get_post_status($post_id),
                'old_status' => $old_status,
            ]
        );
    }
}
