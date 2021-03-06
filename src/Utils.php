<?php
namespace Embed;

/**
 * Some helpers methods used across the library
 */
class Utils
{
    /**
     * Extract all meta elements from html
     *
     * @param \DOMDocument $html
     *
     * @return array with subarrays [name, value, element]
     */
    public static function getMetas(\DOMDocument $html)
    {
        $metas = [];

        foreach ($html->getElementsByTagName('meta') as $meta) {
            $name = trim(strtolower($meta->getAttribute('property') ?: $meta->getAttribute('name')));
            $value = $meta->getAttribute('content') ?: $meta->getAttribute('value');

            $metas[] = [$name, $value, $meta];
        }

        return $metas;
    }

    /**
     * Extract all link elements from html
     *
     * @param \DOMDocument $html
     *
     * @return array with subarrays [rel, href, element]
     */
    public static function getLinks(\DOMDocument $html)
    {
        $links = [];

        foreach ($html->getElementsByTagName('link') as $link) {
            if ($link->hasAttribute('rel') && $link->hasAttribute('href')) {
                $rel = trim(strtolower($link->getAttribute('rel')));
                $href = $link->getAttribute('href');

                $links[] = [$rel, $href, $link];
            }
        }

        return $links;
    }

    /**
     * Search and returns all data retrieved by the providers
     *
     * @param null|array $providers The providers used to retrieve the data
     * @param string     $name      The data name (title, description, image, etc)
     * @param Url        $url       The base url used to resolve relative urls
     *
     * @return array
     */
    public static function getData(array $providers, $name, Url $url = null)
    {
        $method = 'get'.$name;
        $values = [];

        foreach ($providers as $key => $provider) {
            $value = $provider->$method();

            if (empty($value)) {
                continue;
            }

            if (!is_array($value)) {
                $value = [$value];
            }

            foreach ($value as $v) {
                if ($url) {
                    $v = $url->getAbsolute($v);
                }

                if (!isset($values[$v])) {
                    $values[$v] = [
                        'value' => $v,
                        'providers' => [$key],
                    ];
                } else {
                    $values[$v]['providers'][] = $key;
                }
            }
        }

        return array_values($values);
    }

    /**
     * Order the data by provider
     *
     * @param array $values The array provided by self::getData()
     *
     * @return array
     */
    public static function sortByProviders(array $values)
    {
        $sorted = [];

        foreach ($values as $value) {
            foreach ($value['providers'] as $provider) {
                if (!isset($sorted[$provider])) {
                    $sorted[$provider] = [];
                }

                $sorted[$provider][] = $value;
            }
        }

        return $sorted;
    }

    /**
     * Unshifts a new value if it does not exists
     *
     * @param array $values The array provided by self::getData()
     * @param array $value  The value to insert
     */
    public static function unshiftValue(array &$values, $value)
    {
        $key = Utils::searchValue($values, $value['value']);

        if ($key === false) {
            return array_unshift($values, $value);
        }

        $value = array_merge($values[$key], $value);

        array_splice($values, $key, 1);

        return array_unshift($values, $value);
    }

    /**
     * Search by a value and returns its key
     *
     * @param array   $values    The array provided by self::getData()
     * @param string  $value     The value to search
     * @param boolean $returnKey Whether or not return the key instead the value
     *
     * @return array|false
     */
    public static function searchValue(array $values, $value, $returnKey = false)
    {
        foreach ($values as $k => $each) {
            if ($each['value'] === $value) {
                return $returnKey ? $k : $each;
            }
        }

        return false;
    }

    /**
     * Returns the first value if exists
     *
     * @param array   $values    The array provided by self::getData()
     * @param boolean $returnKey Whether or not return the key instead the value
     *
     * @return string|null
     */
    public static function getFirstValue(array $values, $returnKey = false)
    {
        if (isset($values[0])) {
            return $returnKey ? 0 : $values[0]['value'];
        }
    }

    /**
     * Returns the most popular value in an array
     *
     * @param array   $values    The array provided by self::getData()
     * @param boolean $returnKey Whether or not return the key instead the value
     *
     * @return mixed
     */
    public static function getMostPopularValue(array $values, $returnKey = false)
    {
        $mostPopular = null;

        foreach ($values as $k => $value) {
            if ($mostPopular === null || count($value['providers']) > count($values[$mostPopular]['providers'])) {
                $mostPopular = $k;
            }
        }

        if (isset($mostPopular)) {
            return $returnKey ? $mostPopular : $values[$mostPopular]['value'];
        }
    }

