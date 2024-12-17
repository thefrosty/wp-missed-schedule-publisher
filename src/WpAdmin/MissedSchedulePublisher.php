<?php

declare(strict_types=1);

namespace TheFrosty\WpMissedSchedulePublisher\WpAdmin;

use TheFrosty\WpUtilities\Plugin\HooksTrait;
use TheFrosty\WpUtilities\Plugin\HttpFoundationRequestInterface;
use TheFrosty\WpUtilities\Plugin\HttpFoundationRequestTrait;
use TheFrosty\WpUtilities\Plugin\WpHooksInterface;
use function add_query_arg;
use function admin_url;
use function apply_filters;
use function array_map;
use function count;
use function current_time;
use function get_option;
use function get_post_status;
use function hash_equals;
use function intval;
use function nocache_headers;
use function sprintf;
use function strval;
use function substr;
use function TheFrosty\WpUtilities\wp_enqueue_script;
use function TheFrosty\WpUtilities\wp_register_script;
use function time;
use function update_option;
use function wp_add_inline_script;
use function wp_hash;
use function wp_json_encode;
use function wp_nonce_tick;
use function wp_publish_post;
use function wp_safe_remote_post;
use function wp_send_json_success;

/**
 * Class MissedSchedulePublisher
 * @package TheFrosty\WpMissedSchedulePublisher
 */
class MissedSchedulePublisher implements HttpFoundationRequestInterface, WpHooksInterface
{

    use HooksTrait, HttpFoundationRequestTrait;

    public final const FILTER_BATCH_LIMIT = 'wp_missed_scheduled_publisher_batch_limit';
    public final const FILTER_FREQUENCY = 'wp_missed_scheduled_publisher_frequency';
    public final const ACTION_SCHEDULE_MISSED = 'wp_missed_scheduled_publisher_schedule_missed';
    public final const ACTION_SCHEDULE_PUBLISH = 'wp_missed_scheduled_publisher_schedule_publish';
    protected final const ACTION = 'wp_missed_scheduled_publisher';
    protected final const DEFAULT_BATCH_LIMIT = 20;
    protected final const FALLBACK_MULTIPLIER = 1.1;
    protected final const NONCE = self::ACTION . '_nonce';
    protected final const OPTION_NAME = 'wp_missed_scheduled_last_run';

    /**
     * Extra check to remove the loopBack method.
     * @var bool $removeLoopBack
     */
    private bool $removeLoopBack = false;

    /**
     * Add class hooks.
     */
    public function addHooks(): void
    {
        $this->addAction('send_headers', [$this, 'sendHeaders']);
        $this->addAction('shutdown', [$this, 'loopBack']);
        $this->addAction('wp_ajax_nopriv_' . self::ACTION, [$this, 'adminAjax']);
        $this->addAction('wp_ajax_' . self::ACTION, [$this, 'adminAjax']);
    }

    /**
     * Prevent caching of requests including the AJAX script.
     *
     * Includes the no-caching headers if the response will include the
     * AJAX fallback script. This is to prevent excess calls to the
     * admin-ajax.php action.
     */
    protected function sendHeaders(): void
    {
        if ($this->getOption() >= (time() - (self::FALLBACK_MULTIPLIER * $this->getFrequency()))) {
            return;
        }

        $this->addAction('wp_enqueue_scripts', [$this, 'enqueueScripts']);
        nocache_headers();
    }

    /**
     * Make a loop back request to publish posts with a missed schedule.
     */
    protected function loopBack(): void
    {
        if ($this->removeLoopBack || $this->getOption() >= (time() - $this->getFrequency())) {
            return;
        }

        // Do loop back request.
        $request = [
            'url' => add_query_arg('action', self::ACTION, admin_url('admin-ajax.php')),
            'args' => [
                'timeout' => 0.01,
                'blocking' => false,
                /** This filter is documented in wp-includes/class-wp-http-streams.php */
                'sslverify' => apply_filters('https_local_ssl_verify', false),
                'body' => [
                    self::NONCE => $this->getNoPrivilegeNonce(),
                ],
            ],
        ];

        wp_safe_remote_post(esc_url($request['url']), $request['args']);
    }

    /**
     * Handle HTTP request for publishing posts with a missed schedule.
     *
     * Always response with a success result to allow for full page caching
     * retaining the inline script. The visitor does not need to see error
     * messages in their browser.
     */
    protected function adminAjax(): void
    {
        $post = $this->getRequest()->request;
        if (!$post->has(self::NONCE) || !$this->verifyNoPrivilegeNonce($post->get(self::NONCE, ''))) {
            wp_send_json_success();
        }

        if ($this->getOption() >= (time() - $this->getFrequency())) {
            wp_send_json_success();
        }

        $this->publishMissedPosts();
        wp_send_json_success();
    }

