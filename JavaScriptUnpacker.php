<?php
/**
 * JavaScriptUnpacker.php
 * @author Andrey Izman <izmanw@gmail.com>
 * @link https://github.com/mervick/JavaScriptUnpacker
 * @license MIT
 */

/**
 * Class JavaScriptUnpacker
 */
class JavaScriptUnpacker
{
    /**
     * @var string
     */
    protected static $JS_FUNC = 'eval(function(p,a,c,k,e,';

    /**
     * @var string
     */
    protected $script;

    /**
     * @param $script
     * @return string
     */
    public static function unpack($script)
    {
        return (new self($script))->deobfuscate();
    }

    /**
     * @param $script
     */
    protected function __construct($script)
    {
        $this->script = $script;
    }

    /**
     * @return string
     */
    protected function deobfuscate()
    {
        if (self::hasPackedCode($this->script, $start) &&
            (($body = $this->findBlock('{', '}', $start + strlen(self::$JS_FUNC), $pos))) &&
            (($params = $this->findBlock('(', ')', $pos + strlen($body), $pos))) &&
            (($end = strpos($this->script, ')', $pos + strlen($params)))) &&
            (($packed = self::findString($params, 1, $pos, $quote))) &&
            (($keywords = self::findString($params, $offset = $pos + strlen($packed) + 2, $pos))) &&
            (preg_match('/^,([0-9]+),([0-9]+),$/', preg_replace('/[\x03-\x20]+/', '',
                substr($params, $offset, $pos - $offset)), $matches)))
        {
            list(, $ascii, $count) = $matches;
            $encoding = self::detectEncoding($body);
            $packed   = self::stripSlashes($packed, $quote);
            $decode = $encoding === 95 ? 'decode95' : 'decode62';
            $script = self::replaceSpecials($this->$decode($packed, $ascii, $count, explode('|', $keywords)));
            return substr($this->script, 0, $start) . $script . self::unpack(substr($this->script, $end + 1));
        }
        return $this->script;
    }

    /**
     * @param string $str
     * @return string
     */
    protected static function replaceSpecials($str)
    {
        $replace = function($str) {
            return str_replace(['\\n', '\\r'], ["\n", "\r"], $str);
        };
        $pieces = [];
        $offset = $pos = 0;
        while ($string = self::findString($str, $offset, $pos, $quote)) {
            $pieces[] = $replace(substr($str, $offset, $pos - $offset));
            $pieces[] = $string = "$quote$string$quote";
            $offset = $pos + strlen($string);
        }
        $pieces[] = $replace(substr($str, $offset));
        return implode('', $pieces);
    }

    /**
     * @param string $param
     * @param string $strQuote
     * @return string
     */
    protected static function stripSlashes($param, $strQuote)
    {
        for ($i = 0, $len = strlen($param); $i < $len; $i++) {
            foreach (['"', "'"] as $quote) {
                if ($param{$i} === $quote) {
                    for ($j = 0; $j < $i; $j++) {
                        if ($param{$i - $j - 1} !== '\\') {
                            if ($strQuote === $quote) {
                                $j--;
                            }
                            break;
                        }
                    }
                    if ($j > 0) {
                        $esc = str_repeat('\\', $j);
                        foreach (['"', "'"] as $quote) {
                            $param = str_replace("{$esc}{$quote}", $quote, $param);
                        }
                    }
                    return stripslashes(str_replace("\\$strQuote", $strQuote, $param));
                }
            }
        }
        return $param;
    }

    /**
     * @param string $packed
     * @param int $ascii
     * @param int $count
     * @param string $keywords
     * @return string
     */
    protected function decode62($packed, $ascii, $count, $keywords)
    {
        $packed = " $packed ";
        $base = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $encode = function ($count) use (&$encode, $ascii, $base) {
            return ($count < $ascii ? '' : $encode(intval($count / $ascii))) . $base{$count % $ascii};
        };
        $split = '([^a-zA-Z0-9_])';
        while ($count--) {
            if (!empty($keywords[$count])) {
                $pattern = '/' . $split . preg_quote($encode($count)) . $split . '/';
                $packed = preg_replace_callback($pattern, function($matches) use ($keywords, $count) {
                    return $matches[1] . $keywords[$count] . $matches[2];
                }, $packed);
            }
        }
        return substr($packed, 1, -1);
    }

    /**
     * @param string $packed
     * @param int $ascii
     * @param int $count
     * @param string $keywords
     * @return string
     */
    protected function decode95($packed, $ascii, $count, $keywords)
    {
        $encode = function ($count) use (&$encode, $ascii) {
            return ($count < $ascii ? '' : $encode(intval($count / $ascii))) .
                mb_convert_encoding(pack('N', $count % $ascii + 161), 'UTF-8', 'UCS-4BE');
        };
        while ($count--) {
            $encoded = $encode($count);
            $decoded[$encoded] = !empty($keywords[$count]) ? $keywords[$count] : $encoded;
        }
        return preg_replace_callback('/([\xa1-\xff]+)/', function($match) use ($decoded) {
            return isset($decoded[$match[1]]) ? $decoded[$match[1]] : $match[1];
        }, $packed);
    }

    /**
     * @param string $buf
     * @param int $offset
     * @param null|int $start
     * @param null|string $quote
     * @return bool|string
     */
    protected static function findString($buf, $offset, &$start=null, &$quote=null)
    {
        $len = strlen($buf);
        for ($start = $offset; $start < $len; $start++) {
            foreach (['"', "'"] as $quote) {
                if ($buf{$start} === $quote) {
                    for ($i = $start + 1; $i < $len && ($buf{$i} !== $quote || $buf{$i - 1} === '\\'); $i++) ;
                    return substr($buf, $start + 1, $i - $start - 1);
                }
            }
        }
        return false;
    }

    /**
     * @param string $open
     * @param string $close
     * @param int $offset
     * @param int $start
     * @return string|false
     */
    protected function findBlock($open, $close, $offset, &$start)
    {
        $buf = substr($this->script, $offset);
        $len = strlen($buf);
        for ($start = 0; $start < $len && $buf{$start} !== $open; $start++);
        for ($i = $start + 1, $skip = 0; $i < $len; $i++) {
            if ($buf{$i} === $close && 0 === $skip--) {
                break;
            }
            foreach (['"', "'"] as $quote) {
                if ($buf{$i} === $quote) {
                    for ($i++; $i < $len && ($buf{$i} !== $quote || $buf{$i - 1} === '\\'); $i++);
                }
            }
            if ($buf{$i} === $open) {
                $skip++;
            }
        }
        if ($start === $len || $i === $len) {
            return false;
        }
        $block = substr($buf, $start, $i - $start + 1);
        $start += $offset;

        return $block;
    }

    /**
     * @param string $body
     * @return int
     */
    protected static function detectEncoding($body)
    {
        $body = explode('|', preg_replace('/[^0-9]+/', '|', $body));
        if (in_array('35', $body) && in_array('29', $body)) {
            return 62;
        }
        if (in_array('161', $body)) {
            return 95;
        }
        if (in_array('36', $body)) {
            return 36;
        }
        return 10;
    }

    /**
     * @param string $str
     * @param null|int $start
     * @return bool
     */
    public static function hasPackedCode($str, &$start=null)
    {
        if (($pos = strpos(strtolower(preg_replace('/[\x03-\x20]+/', '', $str)), self::$JS_FUNC)) !== false) {
            $start = -1;
            do {
                while (preg_match('/[\x03-\x20]/', $str{++$start}));
            } while (0 < $pos--);
            return true;
        }
        return false;
    }
}
