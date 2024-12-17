# WordPress Missed Schedule Publisher

[![PHP from Packagist](https://img.shields.io/packagist/php-v/thefrosty/wp-missed-schedule-publisher.svg)]()
[![Latest Stable Version](https://img.shields.io/packagist/v/thefrosty/wp-missed-schedule-publisher.svg)](https://packagist.org/packages/thefrosty/wp-missed-schedule-publisher)
[![Total Downloads](https://img.shields.io/packagist/dt/thefrosty/wp-missed-schedule-publisher.svg)](https://packagist.org/packages/thefrosty/wp-missed-schedule-publisher)
[![License](https://img.shields.io/packagist/l/thefrosty/wp-missed-schedule-publisher.svg)](https://packagist.org/thefrosty/wp-missed-schedule-publisher)
![Build Status](https://github.com/thefrosty/wp-missed-schedule-publisher/actions/workflows/main.yml/badge.svg)

Catches scheduled posts that have been missed and publishes them.

## Package Installation (via Composer)

`$ composer require thefrosty/wp-missed-schedule-publisher:^1.0`

-----

- [Actions](#actions)

1. `TheFrosty\WpMissedSchedulePublisher\WpAdmin\MissedSchedulePublisher::ACTION_SCHEDULE_MISSED`
2. `TheFrosty\WpMissedSchedulePublisher\WpAdmin\MissedSchedulePublisher::ACTION_SCHEDULE_PUBLISH`

- [How to use actions](#how-to-use-actions)
   - When a one or more posts have missed their schedule. Example:
     ```php
      use TheFrosty\WpMissedSchedulePublisher\WpAdmin\MissedSchedulePublisher;
      add_action(MissedSchedulePublisher::ACTION_SCHEDULE_MISSED, static function(array $post_ids): void {
            // Do something with the post ID's array.
      });
      ```
   - When a post that missed its schedule is published. Example:
     ```php
      use TheFrosty\WpMissedSchedulePublisher\WpAdmin\MissedSchedulePublisher;
      add_action(MissedSchedulePublisher::ACTION_SCHEDULE_PUBLISH, static function(int $post_id,  false | string $old_status): void {
            $new_status = get_post_status($post_id);

            // Maybe there was an issue publishing?
            if ($old_status === $new_status) {
                return;
            }
      
            // Do something with the $post_id. 
      });
      ```
- [Filters](#filters)

1. `TheFrosty\WpMissedSchedulePublisher\WpAdmin\MissedSchedulePublisher::FILTER_BATCH_LIMIT`
2. `TheFrosty\WpMissedSchedulePublisher\WpAdmin\MissedSchedulePublisher::FILTER_FREQUENCY`

- [How to use filters](#how-to-use-filters)
   - Change the allowed batch (query) limit count. Example:
     ```php
      use TheFrosty\WpMissedSchedulePublisher\WpAdmin\MissedSchedulePublisher;
      // Lower it to only 10 (defaults to 20) 
      add_filter(MissedSchedulePublisher::FILTER_BATCH_LIMIT, static fn(): int => 10);
       ```
   - Change the frequency of the check (in seconds). Example:
     ```php
      use TheFrosty\WpMissedSchedulePublisher\WpAdmin\MissedSchedulePublisher;
      // Every hour (defaults to 15 minutes) 
      add_filter(MissedSchedulePublisher::FILTER_FREQUENCY, static fn(): int => \HOUR_IN_SECONDS);
      // Every minute (defaults to 15 minutes) 
      add_filter(MissedSchedulePublisher::FILTER_FREQUENCY, static fn(): int => \MINUTE_IN_SECONDS);
      ```

