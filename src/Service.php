<?php

namespace Imtigger\TranslationManager;

use Imtigger\TranslationManager\Manager;
use Imtigger\TranslationManager\Models\Translation;

class Service
{

	var $manager;

	public function __construct()
	{
		$this->manager = app()->make(Manager::class);
	}

	public function getGroup($route)
	{
        $groups = \Request::path();
        return str_replace($route, '', $groups);
	}

    public function loadLocales()
    {        
        return $this->manager->loadLocales();
    }

    public function getGroups() 
    {
        $groups = Translation::select('group');
        $excludedGroups = $this->manager->getConfig('exclude_groups');
        if($excludedGroups) {
            $groups->whereNotIn('group', $excludedGroups);
        }

        $groups = $groups->pluck('group', 'group')->all();
        return $this->treeGroups($groups);
    }

    protected function treeGroups($groups, $delimiter = '/') 
    {
        $list = [];

        foreach($groups as $group) {
            $this->stringToArray($list, $group, $delimiter);
        }

        return $list;
    }

    protected function stringToArray(&$arr, $path, $separator = '.') 
    {
        $keys = explode($separator, $path);

        foreach ($keys as $key) {
            if(isset($arr[$key]) && !is_array($arr[$key])) // remove key if it's a string and the same key has array
                unset($arr[$key]);
                
            $arr = &$arr[$key];
        }

        $arr = $path;
    }

    public function getHighlighted() 
    {
        $locale = config('app.locale');

        $notPublished = Translation::where('status', Translation::STATUS_CHANGED)
            ->select('group')
            ->get()
            ->pluck('group')
            ->all();

        $empty = Translation::whereNull('value')
            ->select('group')
            ->get()
            ->pluck('group')
            ->all();

        $notTranslated = [];
        if(config('translation-manager.highlight_locale_marked'))
            $notTranslated = Translation::where('value', 'like binary', '%'. strtoupper($locale))
                ->where('locale', $locale)
                ->select('group')
                ->get()
                ->pluck('group')
                ->all();

        return array_merge($notPublished, $empty, $notTranslated);
    }

    public function getTranslations($group)
    {
    	$allTranslations = Translation::where('group', $group)->orderBy('key', 'asc')->get();
        $translations = [];
        foreach($allTranslations as $translation) {
            $translations[$translation->key][$translation->locale] = $translation;
        }

        return $this->treeKeys($translations);
    }

    protected function treeKeys($keys, $delimiter = '.') {
        $list = [];
        $array = [];
        foreach($keys as $key => $value)
            $array[$key] = $key;

        foreach($array as $key) {
            $this->stringToArray($array, $key, $delimiter);
        }

        foreach($array as $key => $value) {
            if(strpos($key, '.') > -1)
                continue;
            $list[$key] = $value;
        }
        unset($array);

        array_walk_recursive($list, function(&$value, $key, $original) {
            $value = $original[$value];
        }, $keys);

        ksort($list);

        return $list;
    }

    public function getConfig($key)
    {
    	return $this->manager->getConfig($key);
    }

    public function update($name, $value)
    {
    	list($locale, $key) = explode('|', $name, 2);
        $segments = explode('.', $key);
        $group = $segments[0];
        unset($segments[0]);
        $key = implode('.', $segments);

        if(in_array($group, $this->manager->getConfig('exclude_groups')))
        	return;

        // update translation
        $translation = Translation::firstOrNew([
            'locale' => $locale,
            'group' => $group,
            'key' => $key,
        ]);
        $translation->value = (string) $value ?: null;
        $translation->status = Translation::STATUS_CHANGED;
        $translation->save();

        // export translations automatically
        $this->manager->exportTranslations($group);
    }

    public function add($group, $keys)
    {
		$keys = explode("\n", $keys);

        foreach($keys as $key) {
            $key = trim($key);
            if($group && $key) {
                $this->manager->missingKey('*', $group, $key);
            }
        }
    }

    public function edit($group, $name, $value)
    {
		list($locale, $key) = explode('|', $name, 2);
        $translation = Translation::firstOrNew([
            'locale' => $locale,
            'group' => $group,
            'key' => $key,
        ]);
        $translation->value = (string) $value ?: null;
        $translation->status = Translation::STATUS_CHANGED;
        $translation->save();
    }

    public function getDeleteParams($groups)
    {
    	return [
    		array_shift($groups),
    		implode('/', $groups)
    	];
    }

    public function remove($group, $key)
    {
        Translation::where('group', $group)->where('key', $key)->delete();
    }

    public function import($replace)
    {
    	return $this->manager->importTranslations($replace);
    }

    public function find()
    {
    	return $this->manager->findTranslations();
    }

    public function cleanEmpty()
    {
    	return $this->manager->cleanTranslations();
    }

    public function removeAll()
    {
    	return $this->manager->truncateTranslations();
    }

    public function publish($group)
    {
    	return $this->manager->exportTranslations($group);
    }

    public function canManage() 
    {
        $func = $this->manager->getConfig('permissions');
        if(is_callable($func))
            return $func();

        return true;
    }

}
