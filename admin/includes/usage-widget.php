<?php
/**
 * Dashboard widget: AI usage & cost.
 *
 * @package WPiko_Chatbot
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Friendly model names for the usage report, merged per model.
 *
 * @param array<string, float> $costs Model ID => cost.
 * @return array<string, float> Model name => cost.
 */
function wpiko_chatbot_usage_model_labels($costs)
{
    $names = function_exists('wpiko_chatbot_get_responses_api_models') ? wpiko_chatbot_get_responses_api_models() : array();
    $labeled = array();
    foreach ($costs as $model => $cost) {
        $key = wpiko_chatbot_usage_price_key($model);
        $label = ($key !== '' && isset($names[$key])) ? $names[$key] : $model;
        $labeled[$label] = ($labeled[$label] ?? 0) + $cost;
    }
    arsort($labeled);
    return $labeled;
}

/**
 * What one call of a source is called, for the breakdown ("12 replies").
 *
 * @param string $source Source key.
 * @param int    $count  Number of calls.
 * @return string
 */
function wpiko_chatbot_usage_count_label($source, $count)
{
    $number = number_format_i18n($count);
    switch ($source) {
        case 'chat':
            /* translators: %s: number of AI replies. */
            return sprintf(_n('%s reply', '%s replies', $count, 'wpiko-chatbot'), $number);
        case 'chat_tools':
            /* translators: %s: number of order or product lookups. */
            return sprintf(_n('%s lookup', '%s lookups', $count, 'wpiko-chatbot'), $number);
        case 'connection_test':
            /* translators: %s: number of connection tests. */
            return sprintf(_n('%s test', '%s tests', $count, 'wpiko-chatbot'), $number);
        default:
            /* translators: %s: number of AI requests. */
            return sprintf(_n('%s request', '%s requests', $count, 'wpiko-chatbot'), $number);
    }
}

/**
 * Render the "AI usage & cost" Dashboard widget.
 *
 * @param array|null $snapshot Usage snapshot, when the Dashboard already has one.
 * @return void
 */
