<?php
if (!defined('MODX_BASE_PATH')) { die('What are you doing? Get out of here!'); }

// Commands
$id         = isset($id)        ? $id           : NULL;  // to enable "script dependsOn id"
$dependsOn  = isset($dependsOn) ? $dependsOn    : NULL;  // enables "dependsOn" on "id"
$unique     = isset($unique)    ? $unique       : true;  // @todo: test "must be called uncached or with random-parameter to avoid caching by Modx" 
$val        = isset($val)       ? $val          : NULL;  // for &get=`img,css,js,script`
$val        = isset($file)      ? $file         : $val;  // for &get=`img,css,js,script`
$defer      = isset($defer)     ? $defer        : false; // for &add=`css,js,script` (<script defer>)
$async      = isset($async)     ? $async        : false; // for &add=`css,js,script` (<script async>)

// @todo: Set standard setup of minified files in plugin-configuration

// Simple mode
// Handle &get=``-commandos
if(isset($get)) {
    switch($get) {
        case 'img64':
            return deferMinifyX::getImageBase64($file);
        case 'base64':
            return deferMinifyX::getFileBase64($file);
        case 'css':
        case 'js':
            return deferMinifyX::getMinifiedAndCachedFile($file, $get);  
        case 'minifyCss':
            return deferMinifyX::minifyCss($val);  // use [[cached]]
        case 'minifyJs':
            return deferMinifyX::minifyJs($val);  // use [[cached]]
        
        // Get full init-defer script - normally plugin-event OnWebPagePrerender takes care!
        case 'defer':
            $outputArr = deferMinifyX::getDefer();
            
            if (isset($setPlaceholder) && !empty($setPlaceholder) && $outputArr['output'] != '') {
                $modx->setPlaceholder($setPlaceholder, $outputArr['output'].$outputArr['debug']);
                break;
            } else {
                return $outputArr['output'].$outputArr['debug'];
            }
        
        default:
            deferMinifyX::debug('Unknown parameter get='.$get);
    }

// Advanced mode
// Handle &add=``-commandos for chaining
} else if(isset($add)) {
    switch($add) {
        case 'css':
            deferMinifyX::addCssSrc($val, $unique, $defer, $async);
            break;
        case 'js':
            deferMinifyX::addScriptSrc($val, $id, $dependsOn, $unique, $defer, $async);
            break;
        case 'script':
            deferMinifyX::addScript($val, $id, $dependsOn, $unique);
            break;
        default:
            deferMinifyX::debug('Unknown parameter add='.$add);
    }

// Allow changing configuration dynamically
} else if(isset($option)) {
    if(isset($val)) {
        deferMinifyX::setOption($option, $val);
    } else {
        deferMinifyX::debug('Value for setOption("'. $option .'") missing');
    }
}
return '';
?>