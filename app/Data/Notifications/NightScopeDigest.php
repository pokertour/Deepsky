<?php

namespace App\Data\Notifications;

final readonly class NightScopeDigest
{
    /**
     * @param  list<string>  $lines
     */
    public function __construct(
        public string $title,
        public array $lines,
        public string $dedupeKey,
        public string $ntfyTags = 'telescope,stars',
    ) {}

    public function body(): string
    {
        return html_entity_decode(strip_tags($this->htmlBody()), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    public function htmlBody(): string
    {
        return implode("\n", $this->lines);
    }

    public function telegramHtml(): string
    {
        return '<b>'.e($this->title).'</b>'."\n\n".$this->htmlBody();
    }
}
