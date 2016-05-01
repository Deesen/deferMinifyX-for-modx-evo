/**
 * deferMinifyX
 *
 * Flexible all-in-one solution for SEO-tasks like defer JS-, CSS- and IMG-files
 *
 * @category    plugin
 * @version     0.2
 * @license     http://www.gnu.org/copyleft/gpl.html GNU Public License (GPL)
 * @internal    @properties &defer=<b>deferMinifyX</b><br/>(when disabled renders normal link- and script-tags with optional defer/async-attributes);radio;enabled,disabled;disabled &inactiveIds=<b>Disable</b> plugin for Ressource-IDs (comma-separated);text; &activeIds=<b>Enable</b> plugin only for Ressource-IDs (comma-separated, only for development);text; &minifyCss=<b>CSS</b><br/>Minify default CSS-files into min.css;radio;enabled,disabled;enabled &minifyCssLib=Lib to minify CSS;radio;minifier,regex;minifier &minifyCssFile=FilePath of min.css;text;min.css &defaultCssFiles=<u>Default CSS-files</u><br/>(comma-separated,<br/>optional defer/async:<br/>css/style.css||defer||async);text;css/style.css,css/responsive.css &minifyJs=<b>JS</b><br/>Minify default JS-files and inline-code into min.js;radio;enabled,disabled;enabled &minifyJsLib=Lib to minify JS;radio;jsmin,jsminplus,jshrink,regex;jsminplus &minifyJsFile=FilePath of min.js;text;min.js &defaultJsFiles=<u>Default JS-files</u><br/>(comma-separated,<br/>optional defer/async:<br/>js/application.js||defer||async);text;js/jquery.min.js,js/application.js &defaultInlineJs=Default <b>inline js</b> code;textarea; &deferImages=<b>Images</b><br/>Defer Images (src "blank.jpg", data-src "real-image.jpg");radio;enabled,disabled;disabled &blankImage=FilePath to blank.jpg;text;img/blank.jpg &minifyDefer=<b>Minify</b><br/>Minify defer script before injecting;radio;enabled,disabled;enabled &minify=Enable/disable <b>minify</b> globally;radio;enabled,disabled;enabled &minifyHtml=<b>HTML</b><br/>Minify HTML-Output;radio;disabled,minifier,regex;disabled &cache=<b>Cache</b><br/>Caching of minified files (disable for debug);radio;enabled,disabled;enabled &cachePath=Path to cache-file<br/>(empty defaults to<br/>assets/cache/deferMinifyX);text; &cacheReset=<u>Reset-type</u><br/>What to reset when pressing Clear Cache-Button;radio;nothing,index,all;index &hashParam=Parameter-Suffix<br/>min.js?suffix;text; &debug=<b>Debug</b><br/>Extended debug-infos<br/>(in console.log() and HTML-comments);radio;enabled,disabled;disabled &debugTpl=Chunkname of debug-tpl;text;
 * @internal    @events OnLoadWebDocument,OnParseDocument,OnWebPagePrerender,OnSiteRefresh
 * @internal    @modx_category SEO
 * @internal    @legacy_names deferMinifyX
 * @internal    @installset base
 *
 * @documentation Latest docs https://github.com/Deesen/deferMinifyX-for-modx-evo
 * @reportissues Bugs and issues https://github.com/Deesen/deferMinifyX-for-modx-evo
 * @link minifier, jsmin & jsminplus v2.3 https://github.com/mrclay/minify
 * @link regex https://gist.github.com/tovic/d7b310dea3b33e4732c0
 *
 * @author      Deesen
 * @lastupdate  2016-05-01
 *
 * Latest Updates / Issues on Github : https://github.com/Deesen/deferMinifyX-for-modx-evo
*/
if (!defined('MODX_BASE_PATH')) { die('What are you doing? Get out of here!'); }

require(MODX_BASE_PATH."assets/plugins/deferMinifyX/plugin.deferMinifyX.inc.php");