<?php
if (!defined('MODX_BASE_PATH')) { die('What are you doing? Get out of here!'); }

$core_path = MODX_BASE_PATH."assets/snippets/deferMinifyX/";
require_once($core_path.'class/class.deferMinifyX.php');

// Commands
$id         = isset($id)        ? $id           : NULL; // to enable "script dependsOn id"
$dependsOn  = isset($dependsOn) ? $dependsOn    : NULL; // enables "dependsOn" on "id"
$unique     = isset($unique)    ? $unique       : true; // must be called uncached or with random-parameter to avoid caching by Modx

// Options to pass
$optionsArr = array(
    // Parameters for get()
    'deferImages'   =>isset($deferImages)   ? $deferImages      : false,    // Add deferImages-script (src="blank.jpg" data-src=""
    'minify'        =>isset($minify)        ? $minify           : true,     // Enable/disable minify globally
    'minifyDefer'   =>isset($minifyDefer)   ? $minifyDefer      : true,     // Minify defer script before injecting
    'minifyCss'     =>isset($minifyCss)     ? $minifyCss        : true,     // Enable minify CSS-files into min.js
    'minifyCssFile' =>isset($minifyCssFile) ? $minifyCssFile    : 'min.css',// FilePath of min.css
    'minifyJs'      =>isset($minifyJs)      ? $minifyJs         : true,     // Enable minify JS-files into min.js
    'minifyJsFile'  =>isset($minifyJsFile)  ? $minifyJsFile     : 'min.js', // FilePath of min.js
    'cache'         =>isset($cache)         ? $cache            : true,     // Enable/disable caching of minified files (for debug)
    'cacheFile'     =>isset($cacheFile)     ? $cacheFile        : 'deferMinifyX.json', // FilePath for caching latest filetimes
    'hashParam'     =>isset($hashParam)     ? $hashParam        : '',       // Hash xxx xxx.css?h=xosbsof
    'debug'         =>isset($debug)         ? $debug            : false,    // Add debug-infos
    'debugTpl'      =>isset($debug)         ? $debug            : 'default',// @todo: 'default' shows infos as HTML-comments but can be styled 
    
    // Internal parameters
    'core_path'     =>$core_path,       // Path to snippet.deferMinifyX.php
    'base_path'     =>MODX_BASE_PATH,   // Base path 
    'cache_path'    =>isset($cachePath)     ? $cachePath        : MODX_BASE_PATH.'assets/cache/', // Path to store cacheFile
    
    // @todo: enable saving/calling min.css/min.js from different domain
    'assets_path'   =>isset($assetsPath)    ? $assetsPath       : false,    // Save minified files into different directory
    'assets_url'    =>isset($debug)         ? $debug            : '',       // Use absolute URL for loading minified files from different domain 
    
    // Determine if session is allowed for debugging-infos
    'sessionAuth'   =>$_SESSION['mgrValidated'] === 1
);

deferMinifyX::setOptions($optionsArr);

if (isset($addScriptSrc) && !empty(isset($addScriptSrc))) {
    deferMinifyX::addScriptSrc($addScriptSrc, $id, $dependsOn, $unique);
}

if (isset($addScript) && !empty(isset($addScript))) {
    deferMinifyX::addScript($addScript, $id, $dependsOn, $unique);
}

if (isset($addCssSrc) && !empty(isset($addCssSrc))) {
    deferMinifyX::addCssSrc($addCssSrc, $unique);
}

if (isset($get)) {
    $outputArr = deferMinifyX::get($get);
    
    if (isset($setPlaceholder) && !empty(isset($setPlaceholder)) && $outputArr['output'] != '') {
        $modx->setPlaceholder($setPlaceholder, $outputArr['output'].$outputArr['debug']);
    } else {
        return $outputArr['output'].$outputArr['debug'];
    }
}

return '';
?>