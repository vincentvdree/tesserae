<?php

declare(strict_types=1);

namespace Tesserae\Blocks;

use Tesserae\Fields\FieldCollection;
use Tesserae\Support\Arr;
use Tesserae\Support\Yaml;
use Tesserae\Support\YamlException;

/**
 * One block, as described by `blocks/<name>/<name>.yaml` and the files sitting
 * next to it.
 */
final class BlockDefinition
{
    private ?FieldCollection $fields = null;

    /**
     * @param array<string, mixed> $config
     */
    private function __construct(
        public readonly string $type,
        public readonly string $directory,
        public readonly string $url,
        private readonly array $config,
    ) {}

    /**
     * @throws YamlException when the YAML file is unreadable or malformed
     */
    public static function fromDirectory(string $directory, string $url): ?self
    {
        $type = sanitize_key(basename($directory));

        if ('' === $type) {
            return null;
        }

        $yaml = $directory.'/'.basename($directory).'.yaml';

        if (!is_file($yaml)) {
            $yaml = $directory.'/'.basename($directory).'.yml';
        }

        if (!is_file($yaml)) {
            return null;
        }

        $config = Yaml::parseFile($yaml);

        if (!\is_array($config)) {
            throw new YamlException(\sprintf('Block "%s" has an empty or invalid config file.', $type));
        }

        return new self($type, $directory, rtrim($url, '/'), Arr::toMap($config));
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function fromArray(string $type, string $directory, string $url, array $config): self
    {
        return new self($type, $directory, rtrim($url, '/'), $config);
    }

    public function label(): string
    {
        $label = Arr::toString($this->config['label'] ?? $this->config['title'] ?? $this->config['name'] ?? '');

        return '' !== $label ? $label : ucwords(str_replace(['_', '-'], ' ', $this->type));
    }

    public function description(): string
    {
        return trim(Arr::toString($this->config['description'] ?? ''));
    }

    public function icon(): string
    {
        return Arr::toString($this->config['icon'] ?? '◻');
    }

    public function category(): string
    {
        return Arr::toString($this->config['category'] ?? 'content');
    }

    /**
     * @return list<string>
     */
    public function keywords(): array
    {
        return array_map(
            static fn (mixed $keyword): string => Arr::toString($keyword),
            Arr::wrap($this->config['keywords'] ?? []),
        );
    }

    public function fields(): FieldCollection
    {
        return $this->fields ??= FieldCollection::fromConfig($this->config['fields'] ?? []);
    }

    public function supports(string $feature, bool $default = false): bool
    {
        $supports = Arr::toArray($this->config['supports'] ?? []);

        return Arr::toBool($supports[$feature] ?? $default, $default);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return Arr::toMap($this->config['rules'] ?? $this->config['conditions'] ?? []);
    }

    /**
     * @return array<string, mixed>
     */
    public function config(): array
    {
        return $this->config;
    }

    public function templateFor(string $variant = ''): ?string
    {
        $suffix = '' === $variant ? '' : '_'.$variant;
        $path = \sprintf('%s/%s%s.php', $this->directory, $this->type, $suffix);

        return is_file($path) ? $path : null;
    }

    /**
     * A plain JS file sitting next to the template, loaded as a native ES
     * module whenever the block is on the page. Nothing about it is assumed
     * — if it wants a Stimulus controller, it registers one itself against
     * `window.Tesserae.application`.
     */
    public function scriptUrl(): ?string
    {
        $file = \sprintf('%s/%s.js', $this->directory, $this->type);

        return is_file($file)
            ? \sprintf('%s/%s.js?v=%d', $this->url, $this->type, (int) filemtime($file))
            : null;
    }

    public function styleUrl(): ?string
    {
        $file = \sprintf('%s/%s.css', $this->directory, $this->type);

        return is_file($file) ? \sprintf('%s/%s.css', $this->url, $this->type) : null;
    }

    public function styleVersion(): string
    {
        $file = \sprintf('%s/%s.css', $this->directory, $this->type);

        return is_file($file) ? (string) filemtime($file) : '0';
    }

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return $this->fields()->defaults();
    }

    /**
     * Everything the editor needs to know about this block up front.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'label' => $this->label(),
            'description' => $this->description(),
            'icon' => $this->icon(),
            'category' => $this->category(),
            'keywords' => $this->keywords(),
            'supports' => [
                'preview' => $this->supports('preview', true),
                'anchor' => $this->supports('anchor', false),
                'className' => $this->supports('className', false),
                'wrapper' => $this->supports('wrapper', true),
            ],
            'fields' => $this->fields()->schema(),
            'tabs' => $this->fields()->tabs(),
        ];
    }
}