    /**
     * Returns the bigger value
     *
     * @param array   $values    The array provided by self::getData()
     * @param boolean $returnKey Whether or not return the key instead the value
     *
     * @return null|string
     */
    public static function getBiggerValue(array $values, $returnKey = false)
    {
        $bigger = null;

        foreach ($values as $k => $value) {
            if ($bigger === null || $value['size'] > $values[$bigger]['size']) {
                $bigger = $k;
            }
        }

        if ($bigger !== null) {
            return $returnKey ? $bigger : $values[$bigger]['value'];
        }
    }

    /**
     * Creates a <video> element
     *
     * @param string       $poster  Poster attribute
     * @param string|array $sources Video sources
     * @param integer      $width   Width attribute
     * @param integer      $height  Height attribute
     *
     * @return string
     */
    public static function videoHtml($poster, $sources, $width = 0, $height = 0)
    {
        $code = self::element('video', [
            'poster' => ($poster ?: null),
            'width' => ($width ?: null),
            'height' => ($height ?: null),
            'controls' => true,
        ]);

        foreach ((array) $sources as $source) {
            $code .= self::element('source', ['src' => $source]);
        }

        return $code.'</video>';
    }

    /**
     * Creates an <audio> element
     *
     * @param string|array $sources Audio sources
     *
     * @return string
     */
    public static function audioHtml($sources)
    {
        $code = "<audio controls>";

        foreach ((array) $sources as $source) {
            $code .= self::element('source', ['src' => $source]);
        }

        return $code.'</audio>';
    }

    /**
     * Creates an <img> element
     *
     * @param string  $src    Image source attribute
     * @param string  $alt    Alt attribute
     * @param integer $width  Width attribute
     * @param integer $height Height attribute
     *
     * @return string
     */
    public static function imageHtml($src, $alt = '', $width = 0, $height = 0)
    {
        return self::element('img', [
            'src' => $src,
            'alt' => $alt,
            'width' => ($width ?: null),
            'height' => ($height ?: null),
        ]);
    }

    /**
     * Creates an <iframe> element
     *
     * @param string  $src          Iframe source attribute
     * @param integer $width        Width attribute
     * @param integer $height       Height attribute
     * @param integer $extra_styles Extra css styles
     *
     * @return string
     */
    public static function iframe($src, $width = 0, $height = 0, $extra_styles = '')
    {
        $width = $width ? (is_int($width) ? $width.'px' : $width) : '600px';
        $height = $height ? (is_int($height) ? $height.'px' : $height) : '400px';

        return self::element('iframe', [
            'src' => $src,
            'frameborder' => 0,
            'allowTransparency' => 'true',
            'style' => "border:none;overflow:hidden;width:$width;height:$height;$extra_styles",
        ]).'</iframe>';
    }

    /**
     * Creates an <iframe> element with a google viewer
     *
     * @param string $src The file loaded by the viewer (pdf, doc, etc)
     *
     * @return string
     */
    public static function google($src)
    {
        return self::iframe('http://docs.google.com/viewer?'.http_build_query([
            'url' => $src,
            'embedded' => 'true',
        ]), 600, 600);
    }

    /**
     * Creates a flash element
     *
     * @param string       $src    The swf file source
     * @param null|integer $width  Width attribute
     * @param null|integer $height Height attribute
     *
     * @return string
     */
    public static function flash($src, $width = null, $height = null)
    {
        $code = self::element('object', [
            'width' => $width ?: 600,
            'height' => $height ?: 400,
            'classid' => 'clsid:D27CDB6E-AE6D-11cf-96B8-444553540000',
            'codebase' => 'http://download.macromedia.com/pub/shockwave/cabs/flash/swflash.cab#version=9,0,47,0',
        ]);

        $code .= self::element('param', ['name' => 'movie', 'value' => $src]);
        $code .= self::element('param', ['name' => 'allowFullScreen', 'value' => 'true']);
        $code .= self::element('param', ['name' => 'allowScriptAccess', 'value' => 'always']);
        $code .= self::element('embed', [
            'src' => $src,
            'width' => $width ?: 600,
            'height' => $height ?: 400,
            'type' => 'application/x-shockwave-flash',
            'allowFullScreen' => 'true',
            'allowScriptAccess' => 'always',
            'pluginspage' => 'http://www.macromedia.com/shockwave/download/index.cgi?P1_Prod_Version=ShockwaveFlash',
        ]);

        return $code.'</embed></object>';
    }

    /**
     * Creates an html element
     *
     * @param string $name  Element name
     * @param array  $attrs Element attributes
     *
     * @return string
     */
    private static function element($name, array $attrs)
    {
        $str = "<$name";

        foreach ($attrs as $name => $value) {
            if ($value === null) {
                continue;
            } elseif ($value === true) {
                $str .= " $name";
            } elseif ($value !== false) {
                $str .= ' '.$name.'="'.htmlspecialchars($value).'"';
            }
        }

        return "$str>";
    }
}
