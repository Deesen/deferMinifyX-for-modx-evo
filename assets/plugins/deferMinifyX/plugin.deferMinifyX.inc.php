<?php
if (!defined('MODX_BASE_PATH')) { die('What are you doing? Get out of here!'); }

$core_path = MODX_BASE_PATH."assets/plugins/deferMinifyX/";
require_once($core_path.'class/class.deferMinifyX.php');

// Helpers for translating configuration
if(!function_exists('bool')) {
    function bool($val) { return $val == 'enabled' ? true : false; }
}

// Options to pass
$optionsArr = array(
// Parameters for get()
'defer'         =>isset($defer)         ? bool($defer)      : false,      // Enable/disable minify globally
'minify'        =>isset($minify)        ? bool($minify)     : true,       // Enable/disable minify globally
'minifyDefer'   =>isset($minifyDefer)   ? bool($minifyDefer): true,       // Minify defer script before injecting
'minifyCss'     =>isset($minifyCss)     ? bool($minifyCss)  : true,       // Enable minify CSS-files into min.css
'minifyCssFile' =>isset($minifyCssFile) ? $minifyCssFile    : 'min.css',  // FilePath of min.css
'minifyJs'      =>isset($minifyJs)      ? bool($minifyJs)   : true,       // Enable minify JS-files into min.js
'minifyJsFile'  =>isset($minifyJsFile)  ? $minifyJsFile     : 'min.js',   // FilePath of min.js
'minifyJsLib'   =>isset($minifyJsLib)   ? $minifyJsLib      : 'minifier', // Minify or jsshrink
'minifyCssLib'  =>isset($minifyCssLib)  ? $minifyCssLib     : 'minifier', // Only minify available right now
'minifyHtml'    =>isset($minifyHtml)    ? $minifyHtml       : 'disabled', // Minify HTML-Output
'noScriptCss'   =>isset($noScriptCss)   ? $noScriptCss      : true,       // Add <noscript><link style.css></noscript> as fallback
'deferImages'   =>isset($deferImages)   ? bool($deferImages): false,      // Add deferImages-script (src="blank.jpg" data-src="real-image.jpg")
'blankImage'    =>isset($blankImage)    ? $blankImage       : 'img/blank.jpg', // Name of blank.jpg
'cache'         =>isset($cache)         ? bool($cache)      : true,       // Enable/disable caching of minified files (for debug)
'hashParam'     =>isset($hashParam)     ? $hashParam        : '',         // Hash xxx xxx.css?h=xosbsof
'debug'         =>isset($debug)         ? bool($debug)      : false,      // Add debug-infos to HTML
'debugTpl'      =>isset($debugTpl)      ? $debugTpl         : '',         // Optional debugTpl with [+ids+],[+js+],[+css+],[+options+],[+messages+]

// Internal parameters
'core_path'     =>$core_path,
'base_path'     =>!empty($basePath)     ? $basePath         : MODX_BASE_PATH,   // Base path
'cache_path'    =>!empty($cachePath)    ? $cachePath        : MODX_BASE_PATH.'assets/cache/deferMinifyX/', // Path to store internal cache-files
// 'assets_url'    =>isset($assetsUrl)     ? $assetsUrl        : '',       // @todo: Save/call assets from different path
// 'assets_path'   =>isset($assetsPath)    ? $assetsPath       : '',       // @todo: Save/call assets from different path
'defaultCssFiles'=>isset($defaultCssFiles) ? $defaultCssFiles : '',
'defaultJsFiles'=>isset($defaultJsFiles) ? $defaultJsFiles : '',
'cacheReset'=>isset($cacheReset) ? $cacheReset : 'index',
'sessionAuth'   =>$_SESSION['mgrValidated'] === 1   // Determine if session is allowed for debugging-infos
);

// Check activeIds
if(!empty($activeIds)) {
    $exp = explode(',', $activeIds);
    if(!in_array($modx->documentIdentifier, $exp)) return;
}

// Check inactiveIds
if(!empty($inactiveIds)) {
    $exp = explode(',', $inactiveIds);
    if(in_array($modx->documentIdentifier, $exp)) return;
}
    
$e = &$modx->event;
switch ($e->name) {

    // 1. Set options at Modx Init before cache
    case "OnLoadWebDocument":
        deferMinifyX::setOptions($optionsArr);
        deferMinifyX::loadCache();
        deferMinifyX::prepareMinifyLibs();
        break;

    // 2. Set default-setup before parsing first snippet-calls to reliably set ID "min"
    // Additional [[deferMinifyX?&add=`src`&val=``&id=``&dependsOn=`min`]] 
    case "OnParseDocument":
        // default min-sets from plugin-config
        deferMinifyX::addCssSrc($defaultCssFiles, true, false, false, 'default'); // Add css as default-set
        deferMinifyX::addScriptSrc($defaultJsFiles, 'min');     // Add script-src with ID "min"
        deferMinifyX::addScript($defaultInlineJs);              // Add inline-script
        break;

    // 3. Before sending to browser prepare and prepend final script to </body> 
    case "OnWebPagePrerender":
        
        if (isset($setPlaceholder) && !empty(isset($setPlaceholder)) && $outputArr['output'] != '') {
            $outputArr = deferMinifyX::getDefer();
            $modx->setPlaceholder($setPlaceholder, $outputArr['output'].$outputArr['debug']);
            break;
        } else {
            $output = &$modx->documentOutput;
            $output = deferMinifyX::modifyOutput($output);
            $output = deferMinifyX::minifyHtml($output);
        }
        deferMinifyX::updateCache();
        break;

    case "OnSiteRefresh":
        deferMinifyX::setOptions($optionsArr);
        deferMinifyX::resetCache();
        break;
    
    // Important! Stop here!
    default :
        return;
}