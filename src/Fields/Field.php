<?php

namespace Scriptotek\Marc\Fields;

use Scriptotek\Marc\MagicAccess;
use Scriptotek\Marc\Record;

class Field implements \JsonSerializable
{
    use SerializableField, MagicAccess;

    /**
     * @var array List of properties to be included when serializing the record using the `toArray()` method.
     */
    public $properties = [];

    public static $glue = ' : ';
    public static $chopPunctuation = true;

    protected $field;

    public function __construct(\File_MARC_Field $field)
    {
        $this->field = $field;
    }

    public function getField()
    {
        return $this->field;
    }

    public function __call($name, $args)
    {
        return call_user_func_array([$this->field, $name], $args);
    }

    public function __toString()
    {
        return $this->field->__toString();
    }

    protected function clean($value, $options = [])
    {
        $chopPunctuation = isset($options['chopPunctuation']) ? $options['chopPunctuation'] : static::$chopPunctuation;
        $value = trim($value);
        if ($chopPunctuation) {
            $value = rtrim($value, '[.:,;]$');
        }
        return $value;
    }

    /**
     * @param string|string[] $codes
     * @return array
     */
    protected function getSubfieldValues($codes)
    {
        if (!is_array($codes)) {
            $codes = [$codes];
        }
        $parts = [];
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
     * @param array    $options
     * @return string
     */
    protected function toString($codes, $options = [])
    {
        $glue = isset($options['glue']) ? $options['glue'] : static::$glue;
        return $this->clean(implode($glue, $this->getSubfieldValues($codes)), $options);
    }

    /**
     * Return a line MARC representation of the field. If the field is deleted,
     * null is returned.
     *
     * @param string $sep Subfield separator character, defaults to '$'
     * @param string $blank Blank indicator character, defaults to ' '
     * @return string|null.
     */
    public function asLineMarc($sep = '$', $blank = ' ')
    {
        if ($this->field->isEmpty()) {
            return null;
        }
        $subfields = [];
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

        return "${tag} ${ind1}${ind2} " . implode(' ', $subfields);
    }

    /**
     * Return the data value of the *first* subfield with a given code.
     *
     * @param string $code
     * @param mixed $default
     * @return mixed
     */
    public function sf($code, $default = null)
    {

        // In PHP, ("a" == 0) will evaluate to TRUE, so it's actually very important that we ensure type here!
        $code = (string) $code;

        $subfield = $this->getSubfield($code);
        if (!$subfield) {
            return $default;
        }

        return trim($subfield->getData());
    }

    public function mapSubFields($map, $includeNullValues = false)
    {
        $o = [];
        foreach ($map as $code => $prop) {
            $value = $this->sf($code);

            foreach ($this->getSubfields() as $q) {
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

    public static function makeFieldObject(Record $record, $tag, $pcre=false)
    {
        $field = $record->getField($tag, $pcre);

        // Note: `new static()` is a way of creating a new instance of the
        // called class using late static binding.
        return $field ? new static($field->getField()) : null;
    }

    public static function makeFieldObjects(Record $record, $tag, $pcre=false)
    {
        return array_map(function ($field) {

            // Note: `new static()` is a way of creating a new instance of the
            // called class using late static binding.
            return new static($field->getField());
        }, $record->getFields($tag, $pcre));
    }
}
