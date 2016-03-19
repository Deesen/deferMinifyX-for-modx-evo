## deferMinifyX 0.1

**Testers welcome**

I want to provide an easy solution for most nowadays SEO-tasks related to **defer Javascript-, Css- and Img-files**. When finished this snippet aims to be an all-in-one solution for easy/default setups, as well as more complex ones allowing to chain multiple onLoad-handlers.

#### Snippet parameters / defaults for &get=\`\`
    [[deferMinifyX?
        &minifyDefer=`1`
        &minifyCss=`1`
        &minifyCssFile=`css/min.css`
        &minifyJs=`1`
        &minifyJsFile=`js/min.js`
        &deferImages=`0` // img "data-src"->"src" - https://varvy.com/pagespeed/defer-images.html
        &cacheFile=`cache.json`
        &cachePath=`/your/absolute/server_path/to/cache.json` // Default assets/cache/
        &cache=`1`
        &minify=`1`
        &debug=`0`
    ]]

#### Snippet Commandos
    [[deferMinifyX?
        &get=`default`
        &addCssSrc=`your_file.css,another_file.css`
        &addJsSrc=`your_file.js,another_file.css`
        &addScript=`alert('fire!');`
        &id=`unique id of script`
        &dependsOn=`id of another script`
    ]]

## Examples
##### Default example

- Add all nessecary CSS-, JS-files comma-separated in a single snippet-call and add it to your &lt;body&gt;, finish!


        [[deferMinifyX? 
            &addCssSrc=`css/bootstrap.css,css/styles.css,css/responsive.css`
            &addJsSrc=`js/jquery.min.js,js/slider.js`
            &get=`default`
        ]]

##### More complex chaining example

  - On page loaded, call the defer function, which loads all scripts without dependencies (no &dependsOn=\`id\` parameter set) first, in this case "script_src_jquery". For this example we add only jQuery but of course you can add multiple scripts "without dependencies"
  
        [[deferMinifyX? &addScriptSrc=`js/jquery.min.js` &id=`script_src_jquery`]]
    
  - if "script_src_jquery" loaded then load "script_src_slider" (which depends on "script_src_jquery")
  
        [[deferMinifyX? &addScriptSrc=`js/slider.min.js` &dependsOn=`script_src_jquery` &id=`script_src_slider`]]
        
  - if "script_src_slider" loaded then call "script_remove_preloader" + call "script_startslider"
  
        [[deferMinifyX? &addScript=`$('#preloader').fadeOut();` &dependsOn=`script_src_slider` &id=`script_remove_preloader`]]
        [[deferMinifyX? &addScript=`$('.slider').slider('init');` &dependsOn=`script_src_slider` &id=`script_startslider`]]
    
  - if "script_remove_preloader" called then load "script_src_x"
  
        [[deferMinifyX? &addScriptSrc=`js/script_x.js` &dependsOn=`script_remove_preloader`]]
        
  - if "script_startslider" called then call "script_sort_bullets"
  
        [[deferMinifyX? &addScript=`$('.slider').slider('sort');` &dependsOn=`script_startslider`]]
        
  - if "script_src_x" loaded then ..
  
#### Important: Chaining to "min"

By default the scriptSrc-element for min.js has ID "min", so you can depend on "min" and hook onload-events after "min" is loaded. If you don´t use a standard-set of minified (probably *cached**) files and you want to dynamically add scripts only on subpages (like "slider.js" only on start-page), you have to hook "slider.js" on ID "min". Using 

    [[deferMinifyX? &addScriptSrc=`js/jquery.min.js`]]

without &dependsOn="min" will lead to rebuild cache of min-files on each sub-page that changes the default-setup, resulting in constantly changing min.js.

  - on page loaded, call defer function and load min.js (which has no dependencies) with ID "min"
  - if "min" loaded then load "script_src_dev"
  
        [[deferMinifyX? &addScriptSrc=`js/dev.js` &dependsOn=`min` ]]
    
  - if "script_src_dev" loaded then ..
  
#### Debug-Mode

  - &debug=\`1\` provides detailed Debug-infos when logged into manager as HTML-comments + console.log(). Each important step will be logged into console for more insight.
  - &cache=\`0\` forces generating of minified files (for development)
  - &minify=\`0\` globally disables minifying of files (for development)
  - &debugTpl=`chunkname/@CODE` enables styling of debugMessages

**Beware of Modx-Cache when in production/logged into manager!** You probably don´t want to make debug-infos public on your live-site by setting &debug=\`1\` .

------------------------------------------------------------------

### Actual version-infos 0.1
- tested with Modx Evolution 1.1
- **not tested in production** 

### Todo:
- finish deferMinifyX-cache: 
    - check md5-cache with changing orders of addScript
    - check caching on different pages with different addScript-calls
    - check interaction of deferMinifyX-cache / Modx-cache 
- inline-todos
- dependsOn "min": add dynamic minify+cache of each file (and call?)
- add &get="injectcss" directly to source (with dynamic cache of minified files)
- add &get="injectjs" directly to source (with dynamic cache of minified files)
- check common browsers for compatibility
- add &debugTpl=`` to change display of debug-messages (display in modals etc)