<?php

namespace Sintattica\Atk\Attributes;

use Sintattica\Atk\Core\Tools;

/**
 * The atkFormatAttribute can be used to edit a formatted string.
 *
 * @author Ivo Jansch <ivo@achievo.org>
 */
class FormatAttribute extends Attribute
{
    public $m_format = '';
    public $m_breakdownCached = [];

    /**
     * Constructor.
     *
     * <b>Example:</b>
     *        $this->add(new atkFormatAttribute("license", "AAA/##/##",
     *                                                        self::AF_OBLIGATORY));
     *
     * @todo Support for other types of input, upper/lowercase support,
     *       escape possibility for using literal *'s.
     *       Also, variable length parts would be nice.
     *       Finally, there should be an option if 'AA' accepts 'z' or only
     *       'zz'
     *
     * @param string $name Name of the attribute (unique within a node, and
     *                       corresponds to a field in the database.
     * @param int $flags Flags for the attribute.
     * @param string $format The format specifier. Each character defines
     *                       what type of input is expected.
     *                       Currently supported format characters:
     *                       * - Accept any character
     *                       # - Accept letter or digit
     *                       A - Accept a letter from the alphabet
     *                       9 - Accept a digit
     *                       Any other char is seen as a literal and displayed
     *                       literally as non-editable chars in the editor.
     */
    public function __construct($name, $flags = 0, $format)
    {
        $this->m_format = $format;
        parent::__construct($name, $flags);
        $this->setAttribSize(strlen($format));
    }

    /**
     * Validate if all elements match the format specifier.
     *
     * Called by the framework for validation of a record. Raised an error
     * with triggerError if a value is not valid.
     *
     * @param array $record The record to validate
     * @param string $mode Insert or update mode (ignored by this attribute)
     */
    public function validate(&$record, $mode)
    {
        $value = Tools::atkArrayNvl($record, $this->fieldName());
        $elems = $this->_breakDown();
        $values = $this->_valueBreakDown($value);

        for ($i = 0, $j = 0, $_i = Tools::count($elems); $i < $_i; ++$i) {
            if ($elems[$i]['type'] != '/') {
                if (!$this->_checkString($elems[$i]['type'], $values[$j])) {
                    Tools::triggerError($record, $this->fieldName(), 'err',
                        $this->_formatErrorString(($j + 1), str_repeat($elems[$i]['type'], $elems[$i]['size'])));
                }
                ++$j;
            }
        }
    }

    /**
     * Returns a piece of html code that can be used in a form to edit this
     * attribute's value.
     *
     * @param array $record The record that holds the value for this attribute.
     * @param string $fieldprefix The fieldprefix to put in front of the name
     *                            of any html form element for this attribute.
     * @param string $mode
     *
     * @return string A piece of htmlcode for editing this attribute
     */
    public function edit($record, $fieldprefix, $mode)
    {
        $value = Tools::atkArrayNvl($record, $this->fieldName());
        $elems = $this->_breakDown();
        $values = $this->_valueBreakDown($value);

        $inputs = [];
        $hints = [];
        for ($i = 0, $j = 0, $_i = Tools::count($elems); $i < $_i; ++$i) {
            if ($elems[$i]['type'] == '/') { // literal
                $inputs[] = $elems[$i]['mask'];
            } else { // format
                $inputs[] = $this->_inputField($elems[$i]['size'], $i, $fieldprefix, $values[$j]);
                ++$j;
            }
            $hints[] = $elems[$i]['mask'];
        }

        return implode(' ', $inputs).'  ('.implode(' ', $hints).')';
    }

    /**
     * Determine error message.
     *
     * @param int $pos The position of the element that is not properly
     *                        formatted.
     * @param string $specifier The format that the value should've adhered to.
     *
     * @return string A translated error string.
     */
    public function _formatErrorString($pos, $specifier)
    {
        return sprintf(Tools::atktext('error_format_mismatch', 'atk', $this->m_owner), $pos, $specifier);
    }

    /**
     * Generate an input box for one of the elements.
     *
     * @param int $size The maximum size of the input box.
     * @param int $elemnr The position of the element within the format.
     * @param string $fieldprefix The fieldprefix to put in front of the name
     *                            of the html element.
     * @param string $value The current value.
     *
     * @return string An html input element string.
     */
    public function _inputField($size, $elemnr, $fieldprefix, $value)
    {
        $id = $this->getHtmlId($fieldprefix).'['.$elemnr.']';

        return '<input type="text" name="'.$id.'" id="'.$id.'" size="'.$size.'" maxlength="'.$size.'" value="'.$value.'">';
    }

