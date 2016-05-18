<?php
require_once '_fenom.php';

class FenomPlus extends FenomX
{
    /**
     * @inheritdoc
     */
    protected function _addDefaultModifiers()
    {
        parent::_addDefaultModifiers();
        $modx = $this->modx;
        /** @var pdoToolsPlus $pdo */
        $pdo = $this->pdoTools;
        //$fenom = $this;

        // Get chunk from file
        $this->_modifiers['chunk'] = function ($input, $options = array()) use ($modx, $pdo) {
            if (preg_match('/^@OFF/', $input)) return '';
            $input = str_replace(array('../','./'),'',$input);
            $output = $pdo->getChunk($input, $options);

            return $output;
        };
        // Get chunk from the templates folder
        $this->_modifiers['template'] = function ($input, $options = array()) use ($modx, $pdo) {
            $input = str_replace(array('../','./'),'',$input);
            $binding = '';
            if (preg_match('/^@([A-Z]+)/', $input, $matches)) {
                $binding = $matches[1];
            }
            switch ($binding) {
                case '':
                    $input = '@TEMPLATE ' . $input;
                    break;
                case 'OFF':
                    return '';
                    break;
            }
            if (!isset($options['tplPath'])) $options['tplPath'] = MODX_CORE_PATH . 'elements/templates/';
            $output = $pdo->getChunk($input, $options);

            return $output;
        };
        // Get snippet from file
        $this->_modifiers['snippet'] = function ($input, $options = array()) use ($modx, $pdo) {
            $input = str_replace(array('../','./'),'',$input);
            $output = $pdo->runSnippet($input, $options);

            return $output;
        };

        // Run code from content
        $this->_modifiers['code'] = function ($input, $options = array()) use ($modx, $pdo) {
            $input = '@CODE ' . $input;
            $output = $pdo->runSnippet($input, $options);

            return $output;
        };
    }

}
