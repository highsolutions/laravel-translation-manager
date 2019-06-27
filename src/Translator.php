<?php 

namespace Imtigger\TranslationManager;

use Illuminate\Translation\Translator as LaravelTranslator;
use Illuminate\Events\Dispatcher;

class Translator extends LaravelTranslator 
{

    /** @var  Dispatcher */
    protected $events;

    protected $manager;

    /**
     * Get the translation for the given key.
     *
     * @param  string  $key
     * @param  array   $replace
     * @param  string  $locale
     * @return string
     */
    public function get($key, array $replace = array(), $locale = null, $fallback = true)
    {
        // Get without fallback
        $result = parent::get($key, $replace, $locale, false);
        if($result === $key){
            $this->notifyMissingKey($key);

            // Reget with fallback
            $result = parent::get($key, $replace, $locale, $fallback);
        }

        return $result;
    }

    public function setTranslationManager(Manager $manager)
    {
        $this->manager = $manager;
    }

    protected function notifyMissingKey($key)
    {
        list($namespace, $group, $item) = $this->parseKey($key);
        if($this->manager && $namespace === '*' && $group && $item ){
            $this->manager->missingKey($namespace, $group, $item);
        }
    }
    
    /**
     * Get the translation for the given key and wrap it in a span element
     * (it allowins translators to edit translations in-place)
     *
     * @param  string  $key
     * @param  array   $replace
     * @param  string|null  $locale
     * @param  bool  $fallback
     *
     * @return string|array|null
     *
     * @note do not escape translations in views!
     */
    public function transEditable($key, array $replace = [], $locale = null, $fallback = true)
    {
        $translation = self::get($key, $replace, $locale, $fallback);
        if (isLiveTranslationEnabled() == false) {
            // user is not logged or he hasn't enabled inline translations or we are on translations page
            return $translation;
        }

        // currently logged user has enabled inline translations - return translation wrapped in span element

        if (!$locale) {
            $locale = \App::getLocale();
        }
        $basicLocale = config('translation-manager.basic_lang', 'en');
        $basicTranslation = parent::get($key, $replace, $basicLocale, $fallback);

        /**
         * we need escaped versions in order to show them as title and value;
         * real translation is displayed unescaped
         */
        $escapedBasicTranslation = htmlspecialchars($basicTranslation, ENT_QUOTES, 'UTF-8', false);
        $escapedCurrentTranslation = htmlspecialchars($translation, ENT_QUOTES, 'UTF-8', false);

        return '<span class=\'editable locale-'. $locale . '\'' .
            ' data-locale=\'' . $locale . '\'' .
            ' data-url=\'' . route('translation-manager.update') . '\'' .
            ' data-name=\'' . $locale . '|' . $key . '\'' .
            ' data-type=\'textarea\'' .
            ' data-placement="'. config('translation-manager.popup_placement') .'"' .
            ' data-pk=\'dummy\'' .
            ' data-value=\'' . $escapedCurrentTranslation . '\'' .
            ' data-title=\'' . $escapedBasicTranslation . ' (' . $basicLocale . ' -> ' . $locale .  ')\'>' .
            $translation . '</span>';
    }

}
