<?php
/**
 * @file
 * DataDumpParser.php
 */

namespace Wesnick\FdfUtility\Parser;


use Wesnick\FdfUtility\Fields\ButtonField;
use Wesnick\FdfUtility\Fields\ChoiceField;
use Wesnick\FdfUtility\Fields\TextField;

/**
 * Class DataDumpParser
 *
 * Parses output from pdftk dump_data_fields
 */
class PdftkDumpParser
{

    /**
     * Current index in the contents array.
     * @var int
     */
    private $currentIndex;

    /**
     * Array of file lines in the PDFTK Dump File.
     *
     * @var array
     */
    private $currentContents;


    /**
     * @var \Wesnick\FdfUtility\Fields\PdfField[]
     */
    private $fields;


    function __construct($file)
    {
        $this->currentContents = file($file);
        $this->currentIndex = 0;
    }


    /**
     * Parse PDFTK Form Field Dump
     *
     * @return \Wesnick\FdfUtility\Fields\PdfField[]
     */
    public function parse()
    {

        while ($this->nextBlockIndex()) {
            $currentIndex = $this->currentIndex;
            $nextIndex = $this->getNextBlockIndex();
            $fieldValues = $this->processFieldBlock($currentIndex, $nextIndex);
            if ($field = $this->createFieldFromPdftkDump($fieldValues)) {
                $this->fields[] = $field;
            }
        }

        return $this->fields;
    }


    /**
     * Process a Field Element.
     *
     * @param int $start
     * @param int $stop
     * @return array
     */
    private function processFieldBlock($start, $stop)
    {
        $itemValues = array();

        for ($x = $start; $x < $stop; $x++) {

            if (false === strpos($this->currentContents[$x], ":")) {
                continue;
            }

            list($index, $value) = array_map('trim', explode(":", $this->currentContents[$x]));

            // Options are an array
            if ('FieldStateOption' === $index) {
                $itemValues[$index][] = $value;
            }
            else {
                $itemValues[$index] = $value;
            }
        }

        return $itemValues;
    }

    /**
     * Advance the index pointer to the next block.
     *
     * @return bool
     */
    private function nextBlockIndex()
    {
        while ($this->currentIndex < count($this->currentContents) - 1) {
            if (substr($this->currentContents[$this->currentIndex++], 0, 3) === "---") {
                return true;
            }
        }

        return false;
    }

    /**
     * Find the next block index
     *
     * @return int
     */
    private function getNextBlockIndex()
    {
        $index = $this->currentIndex;

        while ($index < count($this->currentContents) - 1) {
            if ("---" === substr($this->currentContents[++$index], 0, 3)) {
                return $index--;
            }
        }

        return count($this->currentContents);
    }


    /**
     * @param $dump
     * @return \Wesnick\FdfUtility\Fields\PdfField
     * @throws \Exception
     */
    private function createFieldFromPdftkDump($dump)
    {
        switch ($dump['FieldType']) {
            case 'Button':
                $field = new ButtonField();
                break;
            case 'Choice':
                $field = new ChoiceField();
                break;
            case 'Text':
                $field = new TextField();
                break;
            default:
                throw new \Exception(sprintf("Unrecognized Field Type %s", $dump['FieldType']));
        }

        if (!isset($dump['FieldJustification'])) {
            $dump['FieldJustification'] = null;
        }

        $field
            ->setName($dump['FieldName'])
            ->setJustification($dump['FieldJustification'])
            ->setFlag($dump['FieldFlags'])
        ;

        if (isset($dump['FieldValue'])) {
            $field->setValue($dump['FieldValue']);
        }

        if (isset($dump['FieldValueDefault'])) {
            $field->setDefaultValue($dump['FieldValueDefault']);
        }

        if (isset($dump['FieldMaxLength'])) {
            $field->setMaxLength($dump['FieldMaxLength']);
        }

        if (!empty($dump['FieldStateOption'])) {
            foreach ($dump['FieldStateOption'] as $opt) {
                $field->addOption($opt, $opt);
            }
        }

        return $field;
    }
}
