<?php

declare(strict_types=1);

namespace Tesserae\Editor;

use Tesserae\Support\Arr;

/**
 * Answers the two questions the renderer keeps asking: is this request editing,
 * and is this request a machine reading the page.
 */
final class EditSession
{
    public const QUERY_VAR = 'tesserae';

    private ?bool $editing = null;
    private ?bool $robot = null;

    public function mode(): string
    {
        $mode = isset($_GET[self::QUERY_VAR]) && \is_string($_GET[self::QUERY_VAR])
            ? sanitize_key(wp_unslash($_GET[self::QUERY_VAR]))
            : '';

        return \in_array($mode, ['edit', 'view', 'robot'], true) ? $mode : '';
    }

    public function isEditing(): bool
    {
        if (null !== $this->editing) {
            return $this->editing;
        }

        $editing = 'edit' === $this->mode() && $this->canEdit($this->postId());

        return $this->editing = (bool) apply_filters('tesserae/is_editing', $editing);
    }

    /**
     * The post the current request is editing or rendering.
     */
    public function postId(): int
    {
        $queried = get_queried_object();

        if ($queried instanceof \WP_Post) {
            return $queried->ID;
        }

        return (int) get_the_ID();
    }

    public function canEdit(int $postId): bool
    {
        if ($postId <= 0 || !is_user_logged_in()) {
            return false;
        }

        $post = get_post($postId);

        if (!$post instanceof \WP_Post || !$this->isEnabledFor($post->post_type)) {
            return false;
        }

        return current_user_can('edit_post', $postId);
    }

    public function isEnabledFor(string $postType): bool
    {
        /** @var list<string> $types */
        $types = apply_filters('tesserae/post_types', ['page', 'post']);

        return \in_array($postType, $types, true);
    }

    /**
     * Bots (search crawlers and AI agents alike) get the `_robot` templates when
     * a block ships one.
     */
    public function isRobot(): bool
    {
        if (null !== $this->robot) {
            return $this->robot;
        }

        if ('robot' === $this->mode()) {
            return $this->robot = true;
        }

        $agent = isset($_SERVER['HTTP_USER_AGENT']) && \is_string($_SERVER['HTTP_USER_AGENT'])
            ? strtolower(wp_unslash($_SERVER['HTTP_USER_AGENT']))
            : '';

        $needles = apply_filters('tesserae/robot_agents', [
            'bot', 'crawler', 'spider', 'crawling', 'facebookexternalhit', 'slurp',
            'gptbot', 'claudebot', 'claude-web', 'anthropic-ai', 'perplexitybot',
            'ccbot', 'google-extended', 'applebot', 'bytespider', 'chatgpt-user',
        ]);

        $robot = false;

        foreach (Arr::wrap($needles) as $needle) {
            $needle = strtolower(Arr::toString($needle));

            if ('' !== $needle && '' !== $agent && str_contains($agent, $needle)) {
                $robot = true;

                break;
            }
        }

        return $this->robot = (bool) apply_filters('tesserae/is_robot', $robot, $agent);
    }

    public function editUrl(int $postId): string
    {
        return add_query_arg(self::QUERY_VAR, 'edit', (string) get_permalink($postId));
    }

    public function viewUrl(int $postId): string
    {
        return remove_query_arg(self::QUERY_VAR, (string) get_permalink($postId));
    }
}
