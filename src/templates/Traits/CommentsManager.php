<?php

namespace Infira\pmg\templates\Traits;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Stringable;

trait CommentsManager
{
    private Collection $comments;

    public function addComment(string $val): static
    {
        $this->comments[] = $this->makeCommentItem($val);

        return $this;
    }

    public function setComment(?string $val): self
    {
        $this->comments = $this->makeCommentsCollection((string)$val);

        return $this;
    }


    public function getComment(): ?string
    {
        if ($this->comments->isEmpty()) {
            return null;
        }

        return $this->comments
            ->sortBy('sort')
            ->map(fn($item) => $item['comment'])
            ->join(PHP_EOL);
    }

    private function makeCommentItem(string|Stringable $comment): array
    {
        $comment = Str::of($comment)->trim();
        $sort = 9999;
        if (!$comment->is('@*')) { //description without any @ sign
            $sort = 0;
        }
        elseif ($comment->is('@generated*')) {
            $sort = 2;
        }
        elseif ($comment->is('@param*')) {
            $sort = 3;
        }
        elseif ($comment->is('@return*')) {
            $sort = 4;
        }

        return ['comment' => $comment, 'sort' => $sort];
    }

    public function makeCommentsCollection(?string $comment): Collection
    {
        return $this->comments = Str::of((string)$comment)
            ->explode(PHP_EOL)
            ->map(fn($comment) => $this->makeCommentItem($comment));
    }

    public function setAsGenerated(): void
    {
        $this->addComment('@generated');
    }

    public function isGenerated(): bool
    {
        return $this->comments->contains(static fn($item) => $item['comment']->is('@generated*'));
    }
}