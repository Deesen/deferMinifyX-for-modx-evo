<?php
/**
 * deferMinifyX
 *
 * @version     0.1
 * @license     http://www.gnu.org/copyleft/gpl.html GNU Public License (GPL)
 * @author      Deesen / updated: 2016-03-19
 *
 * Latest Updates / Issues on Github : https://github.com/Deesen/deferMinifyX
 */

class deferMinifyX
{
    static $buffer = '';
    static $cssArr = array();
    static $cssCacheChanged = false;
    static $debugMessages = array();
    static $idsArr = array();
    static $jsArr = array();
    static $jsCacheChanged = false;
    static $jsTmpArr = array();
    static $options = array();
    private static $version = '0.1';

    static function addScriptSrc($string, $id=NULL, $dependsOn=NULL, $unique=true)
    {
        $arr = explode(',', $string);
        $id         = !$id || count($arr) >> 1  ? NULL  : $id;
        $dependsOn  = !$dependsOn               ? '0'   : $dependsOn;
        
        foreach($arr as $value) {
            $found = $unique != false ? self::checkUniqueJs($value, 'src') : false;
            if (!$found) {
                $newSrc = array();
                $newSrc['val'] = $value;
                if ($id) $newSrc['id'] = $id;
                self::$jsArr[$dependsOn]['src'][] = $newSrc;
            }
        }
    }

    static function addScript($value, $id, $dependsOn, $unique=true)
    {
        $id         = !$id          ? NULL  : $id;
        $dependsOn  = !$dependsOn   ? '0'   : $dependsOn;
        $found = $unique != false ? self::checkUniqueJs($value, 'js') : false;
        
        if (!$found) {
            $newSrc = array();
            $newSrc['val'] = $value;
            if ($id) $newSrc['id'] = $id;
            self::$jsArr[$dependsOn]['js'][] = $newSrc;
        }
    }

    static function checkUniqueJs($value, $type)
    {
        $found = false;
        foreach(self::$jsArr as $dependsOn=>$set) {
            if(isset($set[$type])) {
                foreach ($set[$type] as $script) {
                    if($script['val'] === $value) {
                        $found = true;
                        break;
                    }
                }
            }
        }
        return $found;
    }

    static function addCssSrc($string, $unique)
    {
        $arr = explode(',', $string);
        
        foreach($arr as $src) {
            $found = false;
            if ($unique != false) {
                foreach (self::$cssArr as $css) {
                    if ($css['src'] === $src) {
                        $found = true;
                        break;
                    }
                }
            }
            if (!$found) self::$cssArr[]['src'] = $src;
        }
    }
   
    static function opt($option)
    {
        return isset(self::$options[$option]) ? self::$options[$option] : NULL;
    }

    static function debug($message)
    {
        self::$debugMessages[] = $message;
    }

    static function getVersion()
    {
        return self::$version;
    }

    static function setOptions($optionsArr)
    {
        self::$options = $optionsArr;
    }

