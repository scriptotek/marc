<?php

namespace Scriptotek\Marc\Fields;

use File_MARC_Field;
use File_MARC_List;
use File_MARC_Subfield;
use JsonSerializable;
use Scriptotek\Marc\MagicAccess;
use Scriptotek\Marc\Record;

/**
 * The MARC field wrapper.
 *
 * We wrap File_MARC_Field rather than extend it, just like in our Record
 * class. File_MARC_Field has two known subclasses, namely File_MARC_Data_Field
 * and File_MARC_Control_Field.
 *
 * Methods on the wrapped field that are not implemented here may be accessed
 * using magic method calls, or through `getField()`. Method tags are included
 * below to aid IDE code hinting, but using the getter will give you better
 * code
 * hinting and documentation.
 *
 * @method string getTag()
 * @method string setTag()
 * @method bool isEmpty()
 * @method bool isControlField()
 * @method bool isDataField()
 * @method string toRaw()
 * @method formatField(array $exclude = array('2'))
 *
 * FIXME: These methods are just implemented in File_MARC_Data_Field and not in
 * File_MARC_Field or File_MARC_Control_Field, so they may not always be
 * available:
 *
 * @method File_MARC_Subfield appendSubfield(File_MARC_Subfield $new_subfield)
 * @method File_MARC_Subfield prependSubfield(File_MARC_Subfield $new_subfield)
 * @method File_MARC_Subfield insertSubfield(File_MARC_Subfield $new_field, File_MARC_Subfield $existing_field, bool $before = false)
 * @method int addSubfields(array $subfields)
 * @method deleteSubfield(File_MARC_Subfield $subfield)
 * @method string getIndicator(int $ind)
 * @method string setIndicator(int $ind, string $value)
 * @method File_MARC_Subfield|false getSubfield(string $code = null, bool $pcre = null)
 * @method File_MARC_List|array getSubfields(string $code = null, bool $pcre = null)
 * @method string getContents(string $joinChar = '')
 *
 * FIXME: These methods are just implemented in File_MARC_Control_Field and not
 * in File_MARC_Field or File_MARC_Data_Field, so they may not always be
 * available:
 *
 * @method string getData()
 * @method bool setData(string $data)
 */
class Field implements JsonSerializable, FieldInterface
{
    use SerializableField;
    use MagicAccess;

    /**
     * @var array List of properties to be included when serializing the record
     *     using the `toArray()` method.
     */
    public array $properties = [];

    /**
     * The characters used to separate values of a subfield.
     *
     * @var string
     */
    public static string $glue = ' : ';

    /**
     * Whether to strip punctuation from the end of some field values.
     *
     * @var bool
     */
    public static bool $chopPunctuation = true;

    /**
     * The wrapped field.
     *
     * @var File_MARC_Field
     */
    protected File_MARC_Field $field;

    /**
     * Field constructor.
     *
     * @param File_MARC_Field $field
     *   The field to wrap.
     */
    public function __construct(File_MARC_Field $field)
    {
        $this->field = $field;
    }

    /**
     * Get the wrapped field.
     *
     * @return File_MARC_Field
     */
    public function getField(): File_MARC_Field
    {
        return $this->field;
    }

    /**
     * Delegate all unknown method calls to the wrapped field.
     *
     * @param string $name
     *   The name of the method being called.
     * @param array $args
     *   The arguments being passed to the method.
     *
     * @return mixed
     */
    public function __call(string $name, array $args)
    {
        return call_user_func_array([$this->field, $name], $args);
    }

    /**
     * Get a string representation of this field.
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->field->__toString();
    }

    /**
     * Remove extra whitespace and punctuation from field values.
     *
     * @param string|null $value
     *   The value to clean.
     * @param array $options
     *   A list of options. Currently only the chopPunctuation key is used.
     *
     * @return string
     */
    protected function clean(string $value = null, array $options = []): string
    {
        if (is_null($value)) {
            return "";
        }
        $chopPunctuation = $options['chopPunctuation'] ?? static::$chopPunctuation;
        $value = trim($value);
        if ($chopPunctuation) {
            $value = rtrim($value, '[.:,;]$');
        }
        return $value;
    }

