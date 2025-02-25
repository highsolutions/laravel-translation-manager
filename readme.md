Laravel Translation Manager
=============================

Easy management of translations in Laravel.

![Laravel-Translation-Manager by HighSolutions](https://raw.githubusercontent.com/highsolutions/laravel-translation-manager/master/intro.jpg)

Installation
------------

Add the following line to the `require` section of your Laravel webapp's `composer.json` file:

```javascript
    "require": {
        "highsolutions/laravel-translation-manager": "^1.0"
    }
```

Run `composer update` to install the package.

Then, update `config/app.php` by adding an entry for the service provider:

```php
'providers' => [
    // ...
    HighSolutions\TranslationManager\ManagerServiceProvider::class,
];
```

Next, publish all package resources:

```bash
    php artisan vendor:publish --provider="HighSolutions\TranslationManager\ManagerServiceProvider"
```

This will add to your project:

    - migration - database table for storing translations
    - configuration - package configurations
    - views - configurable views for translation management
    - translations - translations for webinterface

Remember to launch migration: 

```bash
    php artisan migrate
```

Workflow
------------

This package doesn't replace the Translation system, only import/export PHP files to a database and make them editable in browser.
Package contains helper for live editing content on website.

The workflow would be:

    - Import translations: Read all translation files and save them in the database
    - Find all translations in php/twig sources
    - Optionally: Listen to missing translation with the custom Translator
    - Optionally: Mark not-translated records as not-translated (suffix command)
    - Translate all keys through the webinterface
    - Export: Write all translations back to the translation files.

Usage
------

You can access package in `http://yourdomain.com/translations` in default configuration. You can change as you pleased.

Configuration
-------------

| Setting name             | Description                                                             | Default value                                                                                                        |
|--------------------------|-------------------------------------------------------------------------|----------------------------------------------------------------------------------------------------------------------|
| route                    | Route declaration (prefix, namespace, middlewares etc.)                 | [,'prefix' => 'translations', 'namespace' => 'HighSolutions\TranslationManager', 'middleware' => [,'web', 'auth',],] |
| delete_enabled           | Enable deletion of translations                                         | true                                                                                                                 |
| exclude_groups           | Exclude specific file groups (like validation, pagination, routes etc.) | []                                                                                                                   |
| exclude_langs            | Exclude specific langs and directories (like vendor and en, etc.) | []                                                                                                                   |
| basic_lang            | Basic language used by translator. | 'en'                                                  |
| sort_keys                | Export translations with keys output alphabetically.                    | false                                                                                                                |
| highlight_locale_marked  | Highlight lines with locale marked as not translated.                   | false                                                                                                                |
| live_translation_enabled | Enable live translation of content.                                     | false                                                                                                                |
| popup_placement | Position of live translation popup.                                     | top                                                                                                              |
| permissions              | Define whow and when can edit translations.                             |  env('APP_ENV') == 'local'                                                                   |


Commands
---------

### Import command

The import command will search through app/lang and load all strings in the database, so you can easily manage them.

```bash
    php artisan translations:import
```

Note: By default, only new strings are added. Translations already in the DB are kept the same. If you want to replace all values with the ones from the files, 
add the `--replace` (or `-R`) option: `php artisan translations:import --replace`

### Find translations in source

The Find command/button will look search for all php/twig files in the app directory, to see if they contain translation functions, and will try to extract the group/item names.
The found keys will be added to the database, so they can be easily translated.
This can be done through the webinterface, or via an Artisan command.

```bash
    php artisan translations:find
```

### Export command

The export command will write the contents of the database back to resources/lang php files.
This will overwrite existing translations and remove all comments, so make sure to backup your data before using.
Supply the group name to define which groups you want to publish.
If you want to export all groups, provide `*` as name of group.

```bash
    php artisan translations:export <group>
```

For example, `php artisan translations:export reminders` when you have 2 locales (en/pl), will write to `resources/lang/en/reminders.php` and `resources/lang/pl/reminders.php`

### Clean command

The clean command will search for all translation that are NULL and delete them, so your interface is a bit cleaner. Note: empty translations are never exported.

```bash
    php artisan translations:clean
```

### Reset command

The reset command simply clears all translation in the database, so you can start fresh (by a new import). Make sure to export your work if needed before doing this.

```bash
    php artisan translations:reset
```

### Clone command

The clone command copy directory of basic language (langFrom parameter) and saves as new language (langTo parameter). After this operation you will need to launch import command.

```bash
    php artisan translations:clone langFrom langTo
```

### Suffix command

The suffix command analyzes all translations from new locale (langNew parameter) and if the value is the same as in original language (langOriginal parameter) then adds suffix to the end of value of new locale translations to mark that this translation needs to be translated. The suffix is locale code (e.g. EN) upper-cased.

```bash
    php artisan translations:sufix langOriginal langNew
```

### Detect missing translations

Most translations can be found by using the Find command (see above), but in case you have dynamic keys (variables/automatic forms etc), it can be helpful to 'listen' to the missing translations.
To detect missing translations, we can swap the Laravel TranslationServicepProvider with a custom provider.
In your config/app.php, comment out the original TranslationServiceProvider and add the one from this package:

```php
    //'Illuminate\Translation\TranslationServiceProvider',
    'HighSolutions\TranslationManager\TranslationServiceProvider',
```

This will extend the Translator and will create a new database entry, whenever a key is not found, so you have to visit the pages that use them.
This way it shows up in the webinterface and can be edited and later exported.
You shouldn't use this in production, just in production to translate your views, then just switch back.

Live editing
---------

When you have translations in database, you can use `transEditable` method instead of `trans` whenever it's suitable. To do this, you have to make few steps:

Update `config/app.php` by adding an entry for the service provider (another one):

```php
'providers' => [
    // ...
    HighSolutions\TranslationManager\TranslationServiceProvider::class,
];
```

Add these two methods to `app\helpers.php` file.

```php
if (!function_exists('transEditable')) {
    /**
     * Translate the given message and wraps it in .editable container to allow editing
     *
     * @param  string  $id
     * @param  array   $parameters
     * @param  string  $domain
     * @param  string  $locale
     * @return \Symfony\Component\Translation\TranslatorInterface|string
     */
    function transEditable($id = null, $parameters = [], $domain = 'messages', $locale = null) {
        return app('translator')->transEditable($id, $parameters, $locale);
    }
}

if (!function_exists('isLiveTranslationEnabled')) {
    /**
     * Return true if live translation enabled
     *
     * @return bool
     */
    function isLiveTranslationEnabled() {
        return Request::cookie('live-translation-enabled') || config('translation-manager.live_translation_enabled');
    }
}
```

In your layout view add this scripts and style (see Layout customization section):

```html
    <link href="//cdnjs.cloudflare.com/ajax/libs/x-editable/1.5.0/bootstrap3-editable/css/bootstrap-editable.css" rel="stylesheet"/>
    <style>
        .editable-click {
            border-bottom-color: red;
            cursor: pointer;
        }

        .editableform .control-group {
            display: block;
        }

        .editable-input {
            display: block;
        }

        .editable-input > textarea {
            width: 100% !important;
        }

        .editable-buttons {
            margin: 10px 0 0;
            text-align: right;
            width: 100%;
        }

        .editable-buttons .editable-submit {
            float: right;
            margin-left: 10px;
        }
    </style>
    // ...
    <script src="//cdnjs.cloudflare.com/ajax/libs/x-editable/1.5.0/bootstrap3-editable/js/bootstrap-editable.min.js"></script>
```

If you want to change all links into non-links, add this little script:

```
<script>
    $('.editable').parent('a').replaceWith(function () {
            return $('<div/>', {
                html: this.innerHTML
            });
        }
    );
</script>
```

Last step is to add this JS file:

```
jQuery(document).ready(function($){

    $.ajaxSetup({
        beforeSend: function(xhr, settings) {
            settings.data += "&_token=" + $(':hidden[name="_token"]').val();
        }
    });

    $('.editable').editable().on('hidden', function(e, reason){
        var locale = $(this).data('locale');
        if(reason === 'save'){
            $(this).removeClass('status-0').addClass('status-1');
        }
        if(reason === 'save' || reason === 'nochange') {
            var $next = $(this).closest('tr').next().find('.editable.locale-'+locale);
            setTimeout(function() {
                $next.editable('show');
            }, 300);
        }
    });

    $(document).on('click', 'a', function(event) {
        if (event.target.localName !== 'a' && event.target.className.indexOf('editable-submit') !== -1) {
            event.preventDefault();
            return false;
        }
    });
});

```

And now you are able to use `transEditable` helper and when live editing is active (checked through `isLiveTranslationEnabled`), user is able to click on text, popup will show and text can be changed. Saving changes will cause saving to the database and exporting this text to translation file. If live editing is not active, user will see standard text.

You can use this helper like this:

```php
	<div class="text">{!! transEditable('auth.failed') !!}</div>
```

Do not use this inside of non-clickable elements (title attribute, alt attributes etc.). To launch popup inside link, click on border, not text.

Changelog
---------

1.4.0
* Support Laravel 12.x

1.3.0
* Support Laravel 11.x

1.2.0
* Support Laravel 9.x and 10.x

1.1.0
* Support Laravel 7.x and 8.x

1.0.0
* Support Laravel 6.0

0.6.0
* No STRICT_MODE needed anymore

0.5.1
* Fix searching translations

0.5.0
* Change the views path

0.4.7
* remove closures in config file

0.4.5
* Laravel 5.6 support

0.4.4
* Fix translation title popup

0.4.3
* New configurations (popup placement and basic language)
* Update documentation about necessary JS scripts

0.4.0

* New commands: clone and suffix
* Improve export command

0.3.7

* New configuration option to exclude langs

0.3.6

* Support auto-discovery and Laravel 5.5

0.3.0

* Support for subdirectories
* Support for array translations
* New design
* Permission management
* Translations for view
* Live editing

0.2.0

* Barryvdh version of package

Roadmap
-------

* Duplicate translations of one locale to another with locale suffix.
* Detection of incorrect files.
* Support vendor translations files.
* Unit tests!

Credits
-------

This package was originally created by [Barry vd. Heuvel](https://github.com/barryvdh) and is available here: [laravel-feed](https://github.com/barryvdh/laravel-translation-manager).

Currently is developed by [HighSolutions](http://highsolutions.pl), software house from Poland in love in Laravel.