    static function get($mode)
    {
        // Init vars
        $cache      = array('css'=>0,'src'=>0,'js'=>0);
        $cacheFile  = self::opt('cache_path') . self::opt('cacheFile');
        $minCss     = self::opt('minifyCssFile');
        $minFile    = self::opt('minifyJsFile');
        $jsonIndent = self::opt('debug') != false ? JSON_PRETTY_PRINT : 0;

        // Prepare Minify if enabled  
        if(self::opt('minify') && (self::opt('minifyDefer') || self::opt('minifyCss') || self::opt('minifyJs') || self::opt('minifyJsScript'))) {
            require_once(self::opt('core_path').'class/JShrink.php');    // Load JShrink -> https://github.com/tedious/JShrink
        }
        
        // Prepare Cache if enabled - load cached timestamps
        if(self::opt('cache')) {
            if(file_exists($cacheFile)) {
                $cached = file_get_contents($cacheFile);
                $cached = json_decode($cached, true);
                $cache  = json_last_error() == JSON_ERROR_NONE ? $cached : array();
                $cache['css'] = isset($cache['css']) ? (int)$cache['css']   : 0;
                $cache['src'] = isset($cache['src']) ? (int)$cache['src']   : 0;
                $cache['js']  = isset($cache['js'])  ? (string)$cache['js'] : '';
            }
        }
        
        // Prepare CSS-object
        if(self::opt('cache')) {

            // Check for changed files
            if(!empty(self::$cssArr)) {
                foreach (self::$cssArr as $file) {
                    $filePath = self::opt('base_path') . $file['src']; 
                    if(file_exists($filePath)) {
                        $time = (int)filemtime($filePath);
                        if ($time > $cache['css']) self::$cssCacheChanged = true;
                        $cache['css'] = $time > $cache['css'] ? $time : $cache['css'];
                    } else {
                        self::debug('CSS file not found: '.$file['src']);
                    }
                }
            }

            // Create new min.css
            if(self::$cssCacheChanged || !file_exists(self::opt('base_path') . $minCss)) {
                self::resetBuffer();
                // Buffer Css-files
                foreach (self::$cssArr as $file) {
                    $filePath = self::opt('base_path') . $file['src'];
                    if(file_exists($filePath)) {
                        $fileContent = file_get_contents($filePath);
                        self::$buffer .= $fileContent;
                    }
                }
                if(self::opt('minify') && self::opt('minifyCss')) {
                    self::$buffer = \JShrink\Minifier::minify(self::$buffer);
                }
                if(!file_put_contents(self::opt('base_path').$minCss, self::$buffer)) self::debug('Minified Css-File could not be written: '.$minCss);
            }
            // Overwrite $cssArr - set min.css as only file
            self::$cssArr[0]['src'] = $minCss.'?'.self::opt('hashParam').$cache['css'];
            
            /////////////////////////////////////////////////////////////////////
            // Prepare JS-object - check for changed files
            foreach (self::$jsArr as $dependsOn=>$arr) {
                if($dependsOn === 'min') continue;       // Ignore file that dependsOn "min"
                if(isset($arr['src'])) {
                    foreach ($arr['src'] as $scriptSrc) {
                        $filePath = self::opt('base_path').$scriptSrc['val'];
                        if(file_exists($filePath)) {
                            $time = (int)filemtime($filePath);       // @todo: Determine / use doc_root ?
                            if ($time > $cache['src']) self::$jsCacheChanged = true; // Dont break, find newest
                            $cache['src'] = $time > $cache['src'] ? $time : $cache['src'];
                        } else {
                            self::debug('CSS file not found: '.$scriptSrc['val']);
                        }
                    }
                }
            }

            // Calculate MD5-Hash for Inline-Script 
            $buffer = '';
            foreach(self::$jsArr as $dependsOn=>$jsArr) {
                if(isset($jsArr['js'])) {
                    foreach($jsArr['js'] as $script) {
                        if(!empty($script['val']))
                            $buffer .= $script['val'];
                    }
                }
            }
            $md5 = md5($buffer);
            if($md5 != $cache['js']) {
                self::$jsCacheChanged = true;
                $cache['js'] = $md5;
            }

            // Create new min.js
            if(self::$jsCacheChanged || !file_exists(self::opt('base_path') . $minFile)) {
                self::resetBuffer();
                // Sort equivalent to JS-functions
                if(isset(self::$jsArr[0])) {
                    self::$jsTmpArr = self::$jsArr; // keep $jsArr for debug 
                    self::deferRecursive(0); // will use self::$jsTmpArr and self::$buffer
                }
                if(self::opt('minify') && self::opt('minifyJs')) {
                    self::$buffer = \JShrink\Minifier::minify(self::$buffer);
                }
                if(!file_put_contents(self::opt('base_path').$minFile, self::$buffer)) self::debug('Minified Js-File could not be written: '.$minFile);
            }
            
            // @todo: Add inject inline-mode

            // Overwrite $cssArr - set min.css as only file - append dependsOn min
            $keepDependsOnMinSrc = isset(self::$jsArr['min']) ? array('min'=>self::$jsArr['min']) : array();
            self::$jsArr[0]['src'][0]['val']    = $minFile.'?'.self::opt('hashParam').$cache['src'];
            self::$jsArr[0]['src'][0]['id']     = 'min';
            self::$jsArr = !empty($keepDependsOnMinSrc) ? array_merge(self::$jsArr, $keepDependsOnMinSrc) : self::$jsArr;
        };

        // Write cache-file
        if(self::$cssCacheChanged || self::$jsCacheChanged) {
            if (!file_put_contents($cacheFile, json_encode($cache))) self::debug('Cache-File could not be written: ' . self::opt('cacheFile'));
        }

        // Prepare CSS-object
        $cssStr = json_encode(self::$cssArr, $jsonIndent);

        // Prepare JS-object            
        $scriptSrcStr = json_encode(self::$jsArr, $jsonIndent);

        // Get JS-chaining magic
        $output = self::getJsFunctions($cssStr, $scriptSrcStr);

        // Minify final defer call
        // @todo: Cache already minified
        if(self::opt('minify') && self::opt('minifyDefer')) {
            $output = \JShrink\Minifier::minify($output);
        }
        
        // Return array to get() to enable individual handling of content
        return array(
            'output'=>$output,
            'debug'=>self::renderDebugMsg()
        );
    }
    
