# swCombinePlugin

This plugin combines javascript and stylesheet files into optimized files.

## How it works ?

The plugin read all `view.yml` files and generates the correct combined files :

 - stylesheets
 - javascript
 
The plugin can also generate packages from a list of files. This feature allows you to create a common set of files for your website, and include specific assets for a view.
There is also an option to gzip the generated files. This is useful if your server does'nt have mod_deflate enabled and requires a customized htacces.

## Benefits

 - Combine files and optimizations are performed when the application is deployed
 - The view cache file generates the correct path to the combined files, so there is not computation at runtime.

## Available optimizers

  - Global
    - `swPassDriver` : do nothing, use this if you want to combine your files with no optimizations
  
  - Javascript 
    - `swDriverGoogleClosureCompilerApi` : google closure algorithm (remote)
    - `swDriverGoogleClosureCompilerJar` : google closure algorithm (local, require java)
    - `swDriverJSMin` : JSMin algorithm
    - `swDriverJSMinPlus` : JSMin+ algorithm
    - `swDriverYuiJs` : YUI compressor (local, requires java)
    
  - Stylesheet
    - `swDriverCssmin` : Css min algorithm
    - `swDriverMinifyCssCompressor` : Minify Css Compressor algorithm
    - `swDriverYuiCss` : YUI compressor (local, requires java)

