<?php

declare(strict_types=1);

namespace Tesserae\Fields;

use Tesserae\Fields\Types\CheckboxField;
use Tesserae\Fields\Types\ColorField;
use Tesserae\Fields\Types\FileField;
use Tesserae\Fields\Types\GalleryField;
use Tesserae\Fields\Types\GroupField;
use Tesserae\Fields\Types\ImageField;
use Tesserae\Fields\Types\LinkField;
use Tesserae\Fields\Types\MessageField;
use Tesserae\Fields\Types\NumberField;
use Tesserae\Fields\Types\PostsField;
use Tesserae\Fields\Types\RadioField;
use Tesserae\Fields\Types\RepeaterField;
use Tesserae\Fields\Types\RichTextField;
use Tesserae\Fields\Types\SelectField;
use Tesserae\Fields\Types\TabField;
use Tesserae\Fields\Types\TermsField;
use Tesserae\Fields\Types\TextareaField;
use Tesserae\Fields\Types\TextField;
use Tesserae\Fields\Types\ToggleField;

final class FieldRegistry
{
    /**
     * Aliases keep the YAML readable — `type: wysiwyg` and `type: richtext`
     * should not be two different answers to the same question.
     */
    private const ALIASES = [
        'string' => 'text',
        'url' => 'text',
        'email' => 'text',
        'wysiwyg' => 'richtext',
        'rich_text' => 'richtext',
        'editor' => 'richtext',
        'boolean' => 'toggle',
        'true_false' => 'toggle',
        'switch' => 'toggle',
        'int' => 'number',
        'integer' => 'number',
        'dropdown' => 'select',
        'radio_buttons' => 'radio',
        'multi_select' => 'checkbox',
        'picture' => 'image',
        'images' => 'gallery',
        'post' => 'posts',
        'post_object' => 'posts',
        'relationship' => 'posts',
        'taxonomy' => 'terms',
        'flexible' => 'repeater',
        'list' => 'repeater',
        'object' => 'group',
        'fieldset' => 'group',
        'notice' => 'message',
    ];

    /** @var null|array<string, class-string<Field>> */
    private static ?array $types = null;

    /**
     * @return array<string, class-string<Field>>
     */
    public static function types(): array
    {
        if (null !== self::$types) {
            return self::$types;
        }

        /** @var array<string, class-string<Field>> $types */
        $types = [
            TextField::type() => TextField::class,
            TextareaField::type() => TextareaField::class,
            RichTextField::type() => RichTextField::class,
            NumberField::type() => NumberField::class,
            ToggleField::type() => ToggleField::class,
            SelectField::type() => SelectField::class,
            RadioField::type() => RadioField::class,
            CheckboxField::type() => CheckboxField::class,
            ColorField::type() => ColorField::class,
            LinkField::type() => LinkField::class,
            ImageField::type() => ImageField::class,
            GalleryField::type() => GalleryField::class,
            FileField::type() => FileField::class,
            PostsField::type() => PostsField::class,
            TermsField::type() => TermsField::class,
            GroupField::type() => GroupField::class,
            RepeaterField::type() => RepeaterField::class,
            TabField::type() => TabField::class,
            MessageField::type() => MessageField::class,
        ];

        /**
         * Register custom field types.
         *
         * @param array<string, class-string<Field>> $types
         */
        $filtered = apply_filters('tesserae/field_types', $types);

        return self::$types = $filtered;
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function make(array $config): ?Field
    {
        $type = strtolower(\is_string($config['type'] ?? null) ? $config['type'] : 'text');
        $type = self::ALIASES[$type] ?? $type;
        $types = self::types();

        if (!isset($types[$type])) {
            _doing_it_wrong(__METHOD__, \sprintf('Unknown Tesserae field type "%s".', esc_html($type)), '0.1.0');

            return null;
        }

        $class = $types[$type];

        return new $class($config);
    }
}