    // Same names as JS-functions / equivalent sorting mechanismn
    static function deferScriptSrc($p) {
        if (isset(self::$jsTmpArr[$p]['src'])) {
            foreach (self::$jsTmpArr[$p]['src'] as $i=>$c) {
                $id = isset($c['id']) ? $c['id'] : 'src_'.$p.'_'.$i;
                self::addId($id, $c['val']);
                
                $filePath = self::opt('base_path') . $c['val'];
                if (file_exists($filePath)) {
                    $fileContent = file_get_contents($filePath);    // @todo: Determine / use doc_root ? 
                    self::$buffer .= $fileContent;
                }
                self::deferScript($p);
                self::addOnLoadHandler($id);
            }
        }
    }
    static function deferScript($p) {
        if (isset(self::$jsTmpArr[$p]['js'])) {
            foreach (self::$jsTmpArr[$p]['js'] as $i=>$c) {
                $id = isset($c['id']) ? $c['id'] : 'src_'.$p.'_'.$i;
                self::addId($id, $c['val']);
                self::$buffer .= $c['val'];
            }
            unset(self::$jsTmpArr[$p]['js']);  // Avoid doubling in deferRecursive()
        }
    }
    static function deferRecursive($p) {
        if (isset(self::$jsTmpArr[$p])) {
            if (isset(self::$jsTmpArr[$p]['src']) || isset(self::$jsTmpArr[$p]['js'])) {
                self::deferScriptSrc($p);
                self::deferScript($p);
            }
        }
    }
    static function addOnLoadHandler($id) {
        if(!isset(self::$idsArr[$id])) {
            self::debug('Element "'.$id.'" undefined');
        } else {
            if (isset(self::$jsTmpArr[$id])) {
                if (isset(self::$jsTmpArr[$id]['src']) || isset(self::$jsTmpArr[$id]['js'])) {
                        self::deferRecursive($id);
                }
            }
        }
    }
    static function addId($id, $val)
    {
        if(isset(self::$idsArr[$id]))
            self::debug('Double ID "'.$id.'" found! Use unique ID for "'.$val.'"');
        self::$idsArr[$id] = true;
    }
    
    // JS-function to provide multi-dimensional dependence of defered script-srcs and scripts
    static function getJsFunctions($cssStr, $scriptSrcStr)
    {
        $deferImages = self::opt('deferImages') ? self::getDeferImagesFunction() : '';
        
        return "
    <script>
        try {
            var css = {$cssStr};
            var js = {$scriptSrcStr};
    
            var element = {}; var l = {}; var p = 0; var c = 0; var cx = 0; var id = 0; var done = false;
            
            function deferCssSrc() {
                if(css.length) {
                    for (c = 0; c < css.length; c++) {
                        l = document.createElement('link');
                        l.rel = 'stylesheet';
                        l.href = css[c]['src'];
                        h = document.getElementsByTagName('head')[0];
                        h.parentNode.insertBefore(l, h);
                        " . self::console('css_added') . "
                    }
                }
            }
                
            function deferScriptSrc(p) {
                if (js[p].hasOwnProperty('src')) {
                    for (c in js[p]['src']) {
                        id = js[p]['src'][c]['id'] != undefined ? js[p]['src'][c]['id'] : 'src_'+p+'_'+c;
                        val = js[p]['src'][c]['val'];
                        element[id] = document.createElement('script');
                        element[id].src = val;
                        document.body.appendChild(element[id]);
                        " . self::console('script_src_added_with_id') . "
                        deferScript(p);
                        addOnLoadHandler(id);
                    }
                }
            }
            
            function deferScript(p) {
                if (js[p].hasOwnProperty('js')) {
                    for (c in js[p]['js']) {
                        id = js[p]['js'][c]['id'] != undefined ? js[p]['js'][c]['id'] : 'js_'+p+'_'+c;
                        val = js[p]['js'][c]['val'];
                        element[id] = document.createElement('script');
                        element[id].text = val;
                        document.body.appendChild(element[id]);
                        " . self::console('script_added_with_id') . "
                    }
                    delete js[p]['js'];
                }
            }
            
            function deferRecursive(p) {
                if (js.hasOwnProperty(p)) {
                    if (js[p].hasOwnProperty('src') || js[p].hasOwnProperty('js')) {
                        deferScriptSrc(p);
                        deferScript(p);
                    }
                }
            }
            
            function addOnLoadHandler(id) {
                if(element[id] == undefined) {
                    " . self::console('element_undefined_id') . "
                } else {
                    if (js.hasOwnProperty(id)) {
                        if(js[id].hasOwnProperty('src') || js[id].hasOwnProperty('js')) {
                            " . self::console('recursive_chain_for_id') . "
                            element[id].onload=element[id].onreadystatechange = function() {
                                if ( !done && (!this.readyState || this.readyState == 'loaded' || this.readyState == 'complete') ) {
                                    " . self::console('process_scripts_with_dependson_id') . "
                                    deferRecursive(id);
                                }
                            }
                        }
                    }
                }
            }
            
            function downloadDeferedAtOnload() {
                " . self::console('init') . self::console('log_cssSrc') . self::console('log_js') . "
                {$deferImages}
                deferCssSrc();
                if(typeof js['0'] == 'object') {
                    deferRecursive('0');
                }
            }
            
            if (window.addEventListener)
                window.addEventListener('load', downloadDeferedAtOnload, false);
            else if (window.attachEvent)
                window.attachEvent('onload', downloadDeferedAtOnload);
            else window.onload = downloadDeferedAtOnload;
        } catch(e) {
            console.log(e);
        }
    </script>";
    }
    
