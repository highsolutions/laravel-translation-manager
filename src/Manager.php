<?php 

namespace Imtigger\TranslationManager;

use Imtigger\TranslationManager\Models\Translation;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\Finder;
use Google\Cloud\Translate\TranslateClient;

class Manager
{

    /** @var \Illuminate\Foundation\Application  */
    protected $app;
    /** @var \Illuminate\Filesystem\Filesystem  */
    protected $files;

    protected $config;

    public function __construct(Application $app, Filesystem $files)
    {
        $this->app = $app;
        $this->files = $files;
        $this->config = app()['config']['translation-manager'];
    }

    public function missingKey($namespace, $group, $key)
    {
        $locales = in_array($namespace,  ['*', '']) ? $this->loadLocales() : $namespace;

        if(!in_array($group, $this->config['exclude_groups'])) {
            foreach($locales as $locale) {
                Translation::firstOrCreate([
                    'locale' => $locale,
                    'group' => $group,
                    'key' => $key,
                ]);
            }
        }
    }

    public function importTranslations($replace = false)
    {
        $counter = 0;
        foreach($this->files->directories($this->app['path.lang']) as $langPath) {
            $locale = basename($langPath);
            if(in_array($locale, $this->config['exclude_langs']))
                continue;

            foreach($this->files->allfiles($langPath) as $file) {
                $info = pathinfo($file);
                $group = $info['filename'];

                if(in_array($group, $this->config['exclude_groups']))
                    continue;

                $subLangPath = str_replace($langPath . DIRECTORY_SEPARATOR, "", $info['dirname']);
                if ($subLangPath != $langPath) {
                    $group = $subLangPath . "/" . $group;
                }
                $group = str_replace('\\', '/', $group);

                $translations = \Lang::getLoader()->load($locale, $group);
                if (!$translations || !is_array($translations))
                    continue;

                foreach(array_dot($translations) as $key => $value) {
                   if(is_array($value)) // process only string values
                        continue;
                    
                    $value = (string) $value;
                    $translation = Translation::firstOrNew([
                        'locale' => $locale,
                        'group' => $group,
                        'key' => $key,
                    ]);

                    // Check if the database is different then the files
                    $newStatus = $translation->value === $value || !$translation->value ? Translation::STATUS_SAVED : Translation::STATUS_CHANGED;
                    if($newStatus !== (int) $translation->status)
                        $translation->status = $newStatus;

                    // Only replace when empty, or explicitly told so
                    if($replace || !$translation->value)
                        $translation->value = $value;

                    $translation->save();

                    $counter++;
                }
            }
        }
        return $counter;
    }

    public function findTranslations($path = null)
    {
        $path = $path ?: base_path();
        $keys = array();
        $functions =  array('trans', 'trans_choice', 'Lang::get', 'Lang::choice', 'Lang::trans', 'Lang::transChoice', '@lang', '@choice', 'transEditable');
        $pattern =                              // See http://regexr.com/392hu
            "[^\w|>]".                          // Must not have an alphanum or _ or > before real method
            "(".implode('|', $functions) .")".  // Must start with one of the functions
            "\(".                               // Match opening parenthese
            "[\'\"]".                           // Match " or '
            "(".                                // Start a new group to match:
                "[a-zA-Z0-9_-]+".               // Must start with group
                "([.][^\1)]+)+".                // Be followed by one or more items/keys
            ")".                                // Close group
            "[\'\"]".                           // Closing quote
            "[\),]";                            // Close parentheses or new parameter

        // Find all PHP + Twig files in the app folder, except for storage
        $finder = new Finder();
        $finder->in($path)->exclude('storage')->name('*.php')->name('*.twig')->files();

        /** @var \Symfony\Component\Finder\SplFileInfo $file */
        foreach ($finder as $file) {
            // Search the current file for the pattern
            if(preg_match_all("/$pattern/siU", $file->getContents(), $matches)) {
                // Get all matches
                foreach ($matches[2] as $key) {
                    $keys[] = $key;
                }
            }
        }
        // Remove duplicates
        $keys = array_unique($keys);

        // Add the translations to the database, if not existing.
        foreach($keys as $key) {
            // Split the group and item
            list($group, $item) = explode('.', $key, 2);
            $this->missingKey('', $group, $item);
        }

        // Return the number of found translations
        return count($keys);
    }

    public function exportTranslations($group)
    {
        if(in_array($group, $this->config['exclude_groups']))
            return;

        if ($group == '*') {
            return $this->exportAllTranslations();
        }

        $tree = $this->makeTree(Translation::where('group', $group)->whereNotNull('value')->get());

        foreach($tree as $locale => $groups) {
            if(!isset($groups[$group]))
                continue;

            $translations = $groups[$group];
            ksort($translations);

            $path = $this->app->langPath() . '/' . $locale . '/' . $group . '.php';
            $output = "<?php\n\nreturn ".var_export($translations, true).";\n";

            $this->files->put($path, preg_replace("/ \R/", "\n", $output)); // get rid of trailing spaces before line breaks and store in file
        }

        Translation::where('group', $group)
            ->whereNotNull('value')
            ->where('status', '=', Translation::STATUS_CHANGED)
            ->update(array('status' => Translation::STATUS_SAVED));
    }

