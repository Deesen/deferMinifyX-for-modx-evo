## deferMinifyX 0.2

Plugin + Snippet related to SEO-tasks like **defer Javascript-, Css- and Img-files**. It aims to be an all-in-one solution for easy/default setups, as well as more complex ones allowing to chain multiple onLoad-handlers for \<script\>.

### Default use

To set a standard set of CSS- and JS-files, minify them into min.css/min.js and append nessecary tags to \<head\> and \</body\>, follow these steps

- Install plugin 
- Add required CSS-, JS-files comma-separated in plugin-configuration
- Set desired options & save plugin-configuration, finish!

When reloading your frontpage the CSS-, JS-files should be added as configured.

### Above-the-fold

If you want to inject critical parts of CSS directly into the source-code, please refer to the "Snippet Commandos" below.

### Plug-configuration

| Option               | Description | Values (*install default*) |
|----------------------|-------------|--------------------|
|**deferMinifyX**      | When disabled normal link- and script-tags with optional defer/async-attributes will be rendered) | enabled, *disabled* |
|**Disable** Plugin    | Disable plugin for ressource-ids (comma-separated)                                        | |
|**Enable** Plugin     | Enable plugin **only** for ressource-ids (comma-separated)                                | |
|**CSS**               | Minify default CSS-files into min.css	                                                   | *enabled*, disabled |
|Lib to minify CSS     | Which lib to use for minifying CSS-files                                                  | *minify*, regex |
|FilePath of min.css   | FilePath to store, example css/min.css                                                    | *min.css* |
|Default CSS-files     | Comma-separated list, optional: add defer/async: css/style.css\|\|defer\|\|async)         | *css/style.css,css/responsive.css* |
|**JS**                | Minify default JS-files and inline-code into min.js                                       | *enabled*, disabled |
|Lib to minify JS      | Which lib to minify Javascript                                                            | *minify*, jsshrink|
|FilePath of min.js    | FilePath to store minified JS-files, example css/min.js                                   | *min.js* | 
|Default JS-files      | Comma-separated list, optional: add defer/async: js/application.js\|\|defer\|\|async)     | *js/jquery.min.js,js/application.js** |
|Default inline js code| Default inline-code, will be added to min.js                                              | | 
|**Defer Images**      | When enabled, it replaces all img src by src="blank.jpg" data-src "real-image.jpg")       | enabled, *disabled* | 
|FilePath to blank.jpg | Filepath to blank-image                                                                   | *img/blank.jpg* | 
|**Minify** defer script | Inject minified defer script                                                            | *enabled*, disabled |
|Minify globally       | Enable/disable minify globally                                                            | *enabled*, disabled |
|**Minify HTML**       | Minify HTML-output at runtime                                                             | *disabled*, minifier, regex |
|**Cache**             | Caching of minified files (can be disabled for debug)                                     | *disabled*, minifier, regex | 
|Path to cache-file    | Path where to store cache-related files (empty defaults to assets/cache/deferMinifyX)     | |
|Reset-type            | Modx "Clear Cache"-Button: index = clear file-index, all = clear files + file-index       | *index* |
|Parameter-Suffix      | String to add as cache-param min.js?suffix (enables min.js?*ver=*xxx)                     | | 
|**Debug**             | Shows extended debug-infos (in console.log() and HTML-comments, **disable** on live-sites)| enabled, *disabled* | 
|Chunkname of debug-tpl| Optional Chunk to display debug-infos: [+ids+], [+js+], [+css+], [+options+], [+messages+]| |

### More complex chaining example

  - On page loaded, the defer function is called, adding all scripts without dependencies (no &dependsOn=\`id\` parameter set) first. The default set of JS-files (min.js) will be added with ID "min".
  
  - You can add additional files on any subpage using the snippet

        [!deferMinifyX? &add=`js` &file=`js/jquery.min.js` &id=`jquery`!]
        [!deferMinifyX? &add=`js` &file=`js/slider_xy.min.js` &id=`slider` &dependsOn=`jquery`!]
        [!deferMinifyX? &add=`script` &val=`$('.slider').slider();` &dependsOn=`slider`!]
        
  - If jQuery is a default file (= added to min.js), and component depends on jQuery, you can use ID "min" with &dependsOn=\`min\`
  
        [!deferMinifyX? &add=`js` &file=`js/slider_xy.min.js` &id=`slider` &dependsOn=`min`!]
        [!deferMinifyX? &add=`script` &val=`$('.slider').slider();` &dependsOn=`slider` &id=`slider_call`!]

  - you can chain multiple ids and dependsOn per subpage like required. It should be possible to chain all kind of combinations like (otherwise please report an issue):
  
        [!deferMinifyX? &add=`js` &file=`js/slider_tools.min.js` &id=`slider_tools` &dependsOn=`slider_call`!]
        [!deferMinifyX? &add=`script` &val=`$('.slider').sliderTool('xy');` &dependsOn=`slider_tools`!]
        ...

### Snippet Commandos
#### &add

    [!deferMinifyX? &add=`css`    &file=`js/your_file.css`!]
    [!deferMinifyX? &add=`js`     &file=`js/your_file.js` &id=`your__optional_id`!]
    [!deferMinifyX? &add=`js`     &file=`js/your_file.js` &dependsOn=`your__optional_id`!]
    [!deferMinifyX? &add=`script` &val=`your code`        &dependsOn=`your__optional_id`!]

Important: Must be called uncached!

#### &get

You can also directly inject base64-encoded images or files, minified css/js-files, or minified strings (use cached snippet-calls!).

    <img src="   [[deferMinifyX? &get=`img64`  &file=`img/your_file.xxx` ]]" alt="" />
    <style>      [[deferMinifyX? &get=`css`    &file=`css/your_file.css` ]]        </style>
    <script src="[[deferMinifyX? &get=`js`     &file=`js/your_file.js` ]]">        </script>
    
    <style>      [[deferMinifyX? &get=`minify` &val=`your rules` ]]                </style>
    <script>     [[deferMinifyX? &get=`minify` &val=`your code` ]]                 </script>
    
                 [[deferMinifyX? &get=`base64` &val=`your string to encode` ]]

So in a "above-the-fold" scenario you can split critical parts of CSS into a separate file, and put this line into `<head>`

    <style>[[deferMinifyX? &get=`css`&file=`css/critical.css` ]]</style>

#### &option

For development most parameters of plugin-configuration can be modified dynamically via

        [!deferMinifyX? &option=`defer` &val=`0`!]
        [!deferMinifyX? &option=`deferImages` &val=`1`!]
        ...