    /**
     * Extract values from subfields of this field.
     *
     * @param string|string[] $codes
     *   The subfield code or an array of such codes.
     * @return string[]
     *   The values that were contained in the requested subfields.
     */
    public function getSubfieldValues(array|string $codes): array
    {
        if (!is_array($codes)) {
            $codes = [$codes];
        }
        $parts = [];
        /** @var File_MARC_Subfield $sf */
        foreach ($this->field->getSubfields() as $sf) {
            if (in_array($sf->getCode(), $codes)) {
                $parts[] = trim($sf->getData());
            }
        }

        return $parts;
    }

    /**
     * Return concatenated string of the given subfields.
     *
     * @param string[] $codes
     *   The subfield codes to retrieve.
     * @param array $options
     *   Options to pass to the `clean` method.
     * @return string
     *   The concatenated subfield values.
     */
    protected function toString(array $codes, array $options = []): string
    {
        $glue = $options['glue'] ?? static::$glue;
        return $this->clean(implode($glue, $this->getSubfieldValues($codes)), $options);
    }

    /**
     * Get a line MARC representation of the field.
     *
     * @param string $sep
     *   Subfield separator character, defaults to '$'
     * @param string $blank
     *   Blank indicator character, defaults to ' '
     * @return string|null
     *   A line MARC representation of the field or NULL if the field is empty.
     */
    public function asLineMarc(string $sep = '$', string $blank = ' '): ?string
    {
        if ($this->field->isEmpty()) {
            return null;
        }
        $subfields = [];
        /** @var File_MARC_Subfield $sf */
        foreach ($this->field->getSubfields() as $sf) {
            $subfields[] = $sep . $sf->getCode() . ' ' . $sf->getData();
        }
        $tag = $this->field->getTag();
        $ind1 = $this->field->getIndicator(1);
        $ind2 = $this->field->getIndicator(2);
        if ($ind1 == ' ') {
            $ind1 = $blank;
        }
        if ($ind2 == ' ') {
            $ind2 = $blank;
        }

        return "{$tag} {$ind1}{$ind2} " . implode(' ', $subfields);
    }

    /**
     * Return the data value of the *first* subfield with a given code.
     *
     * @param string $code
     *   The subfield identifier.
     * @param string|null $default
     *   The fallback value to return if the subfield does not exist.
     * @return string|null
     */
    public function sf(string $code, string $default = null): ?string
    {
        // In PHP, ("a" == 0) will evaluate to TRUE, so it's actually very important that we ensure type here!
        $code = (string) $code;

        /** @var \File_MARC_Subfield $subfield */
        $subfield = $this->field->getSubfield($code);
        if (!$subfield) {
            return $default;
        }

        return trim($subfield->getData());
    }

    /**
     * TODO: document this function.
     *
     * @param $map
     *   TODO: ?
     * @param bool $includeNullValues
     *   TODO: ?
     *
     * @return array
     *   TODO: ?
     */
    public function mapSubFields(array $map, bool $includeNullValues = false): array
    {
        $o = [];
        foreach ($map as $code => $prop) {
            $value = $this->sf($code);

            /** @var File_MARC_Subfield $q */
            foreach ($this->field->getSubfields() as $q) {
                if ($q->getCode() === $code) {
                    $value = $q->getData();
                }
            }

            if (!is_null($value) || $includeNullValues) {
                $o[$prop] = $value;
            }
        }
        return $o;
    }

    /**
     * TODO: document this function.
     *
     * @param Record $record
     *   TODO: ?
     * @param string $tag
     *   The tag name.
     * @param bool $pcre
     *   If true, match as a regular expression.
     *
     * @return static|null
     *   TODO: ?
     */
    public static function makeFieldObject(Record $record, string $tag, bool $pcre = false): ?static
    {
        $field = $record->getField($tag, $pcre);

        // Note: `new static()` is a way of creating a new instance of the
        // called class using late static binding.
        return $field ? new static($field->getField()) : null;
    }

    /**
     * TODO: document this function.
     *
     * @param Record $record
     *   TODO: ?
     * @param string $tag
     *   The tag name.
     * @param bool $pcre
     *   If true, match as a regular expression.
     *
     * @return static[]
     *   TODO: ?
     */
    public static function makeFieldObjects(Record $record, string $tag, bool $pcre = false): array
    {
        return array_map(function (Field $field) {
            // Note: `new static()` is a way of creating a new instance of the
            // called class using late static binding.
            return new static($field->getField());
        }, $record->getFields($tag, $pcre));
    }
}
