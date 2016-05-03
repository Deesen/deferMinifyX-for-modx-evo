<?php
/**
 * deferMinifyX
 *
 * @version     0.2
 * @license     http://www.gnu.org/copyleft/gpl.html GNU Public License (GPL)
 * @author      Deesen / updated: 2016-05-01
 *
 * Latest Updates / Issues on Github : https://github.com/Deesen/deferMinifyX-for-xxxxxxx
 */

class deferMinifyX
{
    static $buffer = '';
    static $cache = NULL;
    static $cacheChanged = false;
    static $cssArr = array();
    static $cssCacheChanged = false;
    static $debugMessages = array();
    static $debugTpl = '';
    static $idsArr = array();
    static $jsArr = array();
    static $jsCacheChanged = false;
    static $options = array();
    private static $version = '0.2';

    static function addScriptSrc($string, $id=NULL, $dependsOn=NULL, $unique=true, $defer=false, $async=false)
    {
        if($string === '') return;

        $arr = explode(',', $string);
        $id         = !$id || count($arr) >> 1  ? NULL  : $id;
        $dependsOn  = !$dependsOn               ? '0'   : $dependsOn;

        foreach($arr as $file) {

            $file  = explode('||', $file);
            $value = $file[0];
            $defer = in_array('defer', $file) ? true : $defer;
            $async = in_array('async', $file) ? true : $async;

            $found = $unique != false ? self::checkUniqueJs($value, 'src') : false;
            if (!$found) {
                $new = array();
                $new['val'] = $value;
                if ($id) $new['id'] = $id;
                if ($defer) $new['defer'] = true;
                if ($async) $new['async'] = true;
                self::$jsArr[$dependsOn]['src'][] = $new;
            }
        }
    }

    static function addScript($string, $id=NULL, $dependsOn=NULL, $unique=true)
    {
        if($string === '') return;

        $id         = !$id          ? NULL  : $id;
        $dependsOn  = !$dependsOn   ? '0'   : $dependsOn;
        $found = $unique != false ? self::checkUniqueJs($string, 'js') : false;

        if (!$found) {
            $newJs = array();
            $newJs['val'] = $string;
            if ($id) $newJs['id'] = $id;
            self::$jsArr[$dependsOn]['js'][] = $newJs;
        }
    }

    static function checkUniqueJs($string, $type)
    {
        $found = false;
        foreach(self::$jsArr as $dependsOn=>$set) {
            if(isset($set[$type])) {
                foreach ($set[$type] as $script) {
                    if($script['val'] === $string) {
                        $found = true;
                        break;
                    }
                }
            }
        }
        return $found;
    }

