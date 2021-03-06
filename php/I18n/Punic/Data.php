<?php
namespace Surikat\I18n\Punic;

/**
 * Common data helper stuff
 */
class Data
{
    /**
     * Let's cache already loaded files (locale-specific)
     * @var array
     */
    protected static $cache = array();

    /**
     * Let's cache already loaded files (not locale-specific)
     * @var array
     */
    protected static $cacheGeneric = array();

    /**
     * The current default locale
     * @var string
     */
    protected static $defaultLocale = 'en_US';

    /**
     * The fallback locale (used if default locale is not found)
     * @var string
     */
    protected static $fallbackLocale = 'en_US';

    /**
     * Return the current default locale
     * @return string
     */
    public static function getDefaultLocale()
    {
        return static::$defaultLocale;
    }

    /**
     * Return the current default language
     * @return string
     */
    public static function getDefaultLanguage()
    {
        $info = static::explodeLocale(static::$defaultLocale);

        return $info['language'];
    }

    /**
     * Set the current default locale and language
     * @param string $locale
     * @throws \Surikat\I18n\Punic\Exception\InvalidLocale Throws an exception if $locale is not a valid string
     */
    public static function setDefaultLocale($locale)
    {
        if (is_null(static::explodeLocale($locale))) {
           throw new Exception\InvalidLocale($locale);
        }
        static::$defaultLocale = $locale;
    }

    /**
     * Return the current fallback locale (used if default locale is not found)
     * @return string
     */
    public static function getFallbackLocale()
    {
        return static::$fallbackLocale;
    }

    /**
     * Return the current fallback language (used if default locale is not found)
     * @return string
     */
    public static function getFallbackLanguage()
    {
        $info = static::explodeLocale(static::$fallbackLocale);

        return $info['language'];
    }

    /**
     * Set the current fallback locale and language
     * @param string $locale
     * @throws \Surikat\I18n\Punic\Exception\InvalidLocale Throws an exception if $locale is not a valid string
     */
    public static function setFallbackLocale($locale)
    {
        if (is_null(static::explodeLocale($locale))) {
            throw new Exception\InvalidLocale($locale);
        }
        if (static::$fallbackLocale !== $locale) {
            static::$fallbackLocale = $locale;
            static::$cache = array();
        }
    }

    /**
     * Get the locale data
     * @param string $identifier The data identifier
     * @param string $locale ='' The locale identifier (if empty we'll use the current default locale)
     * @return array
     * @throws \Surikat\I18n\Punic\Exception Throws an exception in case of problems
     * @internal
     */
    public static function get($identifier, $locale = '')
    {
        if (!(is_string($identifier) && strlen($identifier))) {
            throw new Exception\InvalidDataFile($identifier);
        }
        if (empty($locale)) {
            $locale = static::$defaultLocale;
        }
        if (!array_key_exists($locale, static::$cache)) {
            static::$cache[$locale] = array();
        }
        if (!@array_key_exists($identifier, static::$cache[$locale])) {
            if (!@preg_match('/^[a-zA-Z0-1_\\-]+$/i', $identifier)) {
                throw new Exception\InvalidDataFile($identifier);
            }
            $dir = static::getLocaleFolder($locale);
            if (!strlen($dir)) {
                throw new Exception\DataFolderNotFound($locale, static::$fallbackLocale);
            }
            $file = $dir . DIRECTORY_SEPARATOR . $identifier . '.json';
            if (!is_file(__DIR__ . DIRECTORY_SEPARATOR . $file)) {
                throw new Exception\DataFileNotFound($identifier, $locale, static::$fallbackLocale);
            }
            $json = @file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . $file);
            //@codeCoverageIgnoreStart
            // In test enviro we can't replicate this problem
            if ($json === false) {
                throw new Exception\DataFileNotReadable($file);
            }
            //@codeCoverageIgnoreEnd
            $data = @json_decode($json, true);
            //@codeCoverageIgnoreStart
            // In test enviro we can't replicate this problem
            if (!is_array($data)) {
                throw new Exception\BadDataFileContents($file, $json);
            }
            //@codeCoverageIgnoreEnd
            static::$cache[$locale][$identifier] = $data;
        }

