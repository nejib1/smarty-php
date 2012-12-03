<?php

ini_set('pcre.backtrack_limit', -1);

/**
 * Smarty Code generator
 *
 * @package Smarty
 * @subpackage Compiler
 * @author Uwe Tews
 */

/**
 * Smarty Code generator
 *
 * Methods to manage code output buffer
 *
 * @package Smarty
 * @subpackage Compiler
 */
class Smarty_Internal_Code {

    public $buffer = '';
    public $indentation = 0;
    public $saved_indentation = 0;
    public $indent_on = true;
    public $no_indent = false;

    /**
     * Constructor
     *
     * @param int indentation
     */
    public function __construct($indentation = 0) {
        $this->indentation = $indentation;
    }

    /**
     * inits tag code block.
     *
     * @param object $compiler compiler object
     *
     * @return Instance The current  instance
     */
    public function iniTagCode($compiler) {
        $this->buffer = '';
        $this->indentation = $this->saved_indentation = $compiler->indentation;
        $this->no_indent = !$compiler->suppressNocacheProcessing && $compiler->template->caching && ($compiler->nocache || $compiler->tag_nocache || $compiler->forceNocache);
        return $this;
    }

    /**
     * return tag code.
     *     *
     * @return string of compiled code
     */
    public function returnTagCode($compiler) {
        $_output = $this->buffer;
        $this->buffer = '';
        $compiler->indentation = $this->indentation;
        $compiler->saved_indentation = $this->saved_indentation;
        return $_output;
    }

    /**
     * Enable indentation
     *
     *
     * @return Instance The current  instance

     */
    public function indent_on() {
        $this->indent_on = true;
        return $this;
    }

    /**
     * Enable indentation
     *
     *
     * @return Instance The current  instance

     */
    public function indent_off() {
        $this->indent_on = false;
        return $this;
    }

    /**
     * Adds a raw string to the compiled code.
     *
     * @param string $string The string
     *
     * @return Instance The current  instance
     */
    public function raw($string) {
        $this->buffer .= $string;

        return $this;
    }

    /**
     * Add an indentation to the current buffer.
     *
     * @return Instance The current  instance

     */
    public function addIndentation() {
        if ($this->indent_on && !$this->no_indent) {
            $this->buffer .= str_repeat(' ', $this->indentation * 4);
        }
        return $this;
    }

    /**
     * Add newline to the current buffer.
     *
     * @return Instance The current  instance

     */
    public function newline() {
        if (!$this->no_indent) {
            $this->buffer .= "\n";
        }
        return $this;
    }

    /**
     * Add a line of PHP code to output.
     *
     * @param string $string The string
     *
     * @return Instance The current  instance
     */
    public function php($value) {
        $this->addIndentation();
        $this->buffer .= $value;
        return $this;
    }

    /**
     * Adds a quoted string to the compiled code.
     *
     * @param string $value The string
     *
     * @return Instance The current  instance

     */
    public function string($value) {
        $length = strlen($value);
        if ($length <= 1000) {
            $this->buffer .= sprintf('"%s"', addcslashes($value, "\0\n\r\t\"\$\\"));
        } else {
            $i = 0;
            while (true) {
                $this->buffer .= sprintf('"%s"', addcslashes(substr($value, $i, 1000), "\0\n\r\t\"\$\\"));
                if ($i == 0) {
                    $this->indent();
                }
                $i += 1000;
                if ($i >= $length) {
                    $this->outdent();
                    break;
                }
                $this->raw("\n")->addIndentation()->raw(', ');
            }
        }
        return $this;
    }

    /**
     * Adds the PHP representation of a given value to the current buffer
     *
     * @param mixed $value The value to convert
     *
     * @return Instance The current  instance

     */
    public function repr($value) {
        if (is_int($value) || is_float($value)) {
            if (false !== $locale = setlocale(LC_NUMERIC, 0)) {
                setlocale(LC_NUMERIC, 'C');
            }

            $this->raw($value);

            if (false !== $locale) {
                setlocale(LC_NUMERIC, $locale);
            }
        } elseif (null === $value) {
            $this->raw('null');
        } elseif (is_bool($value)) {
            $this->raw($value ? 'true' : 'false');
        } elseif (is_array($value)) {
            $this->raw("array(\n")->indent(2)->addIndentation();
            $i = 0;
            foreach ($value as $key => $value) {
                if ($i++) {
                    $this->raw(",\n")->addIndentation();
                }
                $this->repr($key);
                $this->raw(' => ');
                $this->repr($value);
            }
            $this->outdent()->raw("\n")->addIndentation()->raw(')')->outdent();
        } else {
            $this->string($value);
        }
        return $this;
    }

    /**
     * Indents the generated code.
     *
     * @param integer $step The number of indentation to add
     *
     * @return Instance The current  instance

     */
    public function indent($step = 1) {
        $this->indentation += $step;

        return $this;
    }

    /**
     * Outdents the generated code.
     *
     * @param integer $step The number of indentation to remove
     *
     * @return Instance The current  instance

     */
    public function outdent($step = 1) {
        // can't outdent by more steps that the current indentation level
        if ($this->indentation < $step) {
            throw new SmartyException('Unable to call outdent() as the indentation would become negative');
        }
        $this->indentation -= $step;
        return $this;
    }

    /**
     * Format and add aPHP code block to current buffer.
     *
     * @param string $string The string
     *
     * @return Instance The current  instance
     */
    public function formatPHP($value) {
        $save = $this->indent_on;
        $this->indent_on = true;
        preg_replace_callback('%(\'[^\'\\\\]*(?:\\\\.[^\'\\\\]*)*\'|"[^"\\\\]*(?:\\\\.[^"\\\\]*)*")|([\r\n\t ]*(\?>|<\?php)[\r\n\t ]*)|(;[\r\n\t ]*)|({[\r\n\t ]*)|([\r\n\t ]*}[\r\n\t ]*)|([\r\n\t ]*)|([\r\n\t ]*/\*(.*)?\*/[\r\n\t ]*)|(.*?(?=[\'";{}/\n]))%', array($this, '_processPHPoutput'), $value);
        $this->buffer .= "\n";
        $this->indent_on = $save;
        return $this;
    }

    /**
     * preg_replace callback function to process PHP output
     *
     * @param string $match match string
     * @return string  replacemant
     */
    function _processPHPoutput($match) {
        if (empty($match[0]) || !empty($match[2])) {
            return;
        }
        if ($this->indent_on) {
            $this->raw("\n");
        }
        if (!empty($match[7])) {
            return;
        }
        if (!empty($match[1])) {
            $this->raw($match[1]);
            return;
        }
        if (!empty($match[4])) {
            $this->raw(";");
            $this->indent_on = true;
            return;
        }
        if (!empty($match[5])) {
            $this->raw("{")->indent();
            $this->indent_on = true;
            return;
        }
        if (!empty($match[6])) {
            if ($this->indent_on) {
                $this->raw("\n");
                $this->indent_on = true;
            }
            $this->outdent()->addIndentation()->raw('}');
            return;
        }
        if (!empty($match[9])) {
            $this->addIndentation()->raw("/*{$match[9]}*/");
            return;
        }
        if (!empty($match[10])) {
            if ($this->indent_on) {
                $this->addIndentation();
            }
            $this->raw($match[10]);
            $this->indent_on = false;
            return;
        }
        return;
    }
}