    static function getDeferImagesFunction() {
        return self::console('defer_images') ."
        var imgDefer = document.getElementsByTagName('img');
        for (var i=0; i<imgDefer.length; i++) {
            if(imgDefer[i].getAttribute('data-src')) {
                imgDefer[i].setAttribute('src',imgDefer[i].getAttribute('data-src'));
            } 
        }
        ";
    }

    // Prepare console logs for debugging
    static function console($key)
    {
        global $modx;

        // @todo: check/clean messages-block ?

        if(self::opt('sessionAuth') && self::opt('debug')) {
            $c = '';
            switch($key) {
                case 'init':
                    $c = "'deferMinifyX v".self::getVersion()." - onLoad fired - Debug messages enabled, disable for production!'"; $a = "warn"; break;
                case 'log_cssSrc':
                    $c = "'var css = ',css"; $a = "info"; break;
                case 'log_js':
                    $c = "'var js = ', js"; $a = "info"; break;
                case 'defer_images':
                    $c = "'Set defer images'"; $a = "log"; break;
                case 'css_added':
                    $c = "'CSS defered '+l.href"; $a = "log"; break;
                case 'process_script_without':
                    $c = "'process scripts[0] (without dependsOn)'"; $a = "log"; break;
                case 'recursive_chain_for_id':
                    $c = "'recursive chaining \"'+id+'\"'"; $a = "log"; break;
                case 'set_onload_script_src_for_p':
                    $c = "'set onLoad js for id \"'+p+'\"'"; $a = "log"; break;
                case 'id_loaded_process_js':
                    $c = "'\"'+p+'\" loaded, process js with dependsOn \"'+p+'\"'"; $a = "log"; break;
                case 'script_src_added_with_id':
                    $c = "'js['+p+'] \"'+val+'\" added with id \"'+id+'\"'"; $a = "log"; break;
                case 'process_scripts_with_dependson_id':
                    $c = "'process Scripts with dependsOn \"'+id+'\"'"; $a = "log"; break;
                case 'script_added_with_id':
                    $c = "'script added with id \"'+id+'\"'"; $a = "log"; break;
                case 'element_undefined_p':
                    $c = "'! element \"'+p+'\" undefined'"; $a = "error"; break;
                case 'element_undefined_id':
                    $c = "'! element \"'+id+'\" undefined'"; $a = "error"; break;
                default:
                    $c = "'Debug: \"{$key}\"'"; $a = "info"; break;
            }
            return "console.{$a}({$c});";
        };
        return '';
    }

    static function renderDebugMsg()
    {
        global $modx;

        // ADD DEBUG-INFO AS HTML-COMMENTS ONLY WHEN LOGGED IN
        if (self::opt('sessionAuth') && self::opt('debug')) {
            return '
<!-- ......................................................... deferMinifyX Debug:
Items in $idsArr:
' . print_r(self::$idsArr, true) . '
..................................................................................
Items in $jsArr[dependsOn]:
' . print_r(self::$jsArr, true) . '
..................................................................................
Items in $cssArr:
' . print_r(self::$cssArr, true) . '
..................................................................................
$options:
' . print_r(self::$options, true) . '
..................................................................................
Debug-Messages:
 - ' . join("\n - ", self::$debugMessages) . '
.......................................................... /deferMinifyX Debug -->
';
        };
        return '';
    }

    static function resetBuffer()
    {
        self::$buffer = '';
    }
}