    static function addCssSrc($string, $unique=true, $defer=false, $async=false, $type='')
    {
        if($string === '') return;

        $arr = explode(',', $string);

        foreach($arr as $file) {

            $file = explode('||', $file);
            $src = $file[0];
            $defer = in_array('defer', $file) ? true : $defer;
            $async = in_array('async', $file) ? true : $async;

            if(!empty($src)) {
                $found = false;
                if ($unique != false) {
                    foreach (self::$cssArr as $css) {
                        if ($css['src'] === $src) {
                            $found = true;
                            break;
                        }
                    }
                }
                if (!$found) {
                    $new = array();
                    $new['src'] = $src;
                    if ($defer) $new['defer'] = true;
                    if ($async) $new['async'] = true;
                    $new['type'] = $type;
                    self::$cssArr[] = $new;
                }
            }
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
        if(empty(self::$options))
            self::$options = $optionsArr;
    }

    static function setOption($option, $val)
    {
        if(!in_array($option, array('core_path','base_path','cache_path','sessionAuth')))
            self::$options[$option] = $val;
    }

    static function setDebugTpl($tpl)
    {
        self::$debugTpl = $tpl;
    }

    static function modifyOutput($output)
    {
        // @todo: case-insensitive replace </body> / </bODy>
        if(self::opt('defer')) {
            $outputArr = self::getDefer();

            $output = deferMinifyX::replaceImgSrcAttributesForDeferImages($output);
            $noScriptCss = self::opt('noScriptCss') ? self::getLinkTags(true) : '';
            $output = str_replace('</body>', $outputArr['output'] . $noScriptCss . $outputArr['debug'] . '</body>', $output);
        } else {
            $output = str_replace('</head>', self::getLinkTags() . '</head>', $output);
            $output = str_replace('</body>', self::getScriptTags() . self::renderDebugMsg() . '</body>', $output);
        }
        return $output;
    }

    static function getLinkTags($addNoScript=false)
    {
        $output = $addNoScript ? "\n<noscript>\n" : '';
        foreach(self::$cssArr as $css) {
            $defer = isset($css['defer']) ? ' defer="defer"' : '';
            $async = isset($css['async']) ? ' async="async"' : '';
            $output .= !empty($css['src']) ? ($addNoScript?'  ':'').'<link rel="stylesheet" href="'. $css['src'] .'"'.$defer.$async.'>'."\n" : '';
        }
        $output .= $addNoScript ? "</noscript>\n" : '';
        return $output;
    }

    static function getScriptTags()
    {
        if(isset(self::$jsArr[0])) {
            self::resetBuffer();
            self::deferRecursive(0); // will use self::$buffer
            self::deferRecursive('min'); // add scripts that depend on min
        }
        return self::$buffer;
    }

    static function getDefer()
    {
        if(defined('DEFERMINIFYX_GET')) return '';
        define('DEFERMINIFYX_GET',1);

        // Init vars
        $minCss     = self::opt('minifyCssFile');
        $minFile    = self::opt('minifyJsFile');
        $jsonIndent = self::opt('debug') != false ? JSON_PRETTY_PRINT : 0;

        /////////////////////////////////////////////////////////////////////
        // Prepare CSS-object
        if(self::opt('minifyCss')) {

            if(self::$cache['min']['cssSetup'] != md5(self::opt('defaultCssFiles')))
                self::$cssCacheChanged = true;

            // Check cache for changed CSS files
            if(!file_exists(self::opt('base_path') . $minCss)) {
                self::$cssCacheChanged = true;
            } else if (!empty(self::$cssArr)) {
                foreach (self::$cssArr as $file) {
                    if($file['type'] != 'default') continue;
                    $filePath = self::opt('base_path') . $file['src'];
                    if (file_exists($filePath)) {
                        $time = (int)filemtime($filePath);
                        if ($time > self::$cache['min']['css']) {
                            self::$cssCacheChanged = true;
                            self::$cache['min']['css'] = $time;
                        }
                    } else {
                        self::debug('CSS file not found: ' . $file['src']);
                    }
                }
            }

            // Create new min.css
            if (self::$cssCacheChanged) {
                self::resetBuffer();

                foreach (self::$cssArr as $file) {
                    if($file['type'] != 'default') continue;
                    $fileContent = self::getMinifiedAndCachedFile($file['src'], 'css');
                    self::$buffer .= self::$buffer != '' ? "\n" : '';
                    self::$buffer .= $fileContent;
                }

                if (!file_put_contents(self::opt('base_path') . $minCss, self::$buffer)) self::debug('Minified Css-File could not be written: ' . $minCss);

                self::$cache['min']['cssSetup'] = md5(self::opt('defaultCssFiles'));
            }

            // Overwrite $cssArr - set min.css as first file
            $newCssArr = array();
            $newCssArr[0] = array(
                'src'=>$minCss . '?' . self::opt('hashParam') . self::$cache['min']['css']
            );
            foreach (self::$cssArr as $file) {
                if($file['type'] == 'default') continue;
                $newCssArr[] = array('src'=>$file['src']);
            }
            self::$cssArr = $newCssArr;
        }

        /////////////////////////////////////////////////////////////////////
        // Prepare JS-object - minify files into min.js 
        if(self::opt('minifyJs')) {

            if(self::$cache['min']['jsSetup'] != md5(self::opt('defaultJsFiles')))
                self::$jsCacheChanged = true;

            // Check for changed files
            if(!file_exists(self::opt('base_path') . $minFile)) {
                self::$jsCacheChanged = true;
            } else if(!empty(self::$jsArr)) {
                foreach (self::$jsArr as $dependsOn => $arr) {
                    if ($dependsOn != '0') continue;       // Skip none-default files
                    if (isset($arr['src'])) {
                        foreach ($arr['src'] as $scriptSrc) {
                            $filePath = self::opt('base_path') . $scriptSrc['val'];
                            if (file_exists($filePath)) {
                                $time = (int)filemtime($filePath);
                                if ($time > self::$cache['min']['src']) {
                                    self::$jsCacheChanged = true;
                                    self::$cache['min']['src'] = $time;
                                }
                            } else {
                                self::debug('CSS file not found: ' . $scriptSrc['val']);
                            }
                        }
                    }
                }
            }

            // Calculate MD5-Hash for Inline-Script 
            $buffer = '';
            foreach (self::$jsArr as $dependsOn => $jsArr) {
                if (isset($jsArr['js'])) {
                    foreach ($jsArr['js'] as $script) {
                        if (!empty($script['val']))
                            $buffer .= $script['val'];
                    }
                }
            }
            $md5 = md5($buffer);
            if ($md5 != self::$cache['min']['js']) {
                self::$jsCacheChanged = true;
                self::$cache['min']['js'] = $md5;
            }

            // Create new min.js
            if (self::$jsCacheChanged) {
                self::resetBuffer();

                // Sort equivalent to JS-functions
                if (isset(self::$jsArr[0])) {
                    self::deferRecursive(0); // will use self::$buffer
                }

                if (!file_put_contents(self::opt('base_path') . $minFile, self::$buffer)) self::debug('Minified Js-File could not be written: ' . $minFile);

                self::$cache['min']['jsSetup'] = md5(self::opt('defaultJsFiles'));
            }

            // Overwrite $jsArr[0] - set min.js as only file - append dependsOn min events
            self::$jsArr[0] = array();
            self::$jsArr[0]['src'][0]['val'] = $minFile . '?' . self::opt('hashParam') . self::$cache['min']['src'];
            self::$jsArr[0]['src'][0]['id']  = 'min';
        }

        // Prepare JSON-strings
        $cssStr = json_encode(self::$cssArr, $jsonIndent);
        $scriptSrcStr = json_encode(self::$jsArr, $jsonIndent);

        // Get JS-chaining magic
        if (self::opt('minify') && self::opt('minifyDefer') && !self::opt('debug')) {
            $output = self::getMinifiedJsFunctions($cssStr, $scriptSrcStr);
        } else {
            $output = self::getJsFunctions($cssStr, $scriptSrcStr);
        }

        // Return array to get() to enable individual handling of content
        return array(
            'output'=>$output,
            'debug'=>self::renderDebugMsg()
        );
    }

    // Loads cached timestamps/hashes
    static function loadCache()
    {
        if(self::opt('cache') && empty(self::$cache)) {
            $cacheFile = self::opt('cache_path') . 'cache.php';
            if (file_exists($cacheFile)) {
                include($cacheFile);
                if(isset($cache)) {
                    self::$cache = $cache;
                } else {
                    self::debug('Cache-file empty?');
                }
            }
        }
    }

    // Write/update timestamps/hashes
    static function updateCache()
    {
        if(self::opt('cache') && !empty(self::$cache)) {
            if (self::$cacheChanged || self::$cssCacheChanged || self::$jsCacheChanged) {
                if (!file_exists(self::opt('cache_path'))) {
                    if (mkdir(self::opt('cache_path'), 0777, true)) {
                        self::debug('Cache-Path created');
                    } else {
                        self::debug('Cache-Path could not be created');
                    };
                }
                $cacheFile = self::opt('cache_path') . 'cache.php';
                if (!file_put_contents($cacheFile, '<?php $cache='.var_export(self::$cache, true).' ?>')) self::debug('Cache-File could not be written');
            }
        }
    }

    // Loads cached timestamps/hashes
    static function resetCache()
    {
        if(self::opt('cache')) {
            $cacheFile = self::opt('cache_path') . 'cache.php';

            if(self::opt('cacheReset') == 'all') {
                self::loadCache();
                if(isset(self::$cache['files'])) {
                    foreach (self::$cache['files'] as $file => $x) {
                        $filePath = self::opt('cache_path') . $file;
                        if (is_readable($filePath)) {
                            if(!unlink($filePath)) self::debug('File could not be deleted: '.$file);
                        } else {
                            self::debug('Cached file "'.$file.'" not readable?');
                        }
                    }
                }
            }

            if(self::opt('cacheReset') == 'index' || self::opt('cacheReset') == 'all') {
                if (is_readable($cacheFile)) {
                    if(!unlink($cacheFile)) self::debug('Cache-file could not be deleted');
                } else {
                    self::debug('Cache-file not readable?');
                }
            }
        }
    }

    // Checks cache for already minified files and sends file back or creates new
    static function getMinifiedAndCachedFile($file, $type)
    {
        $filePath = self::opt('base_path') . $file;
        $output = '';

        if (file_exists($filePath)) {
            if (self::opt('cache')) {
                if(!isset(self::$cache['files'][$file])) self::$cache['files'][$file] = 0;
                $cachedFile = self::opt('cache_path') . $file;
                if(!file_exists($cachedFile)) self::$cache['files'][$file] = 0;
                $time = (int)filemtime($filePath);
                if ($time > self::$cache['files'][$file]) {
                    self::$cache['files'][$file] = $time;
                    self::$cacheChanged = true;

                    switch($type) {
                        case 'js':
                            $output = self::minifyJs(file_get_contents($filePath));
                            break;
                        case 'css':
                            $output = self::minifyCss(file_get_contents($filePath));
                            break;
                    }

                    if (self::putContentsWithMakeDirectory($cachedFile, $output)) {
                        $filePath = $cachedFile;
                    } else {
                        self::debug('Minified File could not be written to cache path');
                    }
                } else {
                    $filePath = $cachedFile;
                }
            }
            $output = file_get_contents($filePath);
        }
        return $output;
    }

    // adds ".min" to file-extenion
    static function addMinToFilename($filename)
    {
        $ext = strrchr($filename, '.');
        if($ext) {
            $filename = str_replace($ext, '.min'.$ext, $filename);
        }
        return $filename;
    }

    static function putContentsWithMakeDirectory($filePath, $content)
    {
        $path = dirname($filePath);

        // dir doesn't exist, create it
        if (!is_dir($path)) {
            if(!mkdir($path, 0777, true)) {
                self::debug('Directory could not be created :'.$filePath);
            };
        }
        if(!file_put_contents($filePath, $content)) {
            return false;
        }
        return true;
    }

    /////////////////////////////////////////////////////////////
    // Same names as JS-functions / equivalent sorting mechanismn
    static function deferScriptSrc($p) {
        if (isset(self::$jsArr[$p]['src'])) {
            foreach (self::$jsArr[$p]['src'] as $i=>$c) {
                if (isset(self::$jsArr[$p]['src'][$i]['inits'])) {
                    self::$jsArr[$p]['src'][$i]['inits'] += 1;
                    continue;
                }
                $id = isset($c['id']) ? $c['id'] : 'src_' . $p . '_' . $i;
                self::addId($id, $c['val']);

                if (self::opt('defer')) {
                    $fileContent = self::getMinifiedAndCachedFile($c['val'], 'js');
                    self::$buffer .= self::$buffer != '' ? "\n;" : '';
                    self::$buffer .= $fileContent;
                } else {
                    $defer = isset($c['defer']) ? ' defer="defer"' : '';
                    $async = isset($c['async']) ? ' async="async"' : '';
                    self::$buffer .= '<script src="' . $c['val'] . '"'.$defer.$async.'></script>';
                }
                self::$jsArr[$p]['src'][$i]['inits'] = 1;
            }
            self::deferScript($p);
            foreach (self::$jsArr[$p]['src'] as $i=>$c) {
                $id = isset($c['id']) ? $c['id'] : 'src_' . $p . '_' . $i;
                self::addOnLoadHandler($id);
            }
        }
    }
    static function deferScript($p) {
        if (isset(self::$jsArr[$p]['js'])) {
            foreach (self::$jsArr[$p]['js'] as $i=>$c) {
                if (isset(self::$jsArr[$p]['js'][$i]['inits'])) {
                    self::$jsArr[$p]['js'][$i]['inits'] += 1;
                    continue;
                }
                $id = isset($c['id']) ? $c['id'] : 'src_'.$p.'_'.$i;
                self::addId($id, $c['val']);
                if(self::opt('defer')) {
                    self::$buffer .= $c['val'];
                } else {
                    $defer = isset($c['defer']) ? ' defer="defer"' : '';
                    self::$buffer .= '<script'.$defer.'>'. $c['val'] .'</script>';
                }
                self::$jsArr[$p]['js'][$i]['inits'] = 1;
                self::addOnLoadHandler($id);
            }
        }
    }
    static function deferRecursive($p) {
        if (isset(self::$jsArr[$p])) {
            if (isset(self::$jsArr[$p]['src']) || isset(self::$jsArr[$p]['js'])) {
                self::deferScriptSrc($p);
                self::deferScript($p);
            }
        }
    }
    static function addOnLoadHandler($id) {
        if(!isset(self::$idsArr[$id])) {
            self::debug('Element "'.$id.'" undefined');
        } else {
            if (isset(self::$jsArr[$id])) {
                if (isset(self::$jsArr[$id]['src']) || isset(self::$jsArr[$id]['js'])) {
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
                        h.appendChild(l, h);
                        " . self::console('css_added') . "
                    }
                }
            }
                
            function deferScriptSrc(p) {
                if (js[p].hasOwnProperty('src')) {
                    for (c in js[p]['src']) {
                        if (js[p]['src'][c]['inits'] != undefined) {
                            js[p]['src'][c]['inits'] += 1;
                            continue;
                        }
                        id = js[p]['src'][c]['id'] != undefined ? js[p]['src'][c]['id'] : 'src_' + p + '_' + c;
                        val = js[p]['src'][c]['val'];
                        element[id] = document.createElement('script');
                        element[id].src = val;
                        document.body.appendChild(element[id]);
                        js[p]['src'][c]['inits'] = 1;
                        " . self::console('script_src_added_with_id') . "
                        addOnLoadHandler(id);
                    }
                }
            }
            
            function deferScript(p) {
                if (js[p].hasOwnProperty('js')) {
                    for (c in js[p]['js']) {
                        if(js[p]['js'][c]['inits'] != undefined) { js[p]['js'][c]['inits'] += 1; continue; }
                        id = js[p]['js'][c]['id'] != undefined ? js[p]['js'][c]['id'] : 'js_'+p+'_'+c;
                        val = js[p]['js'][c]['val'];
                        element[id] = document.createElement('script');
                        element[id].text = val;
                        document.body.appendChild(element[id]);
                        js[p]['js'][c]['inits'] = 1;
                        " . self::console('script_added_with_id') . "
                        addOnLoadHandler(id);
                    }
                }
            }
            
            function deferRecursive(p) {
                if (js.hasOwnProperty(p)) {
                    if (js[p].hasOwnProperty('src') || js[p].hasOwnProperty('js')) {
                        " . self::console('recursive_chain_for_id') . "
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
                            if(!element[id].src) {
                                deferRecursive(id);
                            } else {
                                element[id].onload=element[id].onreadystatechange = function() {
                                    if ( !done && (!this.readyState || this.readyState == 'loaded' || this.readyState == 'complete') ) {
                                        deferRecursive(id);
                                    }
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

    // Provide compressed version of functions!
    static function getMinifiedJsFunctions($cssStr, $scriptSrcStr)
    {
        $deferImages = self::opt('deferImages') ? self::getDeferImagesFunction() : '';

        return "
    <script>try{var css={$cssStr};var js={$scriptSrcStr};var element={};var l={};var p=0;var c=0;var cx=0;var id=0;var done=false;function deferCssSrc(){if(css.length){for(c=0;c<css.length;c++){l=document.createElement(\"link\");l.rel=\"stylesheet\";l.href=css[c][\"src\"];h=document.getElementsByTagName(\"head\")[0];h.appendChild(l,h)}}}function deferScriptSrc(a){if(js[a].hasOwnProperty(\"src\")){for(c in js[a][\"src\"]){if(js[a][\"src\"][c][\"inits\"]!=undefined){js[a][\"src\"][c][\"inits\"]+=1;continue}id=js[a][\"src\"][c][\"id\"]!=undefined?js[a][\"src\"][c][\"id\"]:\"src_\"+a+\"_\"+c;val=js[a][\"src\"][c][\"val\"];element[id]=document.createElement(\"script\");element[id].src=val;document.body.appendChild(element[id]);js[a][\"src\"][c][\"inits\"]=1;addOnLoadHandler(id)}}}function deferScript(a){if(js[a].hasOwnProperty(\"js\")){for(c in js[a][\"js\"]){if(js[a][\"js\"][c][\"inits\"]!=undefined){js[a][\"js\"][c][\"inits\"]+=1;continue}id=js[a][\"js\"][c][\"id\"]!=undefined?js[a][\"js\"][c][\"id\"]:\"js_\"+a+\"_\"+c;val=js[a][\"js\"][c][\"val\"];element[id]=document.createElement(\"script\");element[id].text=val;document.body.appendChild(element[id]);js[a][\"js\"][c][\"inits\"]=1;addOnLoadHandler(id)}}}function deferRecursive(a){if(js.hasOwnProperty(a)){if(js[a].hasOwnProperty(\"src\")||js[a].hasOwnProperty(\"js\")){deferScriptSrc(a);deferScript(a)}}}function addOnLoadHandler(a){if(element[a]==undefined){}else{if(js.hasOwnProperty(a)){if(js[a].hasOwnProperty(\"src\")||js[a].hasOwnProperty(\"js\")){if(!element[a].src){deferRecursive(a)}else{element[a].onload=element[a].onreadystatechange=function(){if(!done&&(!this.readyState||this.readyState==\"loaded\"||this.readyState==\"complete\")){deferRecursive(a)}}}}}}}function downloadDeferedAtOnload(){{$deferImages}deferCssSrc();if(typeof js[\"0\"]==\"object\"){deferRecursive(\"0\")}}if(window.addEventListener){window.addEventListener(\"load\",downloadDeferedAtOnload,false)}else{if(window.attachEvent){window.attachEvent(\"onload\",downloadDeferedAtOnload)}else{window.onload=downloadDeferedAtOnload}}}catch(e){console.log(e)};</script>";
    }

    static function getDeferImagesFunction()
    {
        return self::console('defer_images') ."
                var imgDefer = document.getElementsByTagName('img');
                for (var i=0; i<imgDefer.length; i++) {
                    if(imgDefer[i].getAttribute('data-src')) {
                        imgDefer[i].setAttribute('src',imgDefer[i].getAttribute('data-src'));
                    } 
                }
        ";
    }
    static function replaceImgSrcAttributesForDeferImages($output)
    {
        if(self::opt('deferImages')) {
            $matches = array();
            if (preg_match_all('/<img[^>]+>/i', $output, $matches)) {
                if(!empty($matches)) {
                    foreach ($matches[0] as $i => $imgTag) {
                        // @todo: dynamically add height/width if not given to assure blankImage has original size ? 
                        $newTag = $imgTag;
                        $newTag = str_replace('src="', 'src="' . self::opt('blankImage') . '" data-src="', $newTag);
                        $newTag = str_replace("src='", "src='" . self::opt('blankImage') . "' data-src='", $newTag);
                        $output = str_replace($imgTag, $newTag, $output);
                    }
                }
            }
        }
        return $output;
    }

    // Prepare libs for minification if enabled
    static function prepareMinifyLibs()
    {
        if(self::opt('minify')) {

            // Prepare minify Css
            switch(self::opt('minifyCssLib')) {
                case 'minifier':
                default:
                    require_once(self::opt('core_path').'class/minifier/CSSmin.php'); // https://github.com/mrclay/minify 
            }

            // Prepare minify Js
            switch(self::opt('minifyJsLib')) {
                case 'jshrink':
                    require_once(self::opt('core_path').'class/jshrink/JShrink.php'); // https://github.com/tedious/JShrink
                    break;
                case 'jsminplus':
                    require_once(self::opt('core_path').'class/minifier/JSMinPlus.php');
                    break;
                case 'jsmin':
                default:
                    require_once(self::opt('core_path').'class/minifier/JSMin.php');
            }

            // Prepare minify Html
            switch(self::opt('minifyHtml')) {
                case 'minifier':
                    require_once(self::opt('core_path').'class/minifier/HTML.php');
            }
        }
    }

    // Returns minified Js-strings
    static function minifyCss($string)
    {
        if(!self::opt('minify')) return $string;

        switch(self::opt('minifyCssLib')) {
            case 'regex':
                return self::minify_css($string);
            case 'minifier':
            default:
                $CSSmin = new CSSmin();
                return $CSSmin->run($string);
        }
    }

    // Returns minified Js-strings
    static function minifyJs($string)
    {
        if(!self::opt('minify')) return $string;

        switch(self::opt('minifyJsLib')) {
            case 'regex':
                return self::minify_js($string);
            case 'jshrink':
                return \JShrink\Minifier::minify($string);
            case 'jsminplus':
                return JSMinPlus::minify($string);
            case 'jsmin':
            default:
                return JSMin::minify($string);
        }
    }

    // Returns minified HTML-code
    static function minifyHtml($string)
    {
        if(!self::opt('minify')) return $string;

        switch(self::opt('minifyHtml')) {
            case 'minifier':
                $out = Minify_HTML::minify($string);
                break;
            case 'regex':
                $out = self::minify_html($string);
                break;
            default:
                $out = $string;
        }

        if(self::opt('debug')) {
            $inLength = strlen($string);
            $outLength = strlen($out);
            self::debug('HTML: Original ' . $inLength . ' Bytes - Minified ' . $outLength . ' Bytes - Difference ' . $inLength - $outLength . ' Bytes');
        }

        return $out;
    }

    // https://gist.github.com/tovic/d7b310dea3b33e4732c0
    public static function minify_html($input)
    {
        if(trim($input) === "") return $input;
        // Remove extra white-space(s) between HTML attribute(s)
        $input = preg_replace_callback('#<([^\/\s<>!]+)(?:\s+([^<>]*?)\s*|\s*)(\/?)>#s', function($matches) {
            return '<' . $matches[1] . preg_replace('#([^\s=]+)(\=([\'"]?)(.*?)\3)?(\s+|$)#s', ' $1$2', $matches[2]) . $matches[3] . '>';
        }, str_replace("\r", "", $input));

        // Minify inline CSS declaration(s)
        if(strpos($input, ' style=') !== false) {
            $input = preg_replace_callback('#<([^<]+?)\s+style=([\'"])(.*?)\2(?=[\/\s>])#s', function($matches) {
                return '<' . $matches[1] . ' style=' . $matches[2] . self::minify_css($matches[3]) . $matches[2];
            }, $input);
        }
        return preg_replace(
            array(
                // t = text
                // o = tag open
                // c = tag close
                // Keep important white-space(s) after self-closing HTML tag(s)
                '#<(img|input)(>| .*?>)#s',
                // Remove a line break and two or more white-space(s) between tag(s)
                '#(<!--.*?-->)|(>)(?:\n*|\s{2,})(<)|^\s*|\s*$#s',
                '#(<!--.*?-->)|(?<!\>)\s+(<\/.*?>)|(<[^\/]*?>)\s+(?!\<)#s', // t+c || o+t
                '#(<!--.*?-->)|(<[^\/]*?>)\s+(<[^\/]*?>)|(<\/.*?>)\s+(<\/.*?>)#s', // o+o || c+c
                '#(<!--.*?-->)|(<\/.*?>)\s+(\s)(?!\<)|(?<!\>)\s+(\s)(<[^\/]*?\/?>)|(<[^\/]*?\/?>)\s+(\s)(?!\<)#s', // c+t || t+o || o+t -- separated by long white-space(s)
                '#(<!--.*?-->)|(<[^\/]*?>)\s+(<\/.*?>)#s', // empty tag
                '#<(img|input)(>| .*?>)<\/\1>#s', // reset previous fix
                '#(&nbsp;)&nbsp;(?![<\s])#', // clean up ...
                '#(?<=\>)(&nbsp;)(?=\<)#', // --ibid
                // Remove HTML comment(s) except IE comment(s)
                '#\s*<!--(?!\[if\s).*?-->\s*|(?<!\>)\n+(?=\<[^!])#s'
            ),
            array(
                '<$1$2</$1>',
                '$1$2$3',
                '$1$2$3',
                '$1$2$3$4$5',
                '$1$2$3$4$5$6$7',
                '$1$2$3',
                '<$1$2',
                '$1 ',
                '$1',
                ""
            ),
            $input);
    }

    // CSS Minifier => http://ideone.com/Q5USEF + improvement(s)
    public static function minify_css($input) {
        if(trim($input) === "") return $input;
        return preg_replace(
            array(
                // Remove comment(s)
                '#("(?:[^"\\\]++|\\\.)*+"|\'(?:[^\'\\\\]++|\\\.)*+\')|\/\*(?!\!)(?>.*?\*\/)|^\s*|\s*$#s',
                // Remove unused white-space(s)
                '#("(?:[^"\\\]++|\\\.)*+"|\'(?:[^\'\\\\]++|\\\.)*+\'|\/\*(?>.*?\*\/))|\s*+;\s*+(})\s*+|\s*+([*$~^|]?+=|[{};,>~+]|\s*+-(?![0-9\.])|!important\b)\s*+|([[(:])\s++|\s++([])])|\s++(:)\s*+(?!(?>[^{}"\']++|"(?:[^"\\\]++|\\\.)*+"|\'(?:[^\'\\\\]++|\\\.)*+\')*+{)|^\s++|\s++\z|(\s)\s+#si',
                // Replace `0(cm|em|ex|in|mm|pc|pt|px|vh|vw|%)` with `0`
                '#(?<=[\s:])(0)(cm|em|ex|in|mm|pc|pt|px|vh|vw|%)#si',
                // Replace `:0 0 0 0` with `:0`
                '#:(0\s+0|0\s+0\s+0\s+0)(?=[;\}]|\!important)#i',
                // Replace `background-position:0` with `background-position:0 0`
                '#(background-position):0(?=[;\}])#si',
                // Replace `0.6` with `.6`, but only when preceded by `:`, `,`, `-` or a white-space
                '#(?<=[\s:,\-])0+\.(\d+)#s',
                // Minify string value
                '#(\/\*(?>.*?\*\/))|(?<!content\:)([\'"])([a-z_][a-z0-9\-_]*?)\2(?=[\s\{\}\];,])#si',
                '#(\/\*(?>.*?\*\/))|(\burl\()([\'"])([^\s]+?)\3(\))#si',
                // Minify HEX color code
                '#(?<=[\s:,\-]\#)([a-f0-6]+)\1([a-f0-6]+)\2([a-f0-6]+)\3#i',
                // Replace `(border|outline):none` with `(border|outline):0`
                '#(?<=[\{;])(border|outline):none(?=[;\}\!])#',
                // Remove empty selector(s)
                '#(\/\*(?>.*?\*\/))|(^|[\{\}])(?:[^\s\{\}]+)\{\}#s'
            ),
            array(
                '$1',
                '$1$2$3$4$5$6$7',
                '$1',
                ':0',
                '$1:0 0',
                '.$1',
                '$1$3',
                '$1$2$4$5',
                '$1$2$3',
                '$1:0',
                '$1$2'
            ),
            $input);
    }

    // JavaScript Minifier
    public static function minify_js($input) {
        if(trim($input) === "") return $input;
        return preg_replace(
            array(
                // Remove comment(s)
                '#\s*("(?:[^"\\\]++|\\\.)*+"|\'(?:[^\'\\\\]++|\\\.)*+\')\s*|\s*\/\*(?!\!|@cc_on)(?>[\s\S]*?\*\/)\s*|\s*(?<![\:\=])\/\/.*(?=[\n\r]|$)|^\s*|\s*$#',
                // Remove white-space(s) outside the string and regex
                '#("(?:[^"\\\]++|\\\.)*+"|\'(?:[^\'\\\\]++|\\\.)*+\'|\/\*(?>.*?\*\/)|\/(?!\/)[^\n\r]*?\/(?=[\s.,;]|[gimuy]|$))|\s*([!%&*\(\)\-=+\[\]\{\}|;:,.<>?\/])\s*#s',
                // Remove the last semicolon
                '#;+\}#',
                // Minify object attribute(s) except JSON attribute(s). From `{'foo':'bar'}` to `{foo:'bar'}`
                '#([\{,])([\'])(\d+|[a-z_][a-z0-9_]*)\2(?=\:)#i',
                // --ibid. From `foo['bar']` to `foo.bar`
                '#([a-z0-9_\)\]])\[([\'"])([a-z_][a-z0-9_]*)\2\]#i'
            ),
            array(
                '$1',
                '$1$2',
                '}',
                '$1$3',
                '$1.$3'
            ),
            $input);
    }

    // Sends images as base64
    static function getImageBase64($file)
    {
        $filePath = self::opt('base_path') . $file;
        if($file!='' && file_exists($filePath)) {
            $info = getimagesize($filePath);
            return 'data:'.$info['mime'].';base64,'.base64_encode((file_get_contents($filePath)));
        }
        return self::opt('debug') ? 'File not found: '.$file : '';
    }

    // Sends files as base64
    static function getFileBase64($file)
    {
        $filePath = self::opt('base_path') . $file;
        if($file!='' && file_exists($filePath)) {
            $mime = self::getMime($file);
            return 'data:'.$mime.';base64,'.base64_encode((file_get_contents($filePath)));
        }
        return self::opt('debug') ? 'File not found: '.$file : '';
    }

    // Sends string as base64
    static function getStringBase64($string)
    {
        $filePath = self::opt('base_path') . $string;
        if($string!='' && file_exists($filePath)) {
            $mime = self::getMime($string);
            return 'data:'.$mime.';base64,'.base64_encode((file_get_contents($filePath)));
        }
        return self::opt('debug') ? 'File not found: '.$string : '';
    }

    // mode 0 = full check, mode 1 = extension only
    static function getMime($file, $mode=0)
    {
        $mime_types = array(

            // text
            'css' => 'text/css',
            'js' => 'application/javascript',

            // images
            'png' => 'image/png',
            'jpe' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'jpg' => 'image/jpeg',
            'gif' => 'image/gif',
            'ico' => 'image/vnd.microsoft.icon',
            'svg' => 'image/svg+xml',
            'svgz' => 'image/svg+xml',
        );

        $ext = strtolower(array_pop(explode('.',$file)));
        $filePath = self::opt('base_path') . $file;

        if (function_exists('mime_content_type') && $mode == 0) {
            $mimetype = mime_content_type($file);
            return $mimetype;
        } elseif (function_exists('finfo_open') && $mode == 0) {
            $finfo = finfo_open(FILEINFO_MIME);
            $mimetype = finfo_file($finfo, $filePath);
            finfo_close($finfo);
            return $mimetype;
        } elseif (array_key_exists($ext, $mime_types)) {
            return $mime_types[$ext];
        } else {
            return 'application/octet-stream';
        }
    }

    // Prepare console logs for debugging
    static function console($key)
    {
        if(self::opt('sessionAuth') && self::opt('debug')) {
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
                case 'recursive_chain_for_id':
                    $c = "'chaining onload-handler for\"'+id+'\"'"; $a = "log"; break;
                case 'script_src_added_with_id':
                    $c = "'script-src \"'+val+'\" added with id \"'+id+'\"'"; $a = "log"; break;
                case 'script_added_with_id':
                    $c = "'script added with id \"'+id+'\"'"; $a = "log"; break;
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
        $output = '';

        // ADD DEBUG-INFO AS HTML-COMMENTS ONLY WHEN LOGGED IN
        if (self::opt('sessionAuth') && self::opt('debug')) {

            // Don´t reveal Base-path in debug-infos
            $options = self::$options;
            foreach(array('core_path','base_path','cache_path') as $option) {
                $options[$option] = str_replace(self::opt('base_path'), '###/', $options[$option]);
            };

            // Check for debug-tpl or set default template
            $output = !empty( self::$debugTpl ) ? self::$debugTpl : '
<!-- ......................................................... deferMinifyX Debug:
Items in $idsArr:
[+ids+]
..................................................................................
Items in $jsArr[dependsOn]:
[+js+]
..................................................................................
Items in $cssArr:
[+css+]
..................................................................................
$options:
[+options+]
..................................................................................
Debug-Messages:
[+messages+]
.......................................................... /deferMinifyX Debug -->
';

            $phArr = array(
                'ids'=>print_r(self::$idsArr, true),
                'js'=>print_r(self::$jsArr, true),
                'css'=>print_r(self::$cssArr, true),
                'options'=>print_r($options, true),
                'messages'=>' - ' . join("\n - ", self::$debugMessages)
            );

            foreach($phArr as $ph=>$val) {
                $output = str_replace('[+'.$ph.'+]', $val, $output);
            }
        }
        return $output;
    }

    static function resetBuffer()
    {
        self::$buffer = '';
    }
}