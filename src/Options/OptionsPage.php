<?php

declare(strict_types=1);

namespace Tesserae\Options;

use Tesserae\Fields\FieldCollection;
use Tesserae\Support\Arr;
use Tesserae\Support\Yaml;
use Tesserae\Support\YamlException;

/**
 * One options page, as described by `option-pages/<slug>.yaml`.
 *
 * Unlike a block, an options page has no template and no per-instance
 * placement — it is one flat set of fields, edited in a single dialog and
 * stored as one row in `wp_options`.
 */
final class OptionsPage
{
    private ?FieldCollection $fields = null;

    /**
     * @param array<string, mixed> $config
     */
    private function __construct(
        public readonly string $slug,
        private readonly array $config,
    ) {}

    /**
     * @throws YamlException when the YAML file is unreadable or malformed
     */
    public static function fromFile(string $path): ?self
    {
        $slug = sanitize_key(pathinfo($path, PATHINFO_FILENAME));

        if ('' === $slug) {
            return null;
        }

        $config = Yaml::parseFile($path);

        if (!\is_array($config)) {
            throw new YamlException(\sprintf('Options page "%s" has an empty or invalid config file.', $slug));
        }

        return new self($slug, Arr::toMap($config));
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function fromArray(string $slug, array $config): self
    {
        return new self($slug, $config);
    }

    public function label(): string
    {
        $label = Arr::toString($this->config['label'] ?? $this->config['title'] ?? '');

        return '' !== $label ? $label : ucwords(str_replace(['_', '-'], ' ', $this->slug));
    }

    public function description(): string
    {
        return trim(Arr::toString($this->config['description'] ?? ''));
    }

    /**
     * The capability required to view and save this page. Checked both for
     * the admin bar link and the REST save route.
     */
    public function capability(): string
    {
        $capability = Arr::toString($this->config['capability'] ?? '');

        if ('' !== $capability) {
            return $capability;
        }

        /*
         * Filter the capability an options page falls back to when it does
         * not declare one of its own.
         *
         * @param string $capability
         */
        return Arr::toString(apply_filters('tesserae/options_default_capability', 'manage_options'), 'manage_options');
    }

    public function fields(): FieldCollection
    {
        return $this->fields ??= FieldCollection::fromConfig($this->config['fields'] ?? []);
    }

    /**
     * @return array<string, mixed>
     */
    public function config(): array
    {
        return $this->config;
    }
}
