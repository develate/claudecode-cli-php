<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli\Transport;

/**
 * A user message as one stream-json line on Claude Code's stdin.
 *
 * Images are named by path rather than embedded: the model opens them with its
 * own Read tool, which also keeps the line short.
 */
final class UserInput
{
    /** @param list<string> $images */
    public static function line(string $prompt, array $images = [], ?string $uuid = null): string
    {
        $message = ['type' => 'user'];
        if ($uuid !== null) {
            $message['uuid'] = $uuid;
        }
        $message['message'] = ['role' => 'user', 'content' => self::text($prompt, $images)];

        return json_encode($message, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n";
    }

    /** @param list<string> $images */
    private static function text(string $prompt, array $images): string
    {
        if ($images === []) {
            return $prompt;
        }

        return $prompt
            ."\n\nUse the following image files as visual inputs:\n- "
            .implode("\n- ", $images);
    }
}
