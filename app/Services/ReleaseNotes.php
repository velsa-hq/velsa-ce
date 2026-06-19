<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\FrontMatter\FrontMatterExtension;
use League\CommonMark\Extension\FrontMatter\Output\RenderedContentWithFrontMatter;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;

/**
 * In-app "What's New" release notes, one markdown file per release in
 * docs/whats-new/ with YAML frontmatter (version, date, title, summary).
 * Returned newest-first by date.
 */
class ReleaseNotes
{
    protected MarkdownConverter $converter;

    public function __construct()
    {
        $environment = new Environment;
        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new GithubFlavoredMarkdownExtension);
        $environment->addExtension(new FrontMatterExtension);

        $this->converter = new MarkdownConverter($environment);
    }

    /**
     * @return Collection<int, array{version:string,date:string,title:string,summary:string,html:string}>
     */
    public function all(): Collection
    {
        $root = $this->root();

        if (! File::isDirectory($root)) {
            return collect();
        }

        return collect(File::files($root))
            ->filter(fn ($f) => $f->getExtension() === 'md')
            ->map(fn ($f) => $this->parseFile($f->getRealPath()))
            ->filter()
            ->sortByDesc('date')
            ->values();
    }

    /**
     * Most recent release's publish date, or null. Drives the unread indicator.
     */
    public function latestDate(): ?CarbonImmutable
    {
        $latest = $this->all()->first();

        return $latest ? CarbonImmutable::parse($latest['date']) : null;
    }

    /**
     * Whether a release was published after $seenAt (or any release if null).
     * Accepts a raw Eloquent datetime attribute without pre-coercion.
     */
    public function hasUnreadSince(\DateTimeInterface|string|null $seenAt): bool
    {
        $latest = $this->latestDate();

        if ($latest === null) {
            return false;
        }

        if ($seenAt === null) {
            return true;
        }

        return $latest->gt(CarbonImmutable::parse($seenAt));
    }

    /**
     * @return array{version:string,date:string,title:string,summary:string,html:string}|null
     */
    protected function parseFile(string $path): ?array
    {
        $rendered = $this->converter->convert(File::get($path));

        $frontMatter = $rendered instanceof RenderedContentWithFrontMatter
            ? (array) $rendered->getFrontMatter()
            : [];

        if (! isset($frontMatter['version'], $frontMatter['date'])) {
            return null;
        }

        $date = $this->parseDate($frontMatter['date']);
        if ($date === null) {
            return null;
        }

        return [
            'version' => (string) $frontMatter['version'],
            'date' => $date->toDateString(),
            'title' => (string) ($frontMatter['title'] ?? 'Release '.$frontMatter['version']),
            'summary' => (string) ($frontMatter['summary'] ?? ''),
            'html' => $rendered->getContent(),
        ];
    }

    /**
     * Normalize a frontmatter date; the YAML parser may return an int
     * timestamp, a DateTime, or a string.
     */
    protected function parseDate(mixed $value): ?CarbonImmutable
    {
        return match (true) {
            $value instanceof \DateTimeInterface => CarbonImmutable::instance($value),
            is_int($value), is_numeric($value) => CarbonImmutable::createFromTimestamp((int) $value),
            is_string($value) => CarbonImmutable::parse($value),
            default => null,
        };
    }

    protected function root(): string
    {
        return base_path('docs/whats-new');
    }
}