        return static::$cache[$locale][$identifier];
    }

    /**
     * Get the generic data
     * @param string $identifier The data identifier
     * @return array
     * @throws Exception Throws an exception in case of problems
     * @internal
     */
    public static function getGeneric($identifier)
    {
        if (!(is_string($identifier) && strlen($identifier))) {
            throw new Exception\InvalidDataFile($identifier);
        }
        if (array_key_exists($identifier, static::$cacheGeneric)) {
            return static::$cacheGeneric[$identifier];
        }
        if (!preg_match('/^[a-zA-Z0-1_\\-]+$/', $identifier)) {
            throw new Exception\InvalidDataFile($identifier);
        }
        $file = 'data' . DIRECTORY_SEPARATOR . "$identifier.json";
        if (!is_file(__DIR__ . DIRECTORY_SEPARATOR . $file)) {
            throw new Exception\DataFileNotFound($identifier);
        }
        $json = @file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . $file);
        //@codeCoverageIgnoreStart
        // In test enviro we can't replicate this problem
        if ($json === false) {
            throw new Exception\DataFileNotReadable($file);
        }
        //@codeCoverageIgnoreEnd
        $data = @json_decode($json, true);
        //@codeCoverageIgnoreStart
        // In test enviro we can't replicate this problem
        if (!is_array($data)) {
            throw new Exception\BadDataFileContents($file, $json);
        }
        //@codeCoverageIgnoreEnd
        static::$cacheGeneric[$identifier] = $data;

        return $data;
    }

    /**
     * Return a list of available locale identifiers
     * @param bool $allowGroups = false Set to true if you want to retrieve locale groups (eg. 'en-001'), false otherwise
     * @return array
     */
    public static function getAvailableLocales($allowGroups = false)
    {
        $locales = array();
        $dir = __DIR__ . DIRECTORY_SEPARATOR . 'data';
        if (is_dir($dir) && is_readable($dir)) {
            $contents = @scandir($dir);
            if (is_array($contents)) {
                foreach (array_diff($contents, array('.', '..')) as $item) {
                    if (is_dir($dir . DIRECTORY_SEPARATOR . $item)) {
                        if ($item === 'root') {
                            $item = 'en-US';
                        }
                        $info = static::explodeLocale($item);
                        if (is_array($info)) {
                            if ((!$allowGroups) && preg_match('/^[0-9]{3}$/', $info['territory'])) {
                                foreach (static::expandTerritoryGroup($info['territory']) as $territory) {
                                    if (strlen($info['script'])) {
                                        $locales[] = "{$info['language']}-{$info['script']}-$territory";
                                    } else {
                                        $locales[] = "{$info['language']}-$territory";
                                    }
                                }
                                $locales[] = $item;
                            } else {
                                $locales[] = $item;
                            }
                        }
                    }
                }
            }
        }

        return $locales;
    }

    /**
     * Try to guess the full locale (with script and territory) ID associated to a language
     * @param string $language ='' The language identifier (if empty we'll use the current default language)
     * @param string $script ='' The script identifier (if $language is empty we'll use the current default script)
     * @return string Returns an empty string if the territory was not found, the territory ID otherwise
     */
    public static function guessFullLocale($language = '', $script = '')
    {
        $result = '';
        if (empty($language)) {
            $defaultInfo = static::explodeLocale(static::$defaultLocale);
            $language = $defaultInfo['language'];
            $script = $defaultInfo['script'];
        }
        $data = static::getGeneric('likelySubtags');
        $keys = array();
        if (!empty($script)) {
            $keys[] = "$language-$script";
        }
        $keys[] = $language;
        foreach ($keys as $key) {
            if (array_key_exists($key, $data)) {
                $result = $data[$key];
                if ((strlen($script) > 0) && (stripos($result, "$language-$script-") !== 0)) {
                    $parts = static::explodeLocale($result);
                    if (!is_null($parts)) {
                        $result = "{$parts['language']}-$script-{$parts['territory']}";
                    }
                }
                break;
            }
        }

        return $result;
    }

    /**
     * Return the terrotory associated to the locale (guess it if it's not present in $locale)
     * @param string $locale ='' The locale identifier (if empty we'll use the current default locale)
     * @return string
     */
    public static function getTerritory($locale = '', $checkFallbackLocale = true)
    {
        $result = '';
        if (empty($locale)) {
            $locale = static::$defaultLocale;
        }
        $info = static::explodeLocale($locale);
        if (is_array($info)) {
            if (!strlen($info['territory'])) {
                $fullLocale = static::guessFullLocale($info['language'], $info['script']);
                if (strlen($fullLocale)) {
                    $info = static::explodeLocale($fullLocale);
                }
            }
            if (strlen($info['territory'])) {
                $result = $info['territory'];
            } elseif ($checkFallbackLocale) {
                $result = static::getTerritory(static::$fallbackLocale, false);
            }
        }

        return $result;
    }

    /**
     * Return the parent of a territory
     * @param string $territory The child territory
     * @return string Returns an empty string if the parent territory was not found, the parent territory ID if found
     */
    protected static function getParentTerritory($territory)
    {
        $result = '';
        if (is_string($territory) && strlen($territory)) {
            foreach (static::getGeneric('territoryContainment') as $parent => $info) {
                if (in_array($territory, $info['contains'], true)) {
                    $result = $parent;
                    break;
                }
            }
        }

        return $result;
    }

    /**
     * Retrieves all the atomic territories belonging to a group.
     * @param string $parentTerritory The parent territory (eg '001')
     * @return array
     */
    protected static function expandTerritoryGroup($parentTerritory)
    {
        $result = array();
        $data = static::getGeneric('territoryContainment');
        if (array_key_exists($parentTerritory, $data)) {
            foreach ($data[$parentTerritory]['contains'] as $child) {
                $grandchildren = static::expandTerritoryGroup($child);
                if (empty($grandchildren)) {
                    $result[] = $child;
                } else {
                    $result = array_merge($result, $grandchildren);
                }
            }
        }

        return $result;
    }

    /**
     * Return the node associated to the locale territory
     * @param string $locale ='' The locale identifier (if empty we'll use the current default locale)
     * @return mixed Returns null if the node was not found, the node data otherwise
     * @internal
     */
    public static function getTerritoryNode($data, $locale = '')
    {
        $result = null;
        $territory = static::getTerritory($locale);
        while (strlen($territory)) {
            if (array_key_exists($territory, $data)) {
                $result = $data[$territory];
                break;
            }
            $territory = static::getParentTerritory($territory);
        }

        return $result;
    }

    /**
     * Return the node associated to the language (not locale) territory
     * @param string $locale ='' The locale identifier (if empty we'll use the current default locale)
     * @return mixed Returns null if the node was not found, the node data otherwise
     * @internal
     */
    public static function getLanguageNode($data, $locale = '')
    {
        $result = null;
        if (empty($locale)) {
            $locale = static::$defaultLocale;
        }
        foreach (static::getLocaleAlternatives($locale) as $l) {
            if (array_key_exists($l, $data)) {
                $result = $data[$l];
                break;
            }
        }

        return $result;
    }

    /**
     * Returns the item of an array associated to a locale
     * @param array $data The data containing the locale info
     * @param string $locale ='' The locale identifier (if empty we'll use the current default locale)
     * @return mixed Returns null if $data is not an array or it does not contain locale info, the array item otherwise
     * @internal
     */
    public static function getLocaleItem($data, $locale = '')
    {
        $result = null;
        if (is_array($data)) {
            if (empty($locale)) {
                $locale = static::$defaultLocale;
            }
            foreach (static::getLocaleAlternatives($locale) as $alternative) {
                if (array_key_exists($alternative, $data)) {
                    $result = $data[$alternative];
                    break;
                }
            }
        }

        return $result;
    }

    /**
     * Parse a string representing a locale and extract its components.
     * @param string $locale
     * @return Return null if $locale is not valid; if $locale is valid returns an array with keys 'language', 'script', 'territory'
     * @internal
     */
    public static function explodeLocale($locale)
    {
        $result = null;
        if (is_string($locale)) {
            if ($locale === 'root') {
                $locale = 'en-US';
            }
            $chunks = explode('-', str_replace('_', '-', strtolower($locale)));
            if (count($chunks) <= 3) {
                if (preg_match('/^[a-z]{2,3}$/', $chunks[0])) {
                    $language = $chunks[0];
                    $script = '';
                    $territory = '';
                    $parentLocale = '';
                    $ok = true;
                    $chunkCount = count($chunks);
                    for ($i = 1; $ok && ($i < $chunkCount); $i++) {
                        if (preg_match('/^[a-z]{4}$/', $chunks[$i])) {
                            if (strlen($script) > 0) {
                                $ok = false;
                            } else {
                                $script = ucfirst($chunks[$i]);
                            }
                        } elseif (preg_match('/^([a-z]{2})|([0-9]{3})$/', $chunks[$i])) {
                            if (strlen($territory) > 0) {
                                $ok = false;
                            } else {
                                $territory = strtoupper($chunks[$i]);
                            }
                        } else {
                            $ok = false;
                        }
                    }
                    if ($ok) {
                        $parentLocales = static::getGeneric('parentLocales');
                        if (strlen($script) && strlen($territory) && array_key_exists("$language-$script-$territory", $parentLocales)) {
                            $parentLocale = $parentLocales["$language-$script-$territory"];
                        } elseif (strlen($script) && array_key_exists("$language-$script", $parentLocales)) {
                            $parentLocale = $parentLocales["$language-$script"];
                        } elseif (strlen($territory) && array_key_exists("$language-$territory", $parentLocales)) {
                            $parentLocale = $parentLocales["$language-$territory"];
                        } elseif (array_key_exists($language, $parentLocales)) {
                            $parentLocale = $parentLocales[$language];
                        }
                        $result = array(
                            'language' => $language,
                            'script' => $script,
                            'territory' => $territory,
                            'parentLocale' => $parentLocale
                        );
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Returns the path of the locale-specific data, looking also for the fallback locale
     * @param string $locale The locale for which you want the data folder
     * @return string Returns an empty string if the folder is not found, the absolute path to the folder otherwise
     */
    protected static function getLocaleFolder($locale)
    {
        static $cache = array();
        $result = '';
        if (is_string($locale)) {
            $key = $locale . '/' . static::$fallbackLocale;
            if (!array_key_exists($key, $cache)) {
                foreach (static::getLocaleAlternatives($locale) as $alternative) {
                    $dir = 'data' . DIRECTORY_SEPARATOR . $alternative;
                    if (is_dir(__DIR__ . DIRECTORY_SEPARATOR . $dir)) {
                        $result = $dir;
                        break;
                    }
                }
                $cache[$key] = $result;
            }
            $result = $cache[$key];
        }

        return $result;
    }

    /**
     * Returns a list of locale identifiers associated to a locale
     * @param string $locale The locale for which you want the alternatives
     * @param string $addFallback = true Set to true to add the fallback locale to the result, false otherwise
     * @return array
     */
    protected static function getLocaleAlternatives($locale, $addFallback = true)
    {
        $result = array();
        $localeInfo = static::explodeLocale($locale);
        if (!is_array($localeInfo)) {
            throw new Exception\InvalidLocale($locale);
        }
        extract($localeInfo);
        if (!strlen($territory)) {
            $fullLocale = static::guessFullLocale($language, $script);
            if (strlen($fullLocale)) {
                extract(static::explodeLocale($fullLocale));
            }
        }
        $territories = array();
        while (strlen($territory) > 0) {
            $territories[] = $territory;
            $territory = static::getParentTerritory($territory);
        }
        if (strlen($script)) {
            foreach ($territories as $territory) {
                $result[] = "{$language}-{$script}-{$territory}";
            }
        }
        if (strlen($script)) {
            $result[] = "{$language}-{$script}";
        }
        foreach ($territories as $territory) {
            $result[] = "{$language}-{$territory}";
            if ("{$language}-{$territory}" === 'en-US') {
                $result[] = 'root';
            }
        }
        if (strlen($parentLocale)) {
            $result = array_merge($result, static::getLocaleAlternatives($parentLocale, false));
        }
        $result[] = $language;
        if ($addFallback && ($locale !== static::$fallbackLocale)) {
            $result = array_merge($result, static::getLocaleAlternatives(static::$fallbackLocale, false));
        }
        for ($i = count($result) - 1; $i > 1; $i--) {
            for ($j = 0; $j < $i; $j++) {
                if ($result[$i] === $result[$j]) {
                    array_splice($result, $i, 1);
                    break;
                }
            }
        }
        $i = array_search('root', $result, true);
        if ($i !== false) {
            array_splice($result, $i, 1);
            $result[] = 'root';
        }

        return $result;
    }
}