    /**
     * Check if a string matches the format specifier.
     *
     * @param string $specifier Char indicating the format that the String must
     *                          adhere to. Can be any of #9A*
     * @param string $string The string to check.
     *
     * @return bool True if string matches the specifier, false if not.
     */
    public function _checkString($specifier, $string)
    {
        for ($i = 0, $_i = strlen($string); $i < $_i; ++$i) {
            if (!$this->_checkChar($specifier, $string[$i])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if a char matches the format specifier.
     *
     * @param string $specifier Char indicating the format that the String must
     *                        adhere to. Can be any of #9A*
     * @param string $char The char to check.
     *
     * @return bool True if char matches the specifier, false if not.
     */
    public function _checkChar($specifier, $char)
    {
        switch ($specifier) {
            case '#':
                return is_numeric($char) || (strtoupper($char) >= 'A' && strtoupper($char) <= 'Z');
            case 'A':
                return strtoupper($char) >= 'A' && strtoupper($char) <= 'Z';
            case '9':
                return is_numeric($char);
        }

        return true;
    }

    /**
     * Return an array containing a structural breakdown of the format string.
     *
     * @return array Structural breakdown of the format string. Each element
     *               contains 3 fields: 'size' -> size of the element
     *               'type' -> 9: numeric
     *               A: alfabetic
     *               #: alfanumeric
     *               *: any char
     *               /: literal
     *               'mask' -> the complete mask, or the
     *               literal string if type is /
     */
    public function _breakDown()
    {
        if (Tools::count($this->m_breakdownCached) == 0) {
            $elems = [];
            $elem = [];
            $last = '';

            for ($i = 0, $_i = strlen($this->m_format); $i < $_i; ++$i) {
                $char = $this->m_format[$i];
                if ($i == 0 || !$this->_equalSpecifiers($char, $last)) {
                    // add elem
                    if ($i > 0) {
                        $elems[] = $elem;
                    }

                    // create new
                    $elem = array(
                        'size' => 1,
                        'type' => ($this->_isSpecifier($char) ? $char : '/'),
                        'mask' => $char,
                    );
                } else {
                    // increase elem
                    ++$elem['size'];
                    $elem['mask'] .= $char;
                }
                $last = $char;
            }

            // leftover
            if ($elem['size'] > 0) {
                $elems[] = $elem;
            }

            $this->m_breakdownCached = $elems;
        }

        return $this->m_breakdownCached;
    }

    /**
     * Converts a string value into a structural breakdown into
     * elements of the format string.
     *
     * @param string $valuestr The value to convert
     *
     * @return array Array containing all values.
     */
    public function _valueBreakDown($valuestr)
    {
        $elems = $this->_breakDown();
        $values = [];
        $pos = 0;

        foreach ($elems as $elem) {
            if ($elem['type'] != '/') {
                $values[] = trim(substr($valuestr, $pos, $elem['size']));
            }
            $pos += $elem['size'];
        }

        return $values;
    }

    /**
     * Check if 2 specifiers are logically equal (i.e. they can be grouped
     * into one element).
     *
     * @param string $charA The first specifier.
     * @param string $charB The second specifier.
     *
     * @return bool True if the specifiers are considered equal, false if
     *              not.
     */
    public function _equalSpecifiers($charA, $charB)
    {
        return (!$this->_isSpecifier($charA) && !$this->_isSpecifier($charB)) || $charA == $charB;
    }

    /**
     * Check if a character is a formatspecifier or a literal.
     *
     * @param string $char The character to check.
     *
     * @return bool True if $char is a valid formatspecifier, false if it
     *              is a literal.
     */
    public function _isSpecifier($char)
    {
        return $char != '' && (strpos('#9A*', $char) !== false);
    }

    /**
     * Convert values from an HTML form posting to an internal value for
     * this attribute.
     *
     * @param array $postvars The array with html posted values ($_POST, for
     *                        example) that holds this attribute's value.
     *
     * @return string The internal value
     */
    public function fetchValue($postvars)
    {
        $masks = $this->_breakDown();
        $elems = isset($postvars[$this->fieldName()])?$postvars[$this->fieldName()]:null;
        $result = '';

        for ($i = 0, $_i = Tools::count($masks); $i < $_i; ++$i) {
            if ($masks[$i]['type'] == '/') { // literal
                $result .= $masks[$i]['mask'];
            } else { // mask
                $result .= $this->_pad($masks[$i]['type'], $masks[$i]['size'], $elems[$i]);
            }
        }

        return $result;
    }

    /**
     * Pad a value according to its specifier.
     *
     * @todo specifier type is not yet used.
     *
     * @param string $type The specifier (9#A*)
     * @param int $size The desired size of the value.
     * @param string $value The value to pad.
     *
     * @return string The padded value.
     */
    public function _pad($type, $size, $value)
    {
        return str_pad($value, $size);
    }

    /**
     * Check if a record has an empty value for this attribute.
     *
     * The record is considered 'empty' if none of the non-literal elements
     * of the format specifier have been filled in. (literals are ignored.)
     *
     * @param array $record The record that holds this attribute's value.
     *
     * @return bool
     */
    public function isEmpty($record)
    {
        // value is empty if all non-literals have not been filled in.
        $values = $this->_valueBreakDown($record[$this->fieldName()]);
        $elems = $this->_breakDown();
        for ($i = 0, $_i = Tools::count($elems); $i < $_i; ++$i) {
            if ($elems[$i]['type'] != '/') { // not a literal
                if (isset($values[$i]) && $values[$i] != '') {
                    return false;
                }
            }
        }

        return true;
    }
}