## Install

 * edit `ProjectConfiguration.class.php` by adding the requirement and enable the plugin
 
        [php] 
        require_once(dirname(__FILE__).'/../plugins/swCombinePlugin/lib/config/swCombineViewConfigHandler.class.php');
        
        class ProjectConfiguration extends sfProjectConfiguration
        {
          public function setup()
          {

             $this->enablePlugins(array(
                // your plugins
                'swCombinePlugin'
             ));
           }
        }

 * edit your `app.yml` and add an optional version value
 
        [yaml]
        swToolbox:
          swCombine:
            # the version number can be used to force load of new files from the media server
            #   you can configure a never ending expired time on your media server
            #   if the version is never updated, media will be never reloaded ...
            version: 3
            # Set this to true if you want to gzip assets
            use_gzip: false
  
 * add a `config_handlers.yml` inside `APP/config`
 
        modules/*/config/view.yml:
          # put sfViewConfigHandler to restore the default symfony config handler
          # class: sfViewConfigHandler
          class: swCombineViewConfigHandler 
          param:
           configuration:
             javascript:
               public_path:  /sw-combine/%SF_APP%
               private_path: %SF_WEB_DIR%/sw-combine/%SF_APP%
               combine:  swCombineJavascript
               driver:   swDriverJSMinPlus
               filename: %s.js                         # %s will by replace by the combined name
               exclude:
                 - /js/jQuery/jquery.1.2.6.js
                 - /js/jQuery/ui-1.5.3/jquery.ui.all.js
                 - /js/jQuery/offset.js
                 - /js/jQuery/jquery.form-defaults.js
                 - /js/jQuery/jquery.timers-1.2.js
                 - /js/jqModal/jqModal.js
                 - /js/scrollTo/jquery.scrollTo-min.js
                 - /js/main.js
               packages:
                 common:
                   auto_include: true                 # this will include the package on ALL pages
                   files:
                     - /js/jQuery/jquery.1.2.6.js
                     - /js/jQuery/ui-1.5.3/jquery.ui.all.js
                     - /js/jQuery/offset.js
                     - /js/jQuery/jquery.form-defaults.js
                     - /js/jQuery/jquery.timers-1.2.js
                     - /js/jqModal/jqModal.js
                     - /js/scrollTo/jquery.scrollTo-min.js
                     - /js/main.js

             stylesheet:
               public_path:  /sw-combine/%SF_APP%
               private_path: %SF_WEB_DIR%/sw-combine/%SF_APP%
               combine:  swCombineStylesheet
               driver:   swDriverCssmin
               filename: %s.css                        # %s will by replace by the combined name
               exclude:                                # this will include the package on ALL pages
                 - /css/main.css
                 - /css/draft/ui-theme.css
                 - /css/mg-theme.css
                 - /css/print.css
               packages:
                 common:
                   auto_include: true
                   files:
                     - /css/main.css
                     - /css/draft/ui-theme.css
                     - /css/mg-theme.css


 * clear your cache

        ./symfony cc

 * combine css and js with 
    
        ./symfony sw:combine frontend
        ./symfony sw:combine backend

 * update your templates files to use these helpers :
    
        use_helper('swCombine')
        sw_include_stylesheets()
        sw_include_javascripts()
        sw_combine_debug()
                    

## Packages

  You can include packages into the `view.yml`, just add these lines into `view.yml`:

        all:
          sw_combine:
            include_packages:
              javascripts: [common]
              stylesheets: [common]

        myViewSuccess:
          sw_combine:
            include_packages:
              javascripts: [extra_code]



## Assets version (optional)

This plugin has a hidden gem : asset versioning. When css files are combined, a version number is added  to all externals references. This feature must be use with helper functions `sw_include_stylesheets()` and `sw_include_javascripts()`, this two helpers add the version number on each declared assets.

The asset version format is : `ASSET_FILE?_sw=ASSET_VERSION`


### What does it mean ?

You can configure a long time expired value on your server by using simple regular expression.

    (.*)\?_sw=(.*) => expired in 1 month.
  
Doing so, the user agent will not do any extras requests to the webserver.

### Defining the asset version

 * edit your `app.yml` file by adding these lines
 
        swToolbox:
          swCombine:
            version: 42

 * clear your cache

         ./symfony cc

 * regenerate the combined css and js files with 

         ./symfony sw:combine frontend
         ./symfony sw:combi"e backend


## Enabling Gzip compression

If your server doesn't have mod_deflate enabled, you can tell the plugin to gzip combined assets.

 * the filename format for combined assets in `APP/config/config_handlers.yml` must end with ".js" or ".css"

    modules/*/config/view.yml:
      param:
        configuration:
          javascript:
            filename: %s.js
          stylesheet:
            filename: %s.css

 * edit your `app.yml` file by adding these lines 

        swToolbox:
          swCombine:
            use_gzip: true

 * regenerate the combined css and js files with 

    ./symfony sw:combine frontend
    ./symfony sw:combine backend

 * add the following lines to your .htacces file (a sample file which combines this with the symfony default htacces is provided in the plugin under data/htaccess.sample)

    <IfModule mod_rewrite.c>
      RewriteEngine On

      # Rules to correctly serve gzip compressed CSS and JS files.
      # Requires both mod_rewrite and mod_headers to be enabled.
      <IfModule mod_headers.c>
        # Serve gzip compressed CSS files if they exist and the client accepts gzip.
        RewriteCond %{HTTP:Accept-encoding} gzip
        RewriteCond %{REQUEST_FILENAME}\.gz -s
        RewriteRule ^(.*)\.css $1\.css\.gz [QSA]

        # Serve gzip compressed JS files if they exist and the client accepts gzip.
        RewriteCond %{HTTP:Accept-encoding} gzip
        RewriteCond %{REQUEST_FILENAME}\.gz -s
        RewriteRule ^(.*)\.js $1\.js\.gz [QSA]

        # Serve correct content types, and prevent mod_deflate double gzip.
        RewriteRule \.css\.gz$ - [T=text/css,E=no-gzip:1]
        RewriteRule \.js\.gz$ - [T=text/javascript,E=no-gzip:1]

        <FilesMatch "(\.js\.gz|\.css\.gz)$">
          # Serve correct encoding type.
          Header set Content-Encoding gzip
          # Force proxies to cache gzipped & non-gzipped css/js files separately.
          Header append Vary Accept-Encoding
        </FilesMatch>
      </IfModule>

    </IfModule>

         
         
## Troubleshooting

### how can I set a media type for each package ?

   for now this feature is not implemented.

### css @import not loaded 

   the import syntax must be : `@import url('yourfile.css')` or `@import url("yourfile.css")`.
   
   this is not valid : `@import url(yourfile.css)`
   
### Class swCombineViewConfigHandler not found

   make sure the `swCombineViewConfigHandler` class is included in the ProjectConfiguration.class.php file
   
   
        require_once(dirname(__FILE__).'/../plugins/swCombinePlugin/lib/config/swCombineViewConfigHandler.class.php');

        class ProjectConfiguration extends sfProjectConfiguration
        [...]
        
### unable to read the asset : /home/[...]/yourfile.css 

   All your css and js files must be relative, like `/js/asds.js` and `/css/toto.css`, the *bad* magic from symfony does not work
   

### my background pictures get stripped out

   The plugin uses css paths and sf_web_dir to find pictures, if the pictures do not exist then the plugin set *none* instead of *url(non_existant_background)*, to avoid 404 requests on the webserver
