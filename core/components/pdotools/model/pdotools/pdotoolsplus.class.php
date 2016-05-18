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

    public function runSnippet($name = '', array $scriptProperties = array())
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
                $snippet->_cacheable = false;
                $snippet->_processed = false;
                $returnFunction = $modx->getOption('returnFunction');
                $returnFunction = !empty($returnFunction);
                $modx->setOption('returnFunction', true);
                $scriptProperties['pdoTools'] = $pdoTools;
                $output = $snippet->process($scriptProperties, $content);
                $modx->setOption('returnFunction', $returnFunction);
                break;
            case 'FILE':
                if (isset($scriptProperties['tplPath'])) {
                    $_path = $scriptProperties['tplPath'] . '/';
                } elseif (isset($this->config['tplPath'])) {
                    $_path = $this->config['tplPath'] . '/';
                } else {
                    $_path =  MODX_CORE_PATH . 'elements/snippets/';
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
            case 'OFF':
                $output = '';
                break;
            default:
                $output = $this->modx->runSnippet($name, $scriptProperties);
                $this->addTime('Ran the MODX snippet "' . $name . '"');
        }

        return $output;
    }

    /**
     * {@inheritdoc}
     * Changed the path definition of the @FILE binding
     *
     * @param string $name Name or binding
     * @param array $row Current row with results being processed
     *
     * @return array
     */
    protected function _loadChunk($name, $row = array())
    {
        $binding = $content = $propertySet = '';

        $name = trim($name);
        if (preg_match('/^@([A-Z]+)/', $name, $matches)) {
            $binding = $matches[1];
            $content = substr($name, strlen($binding) + 1);
            $content = ltrim($content, ' :');
        }
        // Get property set
        if (!$binding && $pos = strpos($name, '@')) {
            $propertySet = substr($name, $pos + 1);
            $name = substr($name, 0, $pos);
        } elseif (in_array($binding, array('CHUNK', 'TEMPLATE')) && $pos = strpos($content, '@')) {
            $propertySet = substr($content, $pos + 1);
            $content = substr($content, 0, $pos);
        }
        // Replace inline tags
        $content = str_replace(array('{{', '}}'), array('[[', ']]'), $content);

        // Change name for empty TEMPLATE binding so will be used template of given row
        if ($binding == 'TEMPLATE' && empty($content) && isset($row['template'])) {
            $name = '@TEMPLATE ' . $row['template'];
            $content = $row['template'];
        }

        // Load from cache
        $cache_name = !empty($binding) && $binding != 'CHUNK' ? md5($name) : $name;
        if ($chunk = $this->getStore($cache_name, 'chunk')) {
            return $chunk;
        }

        $id = 0;
        $properties = array();
        /** @var modChunk $element */
        switch ($binding) {
            case 'CODE':
            case 'INLINE':
                $element = $this->modx->newObject('modChunk', array('name' => $cache_name));
                $element->setContent($content);
                $this->addTime('Created inline chunk with name "' . $cache_name . '"');
                break;
            case 'FILE':
                if (isset($row['tplPath'])) {
                    $path = $row['tplPath'] . '/';
                } elseif (isset($this->config['tplPath'])) {
                    $path = $this->config['tplPath'] . '/';
                } else {
                    $path =  MODX_CORE_PATH . 'elements/chunks/';
                }
                if (strpos($path, MODX_BASE_PATH) === false) {
                    $path = MODX_BASE_PATH . $path;
                }
                $path = preg_replace('#/+#', '/', $path . $content);
                if (!preg_match('/(.html|.tpl)$/i', $path)) {
                    $this->addTime('Allowed extensions for @FILE chunks is "html" and "tpl"');
                } elseif (!file_exists($path)) {
                    $this->addTime('Could not find tpl file at "' . str_replace(MODX_BASE_PATH, '', $path) . '".');

                    return false;
                } elseif ($content = file_get_contents($path)) {
                    $element = $this->modx->newObject('modChunk', array('name' => $cache_name));
                    $element->setContent($content);
                    $this->addTime('Loaded chunk from "' . str_replace(MODX_BASE_PATH, '', $path) . '"');
                }
                break;
            case 'TEMPLATE':
                /** @var modTemplate $template */
                if ($template = $this->modx->getObject('modTemplate',
                    array('id' => $content, 'OR:templatename:=' => $content))
                ) {
                    $content = $template->getContent();
                    if (!empty($propertySet)) {
                        if ($tmp = $template->getPropertySet($propertySet)) {
                            $properties = $tmp;
                        }
                    } else {
                        $properties = $template->getProperties();
                    }
                    $element = $this->modx->newObject('modChunk', array('name' => $cache_name));
                    $element->setContent($content);
                    $this->addTime('Created chunk from template "' . $template->templatename . '"');
                    $id = $template->get('id');
                }
                break;
            case 'CHUNK':
                $cache_name = $content;
                if ($element = $this->modx->getObject('modChunk', array('name' => $cache_name))) {
                    $content = $element->getContent();
                    if (!empty($propertySet)) {
                        if ($tmp = $element->getPropertySet($propertySet)) {
                            $properties = $tmp;
                        }
                    } else {
                        $properties = $element->getProperties();
                    }
                    $this->addTime('Loaded chunk "' . $cache_name . '"');
                    $id = $element->get('id');
                }
                break;
            default:
                if ($element = $this->modx->getObject('modChunk', array('name' => $cache_name))) {
                    $content = $element->getContent();
                    if (!empty($propertySet)) {
                        if ($tmp = $element->getPropertySet($propertySet)) {
                            $properties = $tmp;
                        }
                    } else {
                        $properties = $element->getProperties();
                    }
                    $this->addTime('Loaded chunk "' . $cache_name . '"');
                    $binding = 'CHUNK';
                    $id = $element->get('id');
                }
        }

        if (!$element) {
            $this->addTime('Could not load or create chunk "' . $name . '".');

            return false;
        }

        // Preparing special tags
        if (strpos($content, '<!--' . $this->config['nestedChunkPrefix']) !== false) {
            preg_match_all('/\<!--' . $this->config['nestedChunkPrefix'] . '(.*?)[\s|\n|\r\n](.*?)-->/s', $content,
                $matches);
            $src = $dst = $placeholders = array();
            foreach ($matches[1] as $k => $v) {
                $src[] = $matches[0][$k];
                $dst[] = '';
                $placeholders[$v] = $matches[2][$k];
            }
            if (!empty($src) && !empty($dst)) {
                $content = str_replace($src, $dst, $content);
            }
        } else {
            $placeholders = array();
        }

        $chunk = array(
            'object' => $element,
            'content' => $content,
            'placeholders' => $placeholders,
            'properties' => $properties,
            'name' => $cache_name,
            'id' => $id,
            'binding' => strtolower($binding),
        );
        $this->setStore($cache_name, $chunk, 'chunk');

        return $chunk;
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
