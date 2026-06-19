<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\FrontMatter\FrontMatterExtension;
use League\CommonMark\Extension\FrontMatter\Output\RenderedContentWithFrontMatter;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\MarkdownConverter;
use Symfony\Component\Yaml\Yaml;

/**
 * In-app handbook backed by markdown files in docs/handbook/.
 *
 * Each .md file has YAML frontmatter:
 *
 *   ---
 *   title: Creating a booking
 *   section: Bookings
 *   order: 2
 *   ---
 *
 * The slug is derived from the file path relative to docs/handbook/,
 * stripped of .md (e.g. "bookings/creating-a-booking").
 */
class Handbook
{
    protected MarkdownConverter $converter;

    /**
     * Slug -> video metadata, loaded once from docs/handbook/_videos.yml.
     *
     * @var array<string, array{youtube_id:?string,title:?string,duration_seconds:?int}>|null
     */
    protected ?array $videos = null;

    public function __construct()
    {
        $environment = new Environment([
            'heading_permalink' => [
                'symbol' => '#',
                'min_heading_level' => 2,
                'max_heading_level' => 3,
                'aria_hidden' => true,
                'html_class' => 'heading-anchor opacity-0 group-hover:opacity-60 ml-2 text-sm no-underline',
            ],
        ]);

        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new GithubFlavoredMarkdownExtension);
        $environment->addExtension(new FrontMatterExtension);
        $environment->addExtension(new HeadingPermalinkExtension);

        $this->converter = new MarkdownConverter($environment);
    }

    /**
     * Return every handbook document, parsed and sorted by section then order.
     *
     * @return Collection<int, array{slug:string,title:string,section:string,order:int,html:string,toc:list<array{depth:int,text:string,id:string}>}>
     */
    public function all(): Collection
    {
        $root = $this->root();

        if (! File::isDirectory($root)) {
            return collect();
        }

        $docs = collect(File::allFiles($root))
            ->filter(fn ($f) => $f->getExtension() === 'md')
            ->map(fn ($f) => $this->parseFile($f->getRealPath(), $root))
            ->filter();

        // section order = lowest `order` of any doc in it (no separate field)
        $sectionOrder = $docs->groupBy('section')
            ->map(fn ($g) => $g->min('order'))
            ->all();

        return $docs
            ->sortBy(fn ($d) => [
                $sectionOrder[$d['section']] ?? 999,
                $d['section'],
                $d['order'],
                $d['title'],
            ])
            ->values();
    }

    public function find(string $slug): ?array
    {
        return $this->all()->firstWhere('slug', $slug);
    }

    /**
     * Group docs by section for the sidebar nav.
     *
     * @return Collection<int, array{section:string,docs:list<array{slug:string,title:string,order:int}>}>
     */
    public function navTree(): Collection
    {
        return $this->all()
            ->groupBy('section')
            ->map(fn (Collection $docs, string $section) => [
                'section' => $section,
                'docs' => $docs->map(fn ($d) => [
                    'slug' => $d['slug'],
                    'title' => $d['title'],
                    'order' => $d['order'],
                ])->values()->all(),
            ])
            ->values();
    }

    protected function parseFile(string $path, string $root): ?array
    {
        $raw = File::get($path);
        $raw = $this->expandVideoBlocks($raw);
        $rendered = $this->converter->convert($raw);

        $frontMatter = $rendered instanceof RenderedContentWithFrontMatter
            ? (array) $rendered->getFrontMatter()
            : [];

        $relativePath = ltrim(str_replace($root, '', $path), DIRECTORY_SEPARATOR);
        $slug = Str::beforeLast($relativePath, '.md');
        $slug = str_replace(DIRECTORY_SEPARATOR, '/', $slug);

        $html = $rendered->getContent();

        return [
            'slug' => $slug,
            'title' => $frontMatter['title'] ?? Str::headline($slug),
            'section' => $frontMatter['section'] ?? 'General',
            'order' => (int) ($frontMatter['order'] ?? 999),
            'html' => $html,
            'toc' => $this->extractToc($html),
        ];
    }

    /**
     * Pull H2/H3 headings out of the rendered HTML into a flat TOC list.
     *
     * @return list<array{depth:int,text:string,id:string}>
     */
    protected function extractToc(string $html): array
    {
        if (! preg_match_all('/<h([23])[^>]*id="([^"]+)"[^>]*>(.*?)<\/h\1>/i', $html, $matches, PREG_SET_ORDER)) {
            return [];
        }

        return array_map(fn ($m) => [
            'depth' => (int) $m[1],
            'id' => $m[2],
            'text' => trim(strip_tags($m[3])),
        ], $matches);
    }

    protected function root(): string
    {
        return base_path('docs/handbook');
    }

    /**
     * Replace `:::video <slug>` lines with a placeholder div that HandbookVideo
     * hydrates client-side. CommonMark passes the div through as a raw HTML
     * block. Unknown slug or null youtube_id still renders (React shows a tile).
     */
    protected function expandVideoBlocks(string $raw): string
    {
        return preg_replace_callback(
            '/^:::video[ \t]+(\S+)[ \t]*$/m',
            function (array $m): string {
                $slug = $m[1];
                $video = $this->videos()[$slug] ?? null;

                $attrs = [
                    'data-handbook-video' => $slug,
                    'data-youtube-id' => $video['youtube_id'] ?? '',
                    'data-title' => $video['title'] ?? '',
                    'data-duration' => (string) ($video['duration_seconds'] ?? 0),
                ];

                $attrString = collect($attrs)
                    ->map(fn ($v, $k) => sprintf('%s="%s"', $k, htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')))
                    ->implode(' ');

                return "<div {$attrString}></div>";
            },
            $raw,
        );
    }

    /**
     * Lazy-load the video lookup map from docs/handbook/_videos.yml.
     *
     * @return array<string, array{youtube_id:?string,title:?string,duration_seconds:?int}>
     */
    protected function videos(): array
    {
        if ($this->videos !== null) {
            return $this->videos;
        }

        $path = $this->root().DIRECTORY_SEPARATOR.'_videos.yml';

        if (! File::exists($path)) {
            return $this->videos = [];
        }

        $parsed = Yaml::parseFile($path);

        return $this->videos = is_array($parsed) ? $parsed : [];
    }
}