function wpiko_chatbot_render_usage_widget($snapshot = null)
{
    if (!function_exists('wpiko_chatbot_get_usage_snapshot')) {
        return;
    }

    if (!is_array($snapshot)) {
        $snapshot = wpiko_chatbot_get_usage_snapshot();
    }
    $month = $snapshot['month'];
    $week = $snapshot['week'];
    $today = $snapshot['today'];
    $last_month = $snapshot['last_month'];
    $has_data = $month['calls'] > 0 || $week['calls'] > 0 || (is_array($last_month) && $last_month['calls'] > 0);
    $date_format = get_option('date_format', 'F j, Y');
    $since_label = wp_date($date_format, $snapshot['since']->getTimestamp());
    $prices_label = wp_date($date_format, strtotime(WPIKO_CHATBOT_PRICES_UPDATED . ' 12:00:00 UTC'));
    $usage_url = function_exists('wpiko_chatbot_openai_url') ? wpiko_chatbot_openai_url('usage') : 'https://platform.openai.com/usage';
    ?>
    <section class="dashboard-widget usage-widget" id="wpiko-usage" tabindex="-1" aria-labelledby="wpiko-usage-title">
        <div class="widget-header">
            <h2 id="wpiko-usage-title">
                <?php esc_html_e('AI usage & cost', 'wpiko-chatbot'); ?>
                <span class="usage-badge"><?php esc_html_e('Estimate', 'wpiko-chatbot'); ?></span>
            </h2>
            <a href="<?php echo esc_url($usage_url); ?>" class="widget-action" target="_blank" rel="noopener noreferrer">
                <?php esc_html_e('OpenAI usage', 'wpiko-chatbot'); ?><span class="screen-reader-text"> <?php esc_html_e('(opens in a new tab)', 'wpiko-chatbot'); ?></span>
            </a>
        </div>
        <div class="widget-content">
            <?php if (!$has_data): ?>
                <div class="no-activity usage-empty">
                    <span class="dashicons dashicons-chart-bar" aria-hidden="true"></span>
                    <div>
                        <strong><?php esc_html_e('No AI usage recorded yet', 'wpiko-chatbot'); ?></strong>
                        <p>
                            <?php
                            echo esc_html(sprintf(
                                /* translators: %s: date cost tracking started. */
                                __('The estimated cost appears here after the chatbot’s next reply. Costs are counted from %s.', 'wpiko-chatbot'),
                                $since_label
                            ));
                            ?>
                        </p>
                    </div>
                </div>
            <?php else: ?>
                <div class="usage-periods">
                    <div class="usage-period">
                        <span class="usage-period-label"><?php esc_html_e('Today', 'wpiko-chatbot'); ?></span>
                        <span class="usage-period-value"><?php echo esc_html(wpiko_chatbot_format_usage_cost($today['cost'])); ?></span>
                        <span class="usage-period-meta"><?php echo esc_html(wpiko_chatbot_usage_count_label('chat', $today['replies'])); ?></span>
                    </div>
                    <div class="usage-period">
                        <span class="usage-period-label"><?php esc_html_e('Last 7 days', 'wpiko-chatbot'); ?></span>
                        <span class="usage-period-value"><?php echo esc_html(wpiko_chatbot_format_usage_cost($week['cost'])); ?></span>
                        <span class="usage-period-meta"><?php echo esc_html(wpiko_chatbot_usage_count_label('chat', $week['replies'])); ?></span>
                    </div>
                    <div class="usage-period is-month">
                        <span class="usage-period-label"><?php echo esc_html($snapshot['month_label']); ?></span>
                        <span class="usage-period-value"><?php echo esc_html(wpiko_chatbot_format_usage_cost($month['cost'])); ?></span>
                        <?php if ($snapshot['projected'] !== null): ?>
                            <span class="usage-period-meta">
                                <?php
                                echo esc_html(sprintf(
                                    /* translators: %s: projected cost for the month. */
                                    __('On track for about %s', 'wpiko-chatbot'),
                                    wpiko_chatbot_format_usage_cost($snapshot['projected'])
                                ));
                                ?>
                            </span>
                        <?php else: ?>
                            <span class="usage-period-meta"><?php echo esc_html(wpiko_chatbot_usage_count_label('chat', $month['replies'])); ?></span>
                        <?php endif; ?>
                        <?php if (is_array($last_month)): ?>
                            <span class="usage-period-meta">
                                <?php
                                echo esc_html(sprintf(
                                    /* translators: 1: month name, 2: cost. */
                                    __('%1$s: %2$s', 'wpiko-chatbot'),
                                    $snapshot['last_month_label'],
                                    wpiko_chatbot_format_usage_cost($last_month['cost'])
                                ));
                                ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($month['conversations'] > 0 && $month['replies'] > 0): ?>
                    <dl class="usage-averages">
                        <div>
                            <dt><?php esc_html_e('Per conversation', 'wpiko-chatbot'); ?></dt>
                            <dd><?php echo esc_html(wpiko_chatbot_format_usage_cost($month['conversation_cost'] / $month['conversations'], true)); ?></dd>
                        </div>
                        <div>
                            <dt><?php esc_html_e('Per AI reply', 'wpiko-chatbot'); ?></dt>
                            <dd><?php echo esc_html(wpiko_chatbot_format_usage_cost($month['conversation_cost'] / $month['replies'], true)); ?></dd>
                        </div>
                        <div>
                            <dt><?php esc_html_e('Conversations', 'wpiko-chatbot'); ?></dt>
                            <dd><?php echo esc_html(number_format_i18n($month['conversations'])); ?></dd>
                        </div>
                    </dl>
                <?php endif; ?>

                <?php
                $rows = array();
                foreach ($month['sources'] as $source => $item) {
                    $rows[] = array(
                        'label' => $item['label'],
                        'meta' => wpiko_chatbot_usage_count_label($source, $item['calls']),
                        'cost' => $item['token_cost'],
                    );
                }
                if ($month['search_calls'] > 0) {
                    $rows[] = array(
                        'label' => __('Knowledge search', 'wpiko-chatbot'),
                        /* translators: %s: number of file searches. */
                        'meta' => sprintf(_n('%s search', '%s searches', $month['search_calls'], 'wpiko-chatbot'), number_format_i18n($month['search_calls'])),
                        'cost' => $month['search_cost'],
                    );
                }
                ?>
                <?php if (!empty($rows)): ?>
                    <h3 class="usage-subtitle">
                        <?php
                        echo esc_html(sprintf(
                            /* translators: %s: month and year. */
                            __('%s by feature', 'wpiko-chatbot'),
                            $snapshot['month_label']
                        ));
                        ?>
                    </h3>
                    <ul class="usage-breakdown">
                        <?php foreach ($rows as $row):
                            $share = $month['cost'] > 0 ? max(0, min(100, $row['cost'] / $month['cost'] * 100)) : 0;
                            ?>
                            <li class="usage-row">
                                <span class="usage-row-label"><?php echo esc_html($row['label']); ?></span>
                                <span class="usage-row-meta"><?php echo esc_html($row['meta']); ?></span>
                                <span class="usage-row-cost"><?php echo esc_html(wpiko_chatbot_format_usage_cost($row['cost'])); ?></span>
                                <span class="usage-row-bar" aria-hidden="true"><span style="width: <?php echo esc_attr(round($share, 1)); ?>%"></span></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <?php if ($month['calls'] > 0): ?>
                    <details class="usage-details">
                        <summary><?php esc_html_e('Tokens and models', 'wpiko-chatbot'); ?></summary>
                        <dl class="usage-tokens">
                            <div>
                                <dt><?php esc_html_e('Input tokens', 'wpiko-chatbot'); ?></dt>
                                <dd>
                                    <?php echo esc_html(number_format_i18n($month['input'])); ?>
                                    <?php if ($month['cached'] > 0): ?>
                                        <span>
                                            <?php
                                            echo esc_html(sprintf(
                                                /* translators: %s: number of cached tokens. */
                                                __('(%s cached, billed at a lower price)', 'wpiko-chatbot'),
                                                number_format_i18n($month['cached'])
                                            ));
                                            ?>
                                        </span>
                                    <?php endif; ?>
                                </dd>
                            </div>
                            <div>
                                <dt><?php esc_html_e('Output tokens', 'wpiko-chatbot'); ?></dt>
                                <dd>
                                    <?php echo esc_html(number_format_i18n($month['output'])); ?>
                                    <?php if ($month['reasoning'] > 0): ?>
                                        <span>
                                            <?php
                                            echo esc_html(sprintf(
                                                /* translators: %s: number of reasoning tokens. */
                                                __('(%s reasoning)', 'wpiko-chatbot'),
                                                number_format_i18n($month['reasoning'])
                                            ));
                                            ?>
                                        </span>
                                    <?php endif; ?>
                                </dd>
                            </div>
                            <?php foreach (wpiko_chatbot_usage_model_labels($snapshot['models']) as $model_label => $model_cost): ?>
                                <div>
                                    <dt><?php echo esc_html($model_label); ?></dt>
                                    <dd><?php echo esc_html(wpiko_chatbot_format_usage_cost($model_cost)); ?></dd>
                                </div>
                            <?php endforeach; ?>
                        </dl>
                    </details>
                <?php endif; ?>

                <?php if ($month['unpriced'] > 0): ?>
                    <p class="usage-warning">
                        <?php
                        echo esc_html(sprintf(
                            /* translators: %s: number of API calls. */
                            _n(
                                '%s request this month used a model without a known price. Its tokens are counted, but it is not included in the cost.',
                                '%s requests this month used a model without a known price. Their tokens are counted, but they are not included in the cost.',
                                $month['unpriced'],
                                'wpiko-chatbot'
                            ),
                            number_format_i18n($month['unpriced'])
                        ));
                        ?>
                    </p>
                <?php endif; ?>

                <p class="usage-note">
                    <?php
                    echo esc_html(sprintf(
                        /* translators: %s: date the prices were checked. */
                        __('Estimated with OpenAI list prices (checked %s). Your OpenAI bill is the final amount.', 'wpiko-chatbot'),
                        $prices_label
                    ));
                    // Only while the periods shown start before tracking did, and only for sites that chatted before it.
                    if ($snapshot['partial'] && wpiko_chatbot_usage_has_earlier_chats($snapshot['since'])) {
                        echo ' ' . esc_html(sprintf(
                            /* translators: %s: date cost tracking started on this site (the day the plugin was updated). */
                            __('Costs are counted from %s, the day this report was added to your site; earlier chats are not included.', 'wpiko-chatbot'),
                            $since_label
                        ));
                    }
                    ?>
                </p>
            <?php endif; ?>
        </div>
    </section>
    <?php
}
