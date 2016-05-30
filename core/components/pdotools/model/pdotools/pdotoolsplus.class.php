<?php
require_once 'pdotools.class.php';

class pdoToolsPlus extends pdoTools
{
    /**
     * Loads template engine
     *
     * @return bool|Fenom
     */
    public function getFenom()
    {
        if (!$this->fenom) {
            try {
                if (!class_exists('FenomPlus')) {
                    require '_fenomplus.php';
                }
                $this->fenom = new FenomPlus($this);
            } catch (Exception $e) {
                $this->modx->log(xPDO::LOG_LEVEL_ERROR, $e->getMessage());

                return false;
            }
        }

        return $this->fenom;
    }

    public function runSnippet($name = '', array $scriptProperties = array(), $cacheable  = null )
    {
        $pdoTools = $this;
        $modx =& $this->modx;
        $name = trim($name);
        $binding = $content = $output = '';
        if (empty($name)) {
            return $output;
        }
        if (preg_match('/^@([A-Z]+)/', $name, $matches)) {
            $binding = $matches[1];
            $content = substr($name, strlen($binding) + 1);
            $content = ltrim($content, ' :');
        }
        $permissions = $modx->getOption('pdotools_fenom_modx',null, false) && $modx->getOption('pdotools_fenom_php',null, false);
        switch ($binding) {
            case 'CODE':
            case 'INLINE':
                if (!$permissions) break;
                $name = md5($content);
                /** @var modSnippet $snippet */
                $snippet = $this->modx->newObject('modSnippet', array('name' => $name));
                $snippet->_scriptName= 'elements/modsnippet/' . $name;
                $this->addTime('Created inline snippet with name "' . $name . '"');
                $snippet->setCacheable(false);
                $returnFunction = $modx->getOption('returnFunction');
                $returnFunction = !empty($returnFunction);
                $modx->setOption('returnFunction', true);
                $scriptProperties['pdoTools'] = $pdoTools;
                $output = $snippet->process($scriptProperties, $content);
                $modx->setOption('returnFunction', $returnFunction);
                break;
            case 'FILE':
                if (!empty($scriptProperties['tplPath'])) {
                    $_path = $scriptProperties['tplPath'];
                } elseif (!empty($scriptProperties['elementsPath'])) {
                    $_path = $scriptProperties['elementsPath'];
                } elseif (!empty($this->config['elementsPath'])) {
                    $_path = $this->config['elementsPath'] . 'snippets/';
                } else {
                    $_path =  $modx->getOption('pdotools_elements_path', null, MODX_CORE_PATH . 'elements/') . 'snippets/';
                }

                if (strpos($_path, MODX_BASE_PATH) === false) {
                    $_path = MODX_BASE_PATH . $_path;
                }
                $file = preg_replace('#/+#', '/', $_path . $content);
                if (!preg_match('/(.php)$/i', $file)) {
                    $file .= '.php';
                }
                if (!is_readable($file)) {
                    $this->addTime('Could not load the snippet from the file "' . str_replace(MODX_BASE_PATH, '', $file) . '".');
                    $modx->log(modX::LOG_LEVEL_ERROR, '[pdoTools] Could not load snippet from file "' . str_replace(MODX_BASE_PATH, '', $file) . '"');
                } else {
                    ob_start();
                    if ($scriptProperties) extract($scriptProperties, EXTR_SKIP);
                    $includeResult = include $file;
                    $includeResult = ($includeResult === null ? '' : $includeResult);
                    if (ob_get_length()) {
                        $output = ob_get_contents() . $includeResult;
                    } else {
                        $output = $includeResult;
                    }
                    ob_end_clean();
                    $this->addTime('Loaded the snippet from "' . str_replace(MODX_BASE_PATH, '', $_path) . '"');
                }
                break;
            default:
                $output = $this->modx->runSnippet($name, $scriptProperties);
                $this->addTime('Ran the MODX snippet "' . $name . '"');
        }

        return $output;
    }

    /**
     * @param modPlugin $plugin
     * @param array $events
     * @param bool $disabled
     * @return bool
     */
    public function initPlugin($plugin, $events = array(), $disabled = false)
    {
        $modx =& $this->modx;
        if ($modx->event->name == 'OnMODXInit') {
            if (!file_exists(MODX_BASE_PATH . $plugin->static_file)) {
                $this->addTime('Plugin file "' . $plugin->static_file . ' is not found!"');
                $modx->log(modX::LOG_LEVEL_ERROR, 'Plugin file "' . $plugin->static_file . ' is not found!"');
                return false;
            }
            if (empty($events) || !is_array($events)) {
                $this->addTime('Events for plugin "' . $plugin->name . ' are not defined!"');
                return false;
            }

            foreach ($events as $event) {
                $modx->eventMap[$event][$plugin->id] = $plugin->id;
            }

            $content = @file_get_contents(MODX_BASE_PATH . $plugin->static_file) . "\nreturn;";
            //$modx->pluginCache[$plugin->id]['properties'] = array();
            $modx->pluginCache[$plugin->id]['plugincode'] = $content;
            $modx->pluginCache[$plugin->id]['disabled'] = (int) $disabled;

            $includeFilename = $modx->getCachePath() . 'includes/' . $plugin->getScriptCacheKey() . '.include.cache.php';
            file_put_contents($includeFilename, $content);
        }
        return true;
    }
}