    public function exportAllTranslations()
    {
        $groups = Translation::whereNotNull('value')->selectDistinctGroup()->get('group');

        foreach($groups as $group){
            $this->exportTranslations($group->group);
        }
    }

    public function cleanTranslations()
    {
        Translation::whereNull('value')->delete();
    }

    public function truncateTranslations()
    {
        Translation::truncate();
    }

    protected function makeTree($translations)
    {
        $array = array();
        foreach($translations as $translation) {
            array_set(
                $array[$translation->locale][$translation->group], 
                $translation->key, 
                $translation->value
            );
        }
        return $array;
    }

    public function getConfig($key = null)
    {
        if($key == null)
            return $this->config;

        return $this->config[$key];
    }
    
    public function loadLocales()
    {
        $locales = Translation::groupBy('locale')
            ->select('locale')
            ->get()
            ->pluck('locale')
            ->all();
        
        $locales = array_merge([config('app.locale')], $locales);
        return array_unique($locales);
    }

    public function cloneTranslations($from, $to)
    {
        $fromDir = $this->app['path.lang'] . DIRECTORY_SEPARATOR . $from;
        $toDir = $this->app['path.lang'] . DIRECTORY_SEPARATOR . $to;

        if(File::isDirectory($fromDir))
            File::copyDirectory($fromDir, $toDir);
    }

    public function suffixTranslations($original, $locale)
    {
        $langOriginal = Translation::where('locale', $original)
            ->whereNotNull('value')
            ->where('status', '=', Translation::STATUS_SAVED)
            ->get();

        $langLocale = Translation::where('locale', $locale)
            ->whereNotNull('value')
            ->where('status', '=', Translation::STATUS_SAVED)
            ->get();

        $suffix = strtoupper($locale);

        return $langLocale->filter(function ($lang) use ($langOriginal) {
            $toCompare = $langOriginal->where('group', $lang->group)
                ->where('key', $lang->key)
                ->first();
            
            return $toCompare != null && $toCompare->value == $lang->value;
        })->each(function ($lang) use ($suffix) {
            if(substr($lang->value, -2) == $suffix)
                return;
            
            $lang->update([
                'value' => $lang->value . ' '. $suffix,
            ]);
        })->count();
    }
    
    public function generateTranslations($locale, $group)
    {
        $translations = Translation::whereLocale($locale)->whereGroup($group)->whereStatus(0)->whereNull('value')->get();
        
        foreach ($translations as $translation) {
            $value = substr($translation->key, strrpos($translation->key, '.') + 1);
            $value = str_replace(['_', '-'], ' ', $value);
            $value = ucwords($value);
            $translation->update(['value' => $value]);
        }

        return $translations->count();
    }
    
    public function translateTranslations($fromLocale, $toLocale, $group)
    {
        // Create empty translations for toLocale
        $emptyBuilder = Translation::select('t1.*')->from(DB::raw('ltm_translations as t1'))
                                    ->leftJoin(DB::raw('ltm_translations as t2'), function ($join) use ($toLocale) {
                                        $join->on('t1.key', '=', 't2.key');
                                        $join->on('t2.locale', '=', DB::raw("'{$toLocale}'"));
                                    })
                                    ->where('t1.group', '=', $group)
                                    ->whereNull('t2.id');
                                    
        $emptyTranslations = $emptyBuilder->get();
        
        foreach ($emptyTranslations as $translation) {
            $newTranslation = Translation::create([
                'status' => 0,
                'locale' => $toLocale,
                'group' => $translation->group,
                'key' => $translation->key,
                'value' => null
            ]);
        }
        
        // Find untranslated from toLocale
        $builder = Translation::select('t1.*', 't2.id as nid')->from(DB::raw('ltm_translations as t1'))
                            ->join(DB::raw('ltm_translations as t2'), function ($join) use ($toLocale) {
                                $join->on('t1.key', '=', 't2.key');
                                $join->on('t2.locale', '=', DB::raw("'{$toLocale}'"));
                            })
                            ->where('t1.locale', '=', $fromLocale)
                            ->where('t1.group', '=', $group)
                            ->whereNull('t2.value');
                            
        $translations = $builder->get();
        
        $translator = new TranslateClient([
            'source' => 'en',
            'projectId' => config('google-translate.project_id'),
            'key' => config('google-translate.api_key')
        ]);
        
        foreach ($translations as $translation) {
            $translatedContent = $translator->translate($translation->value, ['target' => $toLocale]);
            
            Translation::whereId($translation->nid)->update([
                'value' => $translatedContent['text']
            ]);
        }

        return $translations->count();
    }
}