    /**
     * Enqueue inline AJAX request to allow for failing loop back requests.
     */
    protected function enqueueScripts(): void
    {
        if ($this->getOption() >= (time() - (self::FALLBACK_MULTIPLIER * $this->getFrequency()))) {
            return;
        }

        // Shutdown loop back request is not needed.
        $this->removeLoopBack = $this->removeAction('shutdown', [$this, 'loopBack']);

        // Null script for inline script to come afterward.
        wp_register_script(self::ACTION, false, [], null, true);

        $request = [
            'url' => add_query_arg('action', self::ACTION, admin_url('admin-ajax.php')),
            'args' => [
                'method' => 'POST',
                'body' => sprintf('%s=%s', self::NONCE, $this->getNoPrivilegeNonce()),
            ],
        ];

        wp_add_inline_script(self::ACTION, $this->addInlineScript($request));
        wp_enqueue_script(self::ACTION);
    }

    /**
     * Get our "last run" option from the DB.
     * @return int
     */
    private function getOption(): int
    {
        return (int) get_option(self::OPTION_NAME, 0);
    }

    /**
     * Add our inline script.
     * @param array $value
     * @return string
     */
    private function addInlineScript(array $value): string
    {
        $request = wp_json_encode($value);
        $script = <<<JS
(function(data) {
  const publisher = () => {
    const request = JSON.parse(data)
    if (!window.fetch) {
      return
    }
  
    request.args.body = new URLSearchParams(request.args.body)
    fetch(request.url, request.args).then()
  }
  document.addEventListener('DOMContentLoaded', publisher, { once: true })
}('$request'))
JS;

        return $script;
    }

    /**
     * Generate a nonce without the UID and session components.
     * As this is a loop back request, the user will not be registered as logged in
     * so the generic WP Nonce function will not work.
     * @return string Nonce based on action name and tick.
     */
    private function getNoPrivilegeNonce(): string
    {
        $uid = 'n/a';
        $token = 'n/a';
        $i = wp_nonce_tick();

        return substr(wp_hash($i . '|' . self::ACTION . '|' . $uid . '|' . $token, 'nonce'), -12, 10);
    }

    /**
     * Verify a nonce without the UID and session components.
     *
     * As this comes from a loop back request, the user will not be registered as
     * logged in so the generic WP Nonce function will not work.
     *
     * The goal here is to mainly to protect against database reads in the event
     * of both full page caching and falling back to the ajax request in place of
     * a successful loop back request.
     * @param string $nonce Nonce based on action name and tick.
     * @return false|int False if nonce invalid. Integer containing tick if valid.
     */
    private function verifyNoPrivilegeNonce(string $nonce): false | int
    {
        if (empty($nonce)) {
            return false;
        }

        $uid = 'n/a';
        $token = 'n/a';
        $i = wp_nonce_tick();

        // Nonce generated 0-12 hours ago.
        $expected = substr(wp_hash($i . '|' . self::ACTION . '|' . $uid . '|' . $token, 'nonce'), -12, 10);
        if (hash_equals($expected, $nonce)) {
            return 1;
        }

        // Nonce generated 12-24 hours ago.
        $expected = substr(wp_hash(($i - 1) . '|' . self::ACTION . '|' . $uid . '|' . $token, 'nonce'), -12, 10);
        if (hash_equals($expected, $nonce)) {
            return 2;
        }

        return false;
    }

    /**
     * Filters the frequency to allow programmatically control.
     * Defaults to 900 (5 minutes in seconds).
     * @return int
     */
    private function getFrequency(): int
    {
        return (int) apply_filters(self::FILTER_FREQUENCY, 900);
    }

    /**
     * Publish posts with a missed schedule.
     */
    private function publishMissedPosts(): void
    {
        global $wpdb;

        update_option(self::OPTION_NAME, time(), false);

        $scheduled_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT ID FROM $wpdb->posts WHERE post_date <= %s AND post_status = 'future' LIMIT %d",
                strval(current_time('mysql')),
                intval(apply_filters(self::FILTER_BATCH_LIMIT, self::DEFAULT_BATCH_LIMIT))
            )
        );

        if (empty($scheduled_ids) || !count($scheduled_ids)) {
            return;
        }

        /**
         * Trigger action on missed scheduled ID's.
         * @param array $scheduled_ids
         */
        $this->doAction(self::ACTION_SCHEDULE_MISSED, [$scheduled_ids]);

        if (count($scheduled_ids) === self::DEFAULT_BATCH_LIMIT) {
            // There's a bit to do.
            update_option(self::OPTION_NAME, 0, false);
        }

        // Publish the posts.
        array_map(static function (int $id): void {
            $old_status = get_post_status($id);
            wp_publish_post($id);
            /**
             * Trigger action on each missed schedule publish.
             * @param int $id
             * @param false|string $old_status
             */
            $this->addAction(self::ACTION_SCHEDULE_PUBLISH, [$id, $old_status]);
        }, $scheduled_ids);
    }
}
