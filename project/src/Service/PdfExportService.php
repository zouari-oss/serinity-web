<?php

namespace App\Service;

use App\Entity\ForumThread;
use App\Entity\Reply;

class PdfExportService
{
    public function __construct()
    {
    }

    /**
     * @param Reply[] $replies
     */
    public function exportThreadHtml(ForumThread $thread, array $replies): string
    {
        $rows = '';
        foreach ($replies as $reply) {
            $rows .= sprintf('<li><strong>%s</strong> - %s</li>', htmlspecialchars((string) ($reply->getAuthorUsername() ?? 'Unknown User')), nl2br(htmlspecialchars((string) $reply->getContent())));
        }

        return sprintf(
            '<h1>%s</h1><p>%s</p><h3>Replies</h3><ul>%s</ul>',
            htmlspecialchars((string) $thread->getTitle()),
            nl2br(htmlspecialchars((string) $thread->getContent())),
            $rows
        );
    }
}
