## pdoToolsPlus
pdoToolsPlus is an extension for pdoTools. It provides a new approach to development - file-based elements. Now you don't need to create chunks, snippets and also templates in the database. Use your favorite IDE. This solution is based on Fenom PHP template engine and more flexible and faster than MODX static elements.

## Instructions
After installation you should set the system setting "pdoTools.class" to "pdotools.pdotoolsplus".  
Default path for element files is `core/elements/(chunks|snippets|plugins|templates)`. But you can define you own path using "tplPath" parameters.  
  
To make this to work use Fenom filters.

### Chunks
* Get the MODX chunk "head".  
```
{'head' | chunk}
```
* Get chunk from file core/elements/chunks/head.tpl.  
```
{'@FILE head.tpl' | chunk}
```  
* Get chunk from file assets/tpl/main/head.tpl.  
```
{'@FILE head.tpl' | chunk : ['tplPath'=>'assets/tpl/main/']}
```
* Get chunk from file assets/tpl/main/head.tpl.  
```
{'@FILE main/head.tpl' | chunk : ['tplPath'=>'assets/tpl/']}
```   
* Get inline chunk.  
```
{'@INLINE <p>This is an inline chunk </p>' | chunk}
```
* Set placeholders for inline chunk.  
```
{'@INLINE <p>The value of the placeholder pls is [[+pls]]</p>' | chunk : ['pls'=>'some value'].}
```
* Get the MODX template "Bootstrap.Main".  
```
{'@TEMPLATE Bootstrap.Main' | chunk}
```  
* Disable the tag parsing. Another way is to put in the HTML comment tags ('<!--' and '-->').  
```
{'@OFF breadcrumbs' | chunk}
```  

Put it in the resource, template or chunk content. Available extensions for the chunk files are 'html' and 'tpl'.  

### Snippets
* Get the MODX snippet 'mySnippet'.  
```
{'mySnippet' | snippet}
```
* Get snippet from file core/elements/snippets/mysnippet.php.  
```
{'@FILE mysnippet' | snippet}
```  
* Get snippet from file assets/snippets/products/head.tpl.  
```
{'@FILE mysnippet' | snippet : ['tplPath'=>'assets/snippets/products/']}
```  
* Get snippet from file assets/snippets/products/head.tpl.  
```
{'@FILE products/mysnippet' | snippet : ['tplPath'=>'assets/snippets/products/']}
```
* Pass $scriptProperties to the snippet.  
```
{'@FILE mysnippet' | snippet : ['var1'=>'val1', 'var2'=>'val2']}
```  
* Pass $scriptProperties to the snippet. Also you can use prefix @INLINE.  
```
{'@CODE return "This resource created on " . date("m.d.Y", strtotime($createdon));' | snippet : ['createdon'=>'[[*createdon]]']}
```
* Use the filter "code" instead of the prefix @CODE.  
```
{'return "This resource created on " . date("m.d.Y", strtotime($createdon));' | code : ['createdon'=>'[[*createdon]]']}
```
* Disable the tag parsing. Another way is to put in the HTML comment tags ('<!--' and '-->').  
```
{'@OFF mySnippet' | snippet}
```   

Inline snippets are available only if the system settings 'pdotools_fenom_modx' and 'pdotools_fenom_php' are true.  
These are examples for HTML contents (resource, template or chunk). In the PHP code you can use pdoTools::runSnippet(). It supports the prefix @FILE in the snippet name.  
```
$pdoTools = $modx->getService('pdoTools');
$result = $pdoTools->runSnippet('mySnippet', $arrayOfProperties); // pdoTools runs the MODX method runSnippet('mySnippet', $arrayOfProperties).
$result = $pdoTools->runSnippet('@FILE mysnippet', $arrayOfProperties); // pdoTools gets the code from file in core/elements/snippets/mysnippet.php.
```
 
### Templates
There are several ways to work with templates. The first way, you can create as many templates as you need (as before) and put a Fenom tag in the template content.  
`{'@FILE mainpage.html' | template}` - get the template from core/elements/templates/mainpage.html.  
For each resource you need to set the corresponding template (as usual).  
The second way is to put all logic in the snippet file "gettemplate.php". For all resources you specify only one template.
`{@FILE 'gettemplate' | snippet : ['resource'=>$_modx->resource]}` - pass an array of resource data to the snippet.  
The code of the snippet can be so  
```
<?php  
$output = '';  
switch (true) {  
    // Define a template for a specific resource
    case in_array($resource['id'], array(1)):  
        $output = $pdoTools->getChunk('@FILE main.html', array('tplPath'=>'core/elements/templates'));  
        break;  
    case in_array($resource['id'], array(7)):
        $output = $pdoTools->getChunk('@FILE contacts.html, array('tplPath'=>'core/elements/templates')');
        break;
    // Define a template for a group of resources. 
    //if the resource is moved to another parent the template will be changed automatically.
    case in_array($resource['parent'], array(2,3)): 
        $output = $pdoTools->getChunk('@FILE services.html, array('tplPath'=>'core/elements/templates')');
        break;
    case in_array($resource['parent'], array(4,5)): 
        $output = $pdoTools->getChunk('@FILE news.html, array('tplPath'=>'core/elements/templates')');
        break;        
    default:
        $output = $resource['content']; // Empty template - only resource content.
        break;
}
return $output;
```
The second way is more complicated then the first one. It can be used on small sites.  
You can combine these two ways.  

## Plugins
Create a plugin with the following content  
```
<?php
if (!empty($this->static_file) && file_exists($this->static_file)) include_once $this->static_file;
```
and check the event 'OnMODXInit'.  
Now we need to tie the plugin to our file.  
1. Check the "Static" checkbox.  
2. Set the path to our file (for example, core/elements/plugins/myplugin.php).  
3. Uncheck the "Static" checkbox.  
4. Save the plugin.  

Put the next code to your plugin file
```
<?php
/** @var pdoToolsPlus $pdoTools */
$pdoTools = $modx->getService('pdoTools');
// 1. Specify events which you need (for example, "OnWebPagePrerender")  
$events = array('OnWebPagePrerender');
if (!$pdoTools->initPlugin($this, $events, false)); // Set the third parameter to true to disable plugin.

// 2. Your code for the specified events
switch ($modx->event->name) {
    case 'OnWebPagePrerender':
        /* Put you code here */
        break;
}
```
That's all. It's not so hard as it looks.

## Advantages

* The elements are stored on disk. No elements in the database. No elements in the element tree.
* The changes are reflected immediately.
* MODX does not clear the site cache when you changed the file. 
* Get enjoyment in developing in your favorite IDE.
* Full version control.

## Conclusion
  
You should understand that all these file-based elements are non-cacheable. If you have some heavy snippets or chunks you should think about caching by yourself. Use pdoTools methods setCache() and getCache() or methods of modCacheManager.  
Now you don't need to go to the manager so often. Try. You like it :).  